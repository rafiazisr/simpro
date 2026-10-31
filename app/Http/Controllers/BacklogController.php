<?php

namespace App\Http\Controllers;

use App\Enums\Priority;
use App\Http\Requests\BacklogRequest;
use App\Models\Backlog;
use App\Models\Project;
use App\Models\User;
use App\Services\BacklogService;
use App\Services\StatusService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BacklogController extends Controller
{
    public function __construct(
        private readonly BacklogService $backlogs,
        private readonly StatusService $statuses,
    ) {}

    public function index(Request $request, Project $project): View
    {
        return view('projects.backlogs', $this->listData($request, $project));
    }

    public function create(Request $request, Project $project): View
    {
        Gate::authorize('createBacklog', $project);

        return view('projects.backlogs', ['drawer' => 'form', 'editing' => null] + $this->listData($request, $project));
    }

    public function store(BacklogRequest $request, Project $project): RedirectResponse
    {
        $backlog = $this->backlogs->create($project, $this->payload($request, $project, null), $request->user());

        if ($request->boolean('again')) {
            return redirect()->route('projects.backlogs.create', [$project, 'again' => 1])
                ->with('toast', "{$backlog->code} berhasil disimpan.");
        }

        return redirect()->route('projects.backlogs.index', $project)->with('toast', "{$backlog->code} berhasil disimpan.");
    }

    public function show(Backlog $backlog): View
    {
        Gate::authorize('view', $backlog);

        $backlog->load(['project', 'sprint', 'status', 'programmer', 'qa', 'creator', 'activities.user', 'docFeatures.module', 'docFeatures.editor']);
        $user = request()->user();

        return view('backlogs.show', [
            'backlog' => $backlog,
            'project' => $backlog->project,
            'statusOptions' => $backlog->project->availableStatuses(),
            'canUpdateStatus' => $user->can('updateStatus', $backlog),
            'canEdit' => $user->can('update', $backlog),
            'isProgrammer' => $backlog->programmer_id === $user->id,
            'isQa' => $backlog->qa_id === $user->id,
        ]);
    }

    public function edit(Request $request, Backlog $backlog): View
    {
        Gate::authorize('update', $backlog);

        return view('projects.backlogs', ['drawer' => 'form', 'editing' => $backlog] + $this->listData($request, $backlog->project));
    }

    public function update(BacklogRequest $request, Backlog $backlog): RedirectResponse
    {
        $this->backlogs->update($backlog, $this->payload($request, $backlog->project, $backlog), $request->user());

        return redirect()->to($request->input('return_to') === 'detail'
            ? route('backlogs.show', $backlog)
            : route('projects.backlogs.index', $backlog->project))
            ->with('toast', "{$backlog->code} berhasil disimpan.");
    }

    public function destroy(Backlog $backlog): RedirectResponse
    {
        Gate::authorize('delete', $backlog);

        $backlog->delete();

        return redirect()->route('projects.backlogs.index', $backlog->project)->with('toast', "{$backlog->code} dihapus.");
    }

    private function payload(BacklogRequest $request, Project $project, ?Backlog $backlog): array
    {
        $user = $request->user();
        $data = $request->safe()->only(['title', 'sprint_id', 'programmer_id', 'qa_id']);
        $data['detail'] = HtmlSanitizer::clean($request->input('detail'));
        $data['status_id'] = $this->statuses->resolve($project, $request->input('status_name'))->id;

        if ($user->canInProject('priority.manage', $project)) {
            $data['priority'] = $request->input('priority');
        } elseif (! $backlog) {
            $data['priority'] = Priority::Medium->value;
        }

        if ($user->canInProject('timeline.manage', $project)) {
            $data['start_date'] = $request->input('start_date') ?: null;
            $data['end_date'] = $request->input('end_date') ?: null;
        }

        return $data;
    }

    private function listData(Request $request, Project $project): array
    {
        Gate::authorize('view', $project);

        $project->load('members')->loadCount(['sprints', 'backlogs', 'members']);
        $view = $request->query('view') === 'board' ? 'board' : 'table';
        $sprints = $project->sprints()->get();
        $statuses = $project->availableStatuses();

        $query = $project->backlogs()
            ->with(['status', 'programmer', 'qa', 'sprint'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")))
            ->when($request->query('sprint'), fn ($q, $s) => $s === 'none' ? $q->whereNull('sprint_id') : $q->where('sprint_id', $s))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status_id', $s))
            ->when(Priority::tryFrom((string) $request->query('priority')), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('assignee'), fn ($q, $u) => $q->where(fn ($q) => $q->where('programmer_id', $u)->orWhere('qa_id', $u)));

        $backlogs = $query->orderBy('number')->get();

        $boardSprint = null;
        if ($view === 'board' && ! $request->filled('sprint')) {
            $boardSprint = $sprints->firstWhere('status.value', 'active') ?? $sprints->first();
            if ($boardSprint) {
                $backlogs = $backlogs->where('sprint_id', $boardSprint->id)->values();
            }
        }

        return [
            'project' => $project,
            'tab' => 'backlog',
            'view' => $view,
            'sprints' => $sprints,
            'statuses' => $statuses,
            'backlogs' => $backlogs,
            'grouped' => $backlogs->groupBy(fn (Backlog $b) => $b->sprint_id ?? 'none'),
            'boardSprint' => $boardSprint,
            'assignees' => $project->members->sortBy('name')->values(),
            'programmers' => $project->membersWithRole('programmer'),
            'qas' => $project->membersWithRole('qa'),
            'filtered' => $request->hasAny(['q', 'sprint', 'status', 'priority', 'assignee']),
            'drawer' => null,
            'editing' => null,
        ];
    }
}
