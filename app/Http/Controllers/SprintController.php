<?php

namespace App\Http\Controllers;

use App\Enums\SprintStatus;
use App\Enums\StatusCategory;
use App\Http\Requests\SprintRequest;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SprintController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        return view('projects.sprints', $this->listData($request, $project));
    }

    public function create(Request $request, Project $project): View
    {
        Gate::authorize('manageSprints', $project);

        $next = $project->sprints()->count() + 1;

        return view('projects.sprints', ['modal' => 'form', 'editing' => null, 'suggestedName' => "Sprint {$next}"] + $this->listData($request, $project));
    }

    public function store(SprintRequest $request, Project $project): RedirectResponse
    {
        $sprint = $project->sprints()->create($request->validated() + [
            'done_at' => $request->input('status') === SprintStatus::Done->value ? now() : null,
        ]);

        return redirect()->route('projects.sprints.index', $project)->with('toast', "{$sprint->name} berhasil dibuat.");
    }

    public function edit(Request $request, Project $project, Sprint $sprint): View
    {
        Gate::authorize('manageSprints', $project);
        abort_unless($sprint->project_id === $project->id, 404);

        return view('projects.sprints', ['modal' => 'form', 'editing' => $sprint, 'suggestedName' => null] + $this->listData($request, $project));
    }

    public function update(SprintRequest $request, Project $project, Sprint $sprint): RedirectResponse
    {
        abort_unless($sprint->project_id === $project->id, 404);

        $data = $request->validated();
        $toDone = $data['status'] === SprintStatus::Done->value;

        if ($toDone && $sprint->status !== SprintStatus::Done) {
            Gate::authorize('markSprintDone', $project);
        }

        $sprint->update($data + ['done_at' => $toDone ? ($sprint->done_at ?? now()) : null]);

        return redirect()->route('projects.sprints.index', $project)->with('toast', "{$sprint->name} berhasil disimpan.");
    }

    public function markDone(Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('markSprintDone', $project);
        abort_unless($sprint->project_id === $project->id, 404);

        $sprint->update(['status' => SprintStatus::Done, 'done_at' => now()]);

        return back()->with('toast', "{$sprint->name} ditandai selesai.");
    }

    public function destroy(Project $project, Sprint $sprint): RedirectResponse
    {
        Gate::authorize('manageSprints', $project);
        abort_unless($sprint->project_id === $project->id, 404);

        $moved = $sprint->backlogs()->count();
        $sprint->delete();

        return redirect()->route('projects.sprints.index', $project)
            ->with('toast', "{$sprint->name} dihapus".($moved ? ", {$moved} backlog dipindah ke Product Backlog." : '.'));
    }

    private function listData(Request $request, Project $project): array
    {
        Gate::authorize('view', $project);

        $status = SprintStatus::tryFrom((string) $request->query('status'));

        $sprints = $project->sprints()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['backlogs.status'])
            ->get()
            ->each(function (Sprint $s) {
                $s->setAttribute('total_count', $s->backlogs->count());
                $s->setAttribute('done_count', $s->backlogs->filter(fn ($b) => $b->status->category === StatusCategory::Done)->count());
                $s->setAttribute('mix', $s->backlogs->groupBy(fn ($b) => $b->status->style ?? 'custom')->map->count());
            });

        return [
            'project' => $project->loadCount(['sprints', 'backlogs', 'members']),
            'tab' => 'sprint',
            'sprints' => $sprints,
            'statusFilter' => $status,
            'modal' => null,
            'editing' => null,
            'suggestedName' => null,
        ];
    }
}
