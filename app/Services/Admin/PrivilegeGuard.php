<?php

namespace App\Services\Admin;

use App\Exceptions\AdminActionException;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Central rules that prevent privilege escalation and administrator lock-out.
 */
class PrivilegeGuard
{
    public function superAdminRole(): string
    {
        return config('admin.super_admin_role');
    }

    public function isProtectedRole(Role $role): bool
    {
        return in_array($role->name, config('admin.protected_roles'), true);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNamesOf(User $user): Collection
    {
        return $user->getAllPermissions()->pluck('name');
    }

    /**
     * A user may grant a set of permissions only if they hold every one of them.
     *
     * @param  iterable<string>  $permissions
     */
    public function canGrantPermissions(User $actor, iterable $permissions): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return collect($permissions)->diff($this->permissionNamesOf($actor))->isEmpty();
    }

    /**
     * Roles the actor may assign to other users (never more power than they hold).
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        return $roles
            ->reject(fn (Role $role): bool => $role->name === $this->superAdminRole())
            ->filter(fn (Role $role): bool => $this->canGrantPermissions($actor, $role->permissions->pluck('name')))
            ->values();
    }

    /**
     * Only super administrators may manage other super administrators.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }

    public function activeSuperAdminCount(): int
    {
        return User::role($this->superAdminRole())->active()->count();
    }

    public function isLastActiveSuperAdmin(User $user): bool
    {
        return $user->is_active
            && $user->isSuperAdmin()
            && $this->activeSuperAdminCount() <= 1;
    }

    /**
     * @param  array<int, string>  $roleNames
     *
     * @throws AdminActionException
     */
    public function assertCanAssignRoles(User $actor, User $target, array $roleNames): void
    {
        if ($actor->is($target)) {
            throw new AdminActionException(__('admin.errors.cannot_change_own_roles'));
        }

        $allowed = $this->assignableRoles($actor)->pluck('name');
        $current = $target->exists ? $target->getRoleNames() : collect();

        // Both granting and removing a role require the authority to assign that role.
        $changed = collect($roleNames)->diff($current)->merge($current->diff($roleNames));

        if ($changed->diff($allowed)->isNotEmpty()) {
            throw new AdminActionException(__('admin.errors.role_not_assignable'));
        }

        if ($this->isLastActiveSuperAdmin($target) && ! in_array($this->superAdminRole(), $roleNames, true)) {
            throw new AdminActionException(__('admin.errors.last_super_admin'));
        }
    }

    /**
     * @throws AdminActionException
     */
    public function assertCanDeactivate(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new AdminActionException(__('admin.errors.cannot_deactivate_self'));
        }

        if ($this->isLastActiveSuperAdmin($target)) {
            throw new AdminActionException(__('admin.errors.last_super_admin'));
        }
    }

    /**
     * @throws AdminActionException
     */
    public function assertCanDelete(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new AdminActionException(__('admin.errors.cannot_delete_self'));
        }

        if ($this->isLastActiveSuperAdmin($target)) {
            throw new AdminActionException(__('admin.errors.last_super_admin'));
        }
    }
}
