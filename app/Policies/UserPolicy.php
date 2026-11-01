<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Admin\PrivilegeGuard;

class UserPolicy
{
    public function __construct(protected PrivilegeGuard $guard) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can('users.update') && $this->guard->canManageUser($actor, $user);
    }

    public function assignRoles(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }

    public function toggleStatus(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $actor->is($user);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can('users.delete')
            && $this->guard->canManageUser($actor, $user)
            && ! $actor->is($user);
    }
}
