<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // daftar difilter dengan scope visibleTo()
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin() || $project->roleOf($user) !== null;
    }

    public function create(User $user): bool
    {
        return $user->can('project.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->canInProject('project.update', $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('project.delete');
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->canInProject('project.assign-member', $project);
    }

    public function markDone(User $user, Project $project): bool
    {
        return $project->status !== ProjectStatus::Done && $user->canInProject('project.mark-done', $project);
    }

    public function manageSprints(User $user, Project $project): bool
    {
        return $project->status !== ProjectStatus::Done && $user->canInProject('sprint.manage', $project);
    }

    public function markSprintDone(User $user, Project $project): bool
    {
        return $project->status !== ProjectStatus::Done && $user->canInProject('sprint.mark-done', $project);
    }

    public function createBacklog(User $user, Project $project): bool
    {
        return $project->status !== ProjectStatus::Done && $user->canInProject('backlog.create', $project);
    }

    public function editDocs(User $user, Project $project): bool
    {
        return $user->canInProject('documentation.create-update', $project);
    }

    public function deleteDocs(User $user, Project $project): bool
    {
        return $user->canInProject('documentation.delete', $project);
    }
}
