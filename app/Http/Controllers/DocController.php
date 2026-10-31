<?php

namespace App\Http\Controllers;

use App\Models\Backlog;
use App\Models\DocFeature;
use App\Models\DocModule;
use App\Models\Project;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()->visibleTo($request->user())
            ->withCount('docModules')
            ->addSelect(['features_count' => DocFeature::query()->selectRaw('count(*)')
                ->join('doc_modules', 'doc_modules.id', '=', 'doc_features.doc_module_id')
                ->whereColumn('doc_modules.project_id', 'projects.id')])
            ->orderBy('name')->get();

        return view('docs.index', ['projects' => $projects]);
    }

    public function project(Project $project): View|RedirectResponse
    {
        Gate::authorize('view', $project);

        $first = DocFeature::query()
            ->join('doc_modules', 'doc_modules.id', '=', 'doc_features.doc_module_id')
            ->where('doc_modules.project_id', $project->id)
            ->orderBy('doc_modules.sort_order')->orderBy('doc_modules.id')
            ->orderBy('doc_features.sort_order')->orderBy('doc_features.id')
            ->select('doc_features.*')->first();

        if ($first) {
            return redirect()->route('projects.docs.show', [$project, $first]);
        }

        return view('docs.show', ['feature' => null, 'mode' => 'view'] + $this->treeData($project));
    }

    public function show(Project $project, DocFeature $feature): View
    {
        $this->authorizeFeature($project, $feature, 'view');

        $feature->load(['editor', 'backlogs' => fn ($q) => $q->with(['status', 'programmer', 'qa'])->orderBy('number')]);

        return view('docs.show', [
            'feature' => $feature,
            'mode' => request('mode') === 'link' && request()->user()->can('editDocs', $project) ? 'link' : 'view',
            'linkCandidates' => $project->backlogs()->whereNotIn('id', $feature->backlogs->pluck('id'))->orderBy('number')->get(['id', 'code', 'title']),
        ] + $this->treeData($project));
    }

    public function create(Project $project, DocModule $module): View
    {
        Gate::authorize('editDocs', $project);
        abort_unless($module->project_id === $project->id, 404);

        return view('docs.show', ['feature' => null, 'mode' => 'edit', 'module' => $module] + $this->treeData($project));
    }

    public function store(Request $request, Project $project, DocModule $module): RedirectResponse
    {
        Gate::authorize('editDocs', $project);
        abort_unless($module->project_id === $project->id, 404);

        $data = $this->validateFeature($request);

        $feature = $module->features()->create([
            'title' => $data['title'],
            'content' => HtmlSanitizer::clean($data['content'] ?? null, allowImages: true),
            'sort_order' => (int) $module->features()->max('sort_order') + 1,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.docs.show', [$project, $feature])->with('toast', "Fitur {$feature->title} disimpan.");
    }

    public function edit(Project $project, DocFeature $feature): View
    {
        $this->authorizeFeature($project, $feature, 'editDocs');

        return view('docs.show', ['feature' => $feature, 'mode' => 'edit', 'module' => $feature->module] + $this->treeData($project));
    }

    public function update(Request $request, Project $project, DocFeature $feature): RedirectResponse
    {
        $this->authorizeFeature($project, $feature, 'editDocs');

        $data = $this->validateFeature($request, $project);
        $feature->update([
            'title' => $data['title'],
            'content' => HtmlSanitizer::clean($data['content'] ?? null, allowImages: true),
            'doc_module_id' => $data['doc_module_id'] ?? $feature->doc_module_id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('projects.docs.show', [$project, $feature])->with('toast', 'Dokumentasi disimpan.');
    }

    public function destroy(Project $project, DocFeature $feature): RedirectResponse
    {
        $this->authorizeFeature($project, $feature, 'deleteDocs');

        $feature->delete();

        return redirect()->route('projects.docs.index', $project)->with('toast', "Fitur {$feature->title} dihapus.");
    }

    public function moveFeature(Request $request, Project $project, DocFeature $feature): RedirectResponse
    {
        $this->authorizeFeature($project, $feature, 'editDocs');

        $this->move($feature->module->features()->get(), $feature->id, $request->input('dir'));

        return back();
    }

    // ---- Modul ----

    public function storeModule(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('editDocs', $project);

        $data = $request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nama modul']);

        $module = $project->docModules()->create([
            'name' => $data['name'],
            'sort_order' => (int) $project->docModules()->max('sort_order') + 1,
        ]);

        return redirect()->route('projects.docs.features.create', [$project, $module])->with('toast', "Modul {$module->name} dibuat. Tambahkan fitur pertamanya.");
    }

    public function updateModule(Request $request, Project $project, DocModule $module): RedirectResponse
    {
        Gate::authorize('editDocs', $project);
        abort_unless($module->project_id === $project->id, 404);

        $module->update($request->validate(['name' => ['required', 'string', 'max:100']], [], ['name' => 'nama modul']));

        return back()->with('toast', 'Nama modul diubah.');
    }

    public function destroyModule(Project $project, DocModule $module): RedirectResponse
    {
        Gate::authorize('deleteDocs', $project);
        abort_unless($module->project_id === $project->id, 404);

        $count = $module->features()->count();
        $module->delete(); // fitur ikut terhapus (cascade)

        return redirect()->route('projects.docs.index', $project)
            ->with('toast', "Modul {$module->name} dihapus".($count ? " beserta {$count} fitur." : '.'));
    }

    public function moveModule(Request $request, Project $project, DocModule $module): RedirectResponse
    {
        Gate::authorize('editDocs', $project);
        abort_unless($module->project_id === $project->id, 404);

        $this->move($project->docModules()->get(), $module->id, $request->input('dir'));

        return back();
    }

    public function linkBacklogs(Request $request, Project $project, DocFeature $feature): RedirectResponse
    {
        $this->authorizeFeature($project, $feature, 'editDocs');

        $data = $request->validate([
            'backlog_ids' => ['required', 'array', 'min:1'],
            'backlog_ids.*' => [Rule::exists('backlogs', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
        ], ['backlog_ids.required' => 'Pilih minimal 1 backlog.']);

        $feature->backlogs()->syncWithoutDetaching($data['backlog_ids']);

        return redirect()->route('projects.docs.show', [$project, $feature])
            ->with('toast', count($data['backlog_ids']).' backlog dihubungkan.');
    }

    public function unlinkBacklog(Project $project, DocFeature $feature, Backlog $backlog): RedirectResponse
    {
        $this->authorizeFeature($project, $feature, 'editDocs');

        $feature->backlogs()->detach($backlog->id);

        return back()->with('toast', "{$backlog->code} dilepas dari fitur ini.");
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'project' => ['required', 'string'],
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'file.max' => 'Ukuran gambar maksimal 2 MB.',
            'file.mimes' => 'Format gambar harus jpg, png, atau webp.',
        ]);

        $project = Project::where('code', $request->input('project'))->firstOrFail();
        Gate::authorize('editDocs', $project);

        $file = $request->file('file');
        $name = Str::lower($project->code).'-'.Str::random(20).'.'.$file->extension();
        $path = $file->storeAs('docs', $name, 'public');

        return response()->json(['url' => '/storage/'.$path, 'name' => $file->getClientOriginalName()]);
    }

    // ---- Helper ----

    private function treeData(Project $project): array
    {
        $project->load('members')->loadCount(['sprints', 'backlogs']);

        return [
            'project' => $project,
            'tab' => 'docs',
            'modules' => $project->docModules()->with('features:id,doc_module_id,title,sort_order')->get(),
            'module' => null,
            'linkCandidates' => collect(),
        ];
    }

    private function authorizeFeature(Project $project, DocFeature $feature, string $ability): void
    {
        abort_unless($feature->module && $feature->module->project_id === $project->id, 404);
        Gate::authorize($ability, $project);
    }

    private function validateFeature(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['nullable', 'string', 'max:500000'],
            'doc_module_id' => $project
                ? ['nullable', Rule::exists('doc_modules', 'id')->where('project_id', $project->id)]
                : ['nullable'],
        ], [], ['title' => 'judul fitur', 'content' => 'isi dokumentasi', 'doc_module_id' => 'modul']);
    }

    private function move($items, int $id, ?string $dir): void
    {
        $items = $items->values();
        $i = $items->search(fn ($m) => $m->id === $id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;

        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }

        $ordered = $items->all();
        [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];

        DB::transaction(function () use ($ordered) {
            foreach ($ordered as $pos => $item) {
                $item->forceFill(['sort_order' => $pos + 1])->saveQuietly();
            }
        });
    }
}
