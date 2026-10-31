<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('user.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->can('user.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('user.manage') && ! $target->isSuperAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can('user.manage') && ! $target->isSuperAdmin() && ! $actor->is($target);
    }

    public function toggleActive(User $actor, User $target): bool
    {
        return $actor->can('user.manage') && ! $target->isSuperAdmin() && ! $actor->is($target);
    }
}
