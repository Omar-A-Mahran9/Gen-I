<?php

namespace App\Services\Admin;

use App\Exceptions\AdminActionException;
use App\Models\CompanyMember;
use App\Models\User;
use App\Notifications\AccountUpdated;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * User administration shared by the admin dashboard (and future API controllers).
 */
class UserService
{
    public function __construct(
        protected PrivilegeGuard $guard,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, is_active?: bool, locale?: ?string, roles?: array<int, string>}  $data
     */
    public function create(array $data, User $actor): User
    {
        $roles = $data['roles'] ?? [];

        return DB::transaction(function () use ($data, $actor, $roles): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
                'locale' => $data['locale'] ?? null,
            ]);

            $user->forceFill([
                'organization_id' => $actor->isSuperAdmin() ? ($data['organization_id'] ?? null) : $actor->organization_id,
            ])->save();

            if ($roles !== []) {
                $this->guard->assertCanAssignRoles($actor, $user, $roles);
                $user->syncRoles($roles);
            }

            $this->audit->log('user.created', $user, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'is_active' => $user->is_active,
            ], $actor);

            $user->notify(new AccountUpdated('created', $actor));

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, locale?: ?string, roles?: array<int, string>}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $data['locale'] ?? $user->locale,
            ]);

            if ($actor->isSuperAdmin() && array_key_exists('organization_id', $data)) {
                $newOrganization = $data['organization_id'] === null ? null : (int) $data['organization_id'];

                if ($newOrganization !== $user->organization_id && CompanyMember::query()->where('user_id', $user->id)->exists()) {
                    throw new AdminActionException(__('admin.errors.user_has_company_memberships'));
                }

                $user->organization_id = $newOrganization;
            }

            $changes = array_keys($user->getDirty());
            $user->save();

            $rolesChanged = false;

            if (array_key_exists('roles', $data)) {
                $current = $user->getRoleNames()->sort()->values()->all();
                $requested = collect($data['roles'])->unique()->sort()->values()->all();

                if ($current !== $requested) {
                    $this->guard->assertCanAssignRoles($actor, $user, $requested);
                    $user->syncRoles($requested);
                    $rolesChanged = true;

                    $this->audit->log('user.roles_changed', $user, ['from' => $current, 'to' => $requested], $actor);
                    $user->notify(new AccountUpdated('roles_changed', $actor));
                }
            }

            if ($changes !== []) {
                $this->audit->log('user.updated', $user, ['changed' => $changes], $actor);
            }

            if ($changes !== [] && ! $rolesChanged && ! $actor->is($user)) {
                $user->notify(new AccountUpdated('updated', $actor));
            }

            return $user->refresh();
        });
    }

    public function setActive(User $user, bool $active, User $actor): User
    {
        if ($user->is_active === $active) {
            return $user;
        }

        if (! $active) {
            $this->guard->assertCanDeactivate($actor, $user);
        }

        DB::transaction(function () use ($user, $active, $actor): void {
            $user->forceFill(['is_active' => $active])->save();

            if (! $active) {
                $this->terminateSessions($user);
            }

            $this->audit->log($active ? 'user.activated' : 'user.deactivated', $user, [], $actor);
            $user->notify(new AccountUpdated($active ? 'activated' : 'deactivated', $actor));
        });

        return $user;
    }

    public function resetPassword(User $user, string $password, User $actor): void
    {
        DB::transaction(function () use ($user, $password, $actor): void {
            $user->forceFill(['password' => $password])->save();

            if (! $actor->is($user)) {
                $this->terminateSessions($user);
            }

            $this->audit->log('user.password_reset', $user, [], $actor);
            $user->notify(new AccountUpdated('password_reset', $actor));
        });
    }

    public function delete(User $user, User $actor): void
    {
        $this->guard->assertCanDelete($actor, $user);

        DB::transaction(function () use ($user, $actor): void {
            $this->audit->log('user.deleted', $user, ['name' => $user->name, 'email' => $user->email], $actor);

            $this->terminateSessions($user);
            $user->syncRoles([]);
            $user->notifications()->delete();
            $user->delete();
        });
    }

    /**
     * Sign the user out everywhere: web sessions, "remember me" cookies and API tokens.
     */
    public function terminateSessions(User $user): void
    {
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }
    }
}
