<?php

namespace App\Http\Controllers;

use App\Models\Backlog;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectAssigned;
use App\Services\ProjectMemberService;
use App\Support\ProjectRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectMemberController extends Controller
{
    public function __construct(private ProjectMemberService $members)
    {
    }

    public function index(Project $project): View
    {
        Gate::authorize('view', $project);

        $project->load(['members' => fn ($q) => $q->withTrashed()->orderBy('name')]);

        $activeCounts = Backlog::query()
            ->where('project_id', $project->id)->notDone()
            ->get(['programmer_id', 'qa_id'])
            ->flatMap(fn ($b) => array_filter([$b->programmer_id, $b->qa_id]))
            ->countBy();

        return view('projects.members', [
            'project' => $project,
            'tab' => 'anggota',
            'activeCounts' => $activeCounts,
            'candidates' => User::where('is_active', true)
                ->whereNotIn('id', $project->members->pluck('id'))
                ->orderBy('name')->get(['id', 'name', 'email']),
            'roleOptions' => ProjectRoles::options(),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'role' => ['required', Rule::in(ProjectRoles::options()->keys()->all())],
        ], ['user_id.required' => 'Pilih user yang akan ditambahkan.'], ['role' => 'role project']);

        if ($project->members()->where('users.id', $data['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'User sudah menjadi anggota project ini.']);
        }

        $project->members()->attach($data['user_id'], ['project_role' => $data['role']]);
        $user = User::find($data['user_id']);

        if ($user->id !== $request->user()->id) {
            $user->notify(new ProjectAssigned($project, $data['role'], $request->user()));
        }

        return back()->with('toast', "{$user->name} ditambahkan sebagai ".ProjectRoles::label($data['role']).'.');
    }

    public function update(Request $request, Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $data = $request->validate(['role' => ['required', Rule::in(ProjectRoles::options()->keys()->all())]]);
        $current = $project->roleOf($user);
        abort_if($current === null, 404);

        if ($current === 'pm' && $data['role'] !== 'pm' && $this->pmCount($project) <= 1) {
            return back()->withErrors(['role' => 'Project harus memiliki minimal 1 Project Manager.']);
        }

        if ($this->activeBacklogs($project, $user) > 0 && $current !== $data['role']) {
            return back()->withErrors(['role' => "{$user->name} masih punya backlog aktif. Assign ulang backlog-nya sebelum mengganti role."]);
        }

        $project->members()->updateExistingPivot($user->id, ['project_role' => $data['role']]);

        return back()->with('toast', "Role {$user->name} diubah menjadi ".ProjectRoles::label($data['role']).'.');
    }

    public function destroy(Project $project, User $user): RedirectResponse
    {
        Gate::authorize('manageMembers', $project);

        $role = $project->roleOf($user);
        abort_if($role === null, 404);

        if ($role === 'pm' && $this->pmCount($project) <= 1) {
            return back()->withErrors(['member' => 'Project harus memiliki minimal 1 Project Manager.']);
        }

        if (($n = $this->activeBacklogs($project, $user)) > 0) {
            return back()->withErrors(['member' => "{$user->name} masih punya {$n} backlog aktif. Assign ulang backlog-nya sebelum dikeluarkan."]);
        }

        $project->members()->detach($user->id);

        return back()->with('toast', "{$user->name} dikeluarkan dari project.");
    }

    private function pmCount(Project $project): int
    {
        return $this->members->pmCount($project);
    }

    private function activeBacklogs(Project $project, User $user): int
    {
        return $this->members->activeBacklogs($project, $user);
    }
}
