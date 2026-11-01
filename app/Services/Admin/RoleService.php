<?php

namespace App\Services\Admin;

use App\Exceptions\AdminActionException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role and permission administration with privilege-escalation checks.
 */
class RoleService
{
    public function __construct(
        protected PrivilegeGuard $guard,
        protected AuditLogger $audit,
    ) {}

    /**
     * Permissions grouped by their prefix ("users.view" => group "users").
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    public function groupedPermissions(): Collection
    {
        $order = array_keys(config('admin.permissions'));

        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => strtok($permission->name, '.'))
            ->sortBy(fn (Collection $group, string $key): int => ($index = array_search($key, $order, true)) === false ? PHP_INT_MAX : $index);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function create(string $name, array $permissions, User $actor): Role
    {
        $this->assertCanGrant($actor, $permissions);

        return DB::transaction(function () use ($name, $permissions, $actor): Role {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            $this->audit->log('role.created', $role, ['name' => $name, 'permissions' => array_values($permissions)], $actor);

            return $role;
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function update(Role $role, string $name, array $permissions, User $actor): Role
    {
        if ($this->guard->isProtectedRole($role)) {
            throw new AdminActionException(__('admin.errors.protected_role'));
        }

        $current = $role->permissions->pluck('name');
        $added = collect($permissions)->diff($current)->values();
        $removed = $current->diff($permissions)->values();

        // Only newly granted permissions need to be held by the actor.
        $this->assertCanGrant($actor, $added->all());

        if ($actor->hasRole($role) && ! $actor->isSuperAdmin() && $removed->isNotEmpty()) {
            throw new AdminActionException(__('admin.errors.cannot_reduce_own_role'));
        }

        return DB::transaction(function () use ($role, $name, $permissions, $actor, $added, $removed): Role {
            $originalName = $role->name;
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);

            $this->audit->log('role.updated', $role, array_filter([
                'name' => $originalName !== $name ? ['from' => $originalName, 'to' => $name] : null,
                'permissions_added' => $added->all() ?: null,
                'permissions_removed' => $removed->all() ?: null,
            ]), $actor);

            return $role->refresh();
        });
    }

    public function delete(Role $role, User $actor): void
    {
        if ($this->guard->isProtectedRole($role)) {
            throw new AdminActionException(__('admin.errors.protected_role'));
        }

        if ($role->users()->exists()) {
            throw new AdminActionException(__('admin.errors.role_in_use'));
        }

        DB::transaction(function () use ($role, $actor): void {
            $this->audit->log('role.deleted', $role, ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->all()], $actor);
            $role->delete();
        });
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function assertCanGrant(User $actor, array $permissions): void
    {
        if (! $this->guard->canGrantPermissions($actor, $permissions)) {
            throw new AdminActionException(__('admin.errors.permission_not_grantable'));
        }
    }
}
