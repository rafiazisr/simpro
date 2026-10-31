<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\StatusCategory;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use App\Support\HtmlSanitizer;
use App\Support\ProjectRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = ProjectStatus::tryFrom((string) $request->query('status'));
        $view = $request->query('view') === 'table' ? 'table' : 'cards';

        $base = Project::query()->visibleTo($user);

        $projects = (clone $base)
            ->search($request->query('q'))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['members' => fn ($q) => $q->orderBy('project_user.id')])
            ->withCount([
                'backlogs',
                'backlogs as done_backlogs_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('category', StatusCategory::Done)),
                'sprints as active_sprints_count' => fn ($q) => $q->where('status', SprintStatus::Active),
            ])
            ->orderByRaw("case status when 'active' then 0 when 'on_hold' then 1 else 2 end")
            ->orderBy('start_date', 'desc')
            ->paginate(24)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'view' => $view,
            'status' => $status,
            'total' => (clone $base)->count(),
            'activeCount' => (clone $base)->where('status', ProjectStatus::Active)->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Project::class);

        return view('projects.form', [
            'project' => null,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']),
            'roleOptions' => ProjectRoles::options(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(function () use ($request) {
            $project = Project::create([
                ...$request->safe()->only(['code', 'name', 'start_date', 'end_date', 'status']),
                'description' => HtmlSanitizer::clean($request->input('description')),
                'color' => Project::COLORS[Project::withTrashed()->count() % count(Project::COLORS)],
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->input('members') as $m) {
                $project->members()->attach($m['user_id'], ['project_role' => $m['role']]);
            }

            return $project;
        });

        foreach ($request->input('members') as $m) {
            if ((int) $m['user_id'] !== $request->user()->id) {
                User::find($m['user_id'])?->notify(new ProjectAssigned($project, $m['role'], $request->user()));
            }
        }

        return redirect()->route('projects.show', $project)->with('toast', "Project {$project->name} berhasil dibuat.");
    }

    public function show(Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['members' => fn ($q) => $q->withTrashed()->orderBy('name')]);

        $byStatus = $project->availableStatuses()->map(fn ($s) => [
            'status' => $s,
            'count' => $project->backlogs()->where('status_id', $s->id)->count(),
        ])->filter(fn ($row) => $row['count'] > 0 || ! $row['status']->isCustom())->values();

        $total = $byStatus->sum('count');
        $done = $byStatus->filter(fn ($r) => $r['status']->category === StatusCategory::Done)->sum('count');

        return view('projects.show', [
            'project' => $project,
            'tab' => 'ringkasan',
            'byStatus' => $byStatus,
            'stats' => [
                'total' => $total,
                'done' => $done,
                'new_this_week' => $project->backlogs()->where('created_at', '>=', now()->startOfWeek())->count(),
                'ready_for_qa' => $project->backlogs()->whereHas('status', fn ($q) => $q->where('category', StatusCategory::ReadyForQa))->count(),
                'late' => $project->backlogs()->notDone()->whereDate('end_date', '<', today())->count(),
            ],
            'activeSprint' => $project->sprints()->where('status', SprintStatus::Active)->first(),
            'membersByRole' => $project->members->groupBy('pivot.project_role'),
            'roleOptions' => ProjectRoles::options(),
        ]);
    }

    public function edit(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects.form', [
            'project' => $project,
            'users' => collect(),
            'roleOptions' => ProjectRoles::options(),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'start_date', 'end_date', 'status']);
        $data['description'] = HtmlSanitizer::clean($request->input('description'));

        if ($data['status'] === ProjectStatus::Done->value && $project->status !== ProjectStatus::Done) {
            Gate::authorize('markDone', $project);
            $data['done_at'] = now();
        } elseif ($data['status'] !== ProjectStatus::Done->value) {
            $data['done_at'] = null;
        }

        $project->update($data);

        return redirect()->route('projects.show', $project)->with('toast', 'Project berhasil disimpan.');
    }

    public function markDone(Project $project): RedirectResponse
    {
        Gate::authorize('markDone', $project);

        DB::transaction(function () use ($project) {
            $project->update(['status' => ProjectStatus::Done, 'done_at' => now()]);
            $project->sprints()->where('status', '!=', SprintStatus::Done)->update([
                'status' => SprintStatus::Done, 'done_at' => now(),
            ]);
        });

        return back()->with('toast', "Project {$project->name} ditandai selesai.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::authorize('delete', $project);

        $project->delete(); // soft delete

        return redirect()->route('projects.index')->with('toast', "Project {$project->name} dihapus.");
    }
}
