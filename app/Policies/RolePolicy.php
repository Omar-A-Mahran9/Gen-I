<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Admin\PrivilegeGuard;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function __construct(protected PrivilegeGuard $guard) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.update') && ! $this->guard->isProtectedRole($role);
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.delete') && ! $this->guard->isProtectedRole($role);
    }
}
