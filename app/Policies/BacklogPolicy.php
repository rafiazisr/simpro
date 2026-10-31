<?php

namespace App\Policies;

use App\Models\Backlog;
use App\Models\User;

class BacklogPolicy
{
    public function view(User $user, Backlog $backlog): bool
    {
        return $user->can('view', $backlog->project);
    }

    public function update(User $user, Backlog $backlog): bool
    {
        return $user->canInProject('backlog.update-delete', $backlog->project);
    }

    public function delete(User $user, Backlog $backlog): bool
    {
        return $user->canInProject('backlog.update-delete', $backlog->project);
    }

    public function updateStatus(User $user, Backlog $backlog): bool
    {
        if ($user->canInProject('backlog.update-delete', $backlog->project)) {
            return true;
        }

        return $backlog->isAssignedTo($user)
            && $user->canInProject('backlog.update-status', $backlog->project);
    }

    public function setPriority(User $user, Backlog $backlog): bool
    {
        return $user->canInProject('priority.manage', $backlog->project);
    }

    public function setTimeline(User $user, Backlog $backlog): bool
    {
        return $user->canInProject('timeline.manage', $backlog->project);
    }
}
