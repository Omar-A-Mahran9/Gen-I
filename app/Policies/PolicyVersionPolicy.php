<?php

namespace App\Policies;

use App\Enums\PolicyStatus;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Commercial/service policy versions: "policies.manage" drafts, "policies.approve" approves (BR12).
 */
class PolicyVersionPolicy extends OrganizationScopedPolicy
{
    protected function group(): string
    {
        return 'policies';
    }

    protected function writePermission(string $action): string
    {
        return 'policies.manage';
    }

    public function update(User $user, Model $model): bool
    {
        return parent::update($user, $model) && $model->status === PolicyStatus::Draft;
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model->status === PolicyStatus::Draft;
    }

    public function approve(User $user, PolicyVersion $policy): bool
    {
        return $user->can('policies.approve') && $this->inScope($user, $policy) && $policy->status === PolicyStatus::Draft;
    }

    public function retire(User $user, PolicyVersion $policy): bool
    {
        return $user->can('policies.approve') && $this->inScope($user, $policy) && $policy->status === PolicyStatus::Approved;
    }
}
