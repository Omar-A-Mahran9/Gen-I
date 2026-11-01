<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission + organization-scope checks shared by business module policies.
 * Every record action requires the "<group>.<action>" permission AND access
 * to the record's organization (super administrators: all organizations).
 */
abstract class OrganizationScopedPolicy
{
    /**
     * Permission group, e.g. "branches" for "branches.view".
     */
    abstract protected function group(): string;

    protected function readPermission(): string
    {
        return $this->group().'.view';
    }

    protected function writePermission(string $action): string
    {
        return $this->group().'.'.$action;
    }

    protected function inScope(User $user, Model $model): bool
    {
        return $user->canAccessOrganization((int) $model->getAttribute('organization_id'));
    }

    public function viewAny(User $user): bool
    {
        return $user->can($this->readPermission());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->readPermission()) && $this->inScope($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can($this->writePermission('create')) && ($user->isSuperAdmin() || $user->organization_id !== null);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->writePermission('update')) && $this->inScope($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->writePermission('delete')) && $this->inScope($user, $model);
    }
}
