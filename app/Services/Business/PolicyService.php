<?php

namespace App\Services\Business;

use App\Enums\PolicyStatus;
use App\Exceptions\AdminActionException;
use App\Models\PolicyVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Versioned commercial/service rules (S2 §9.4 policy_versions, BR12).
 * Drafts are editable; approved versions are immutable; authors cannot approve their own drafts.
 */
class PolicyService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PolicyVersion>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = PolicyVersion::query()->forUser($user)->with(['organization:id,name', 'author:id,name', 'approver:id,name']);

        $this->applySearch($query, $filters['search'] ?? null, ['policy_code']);
        $this->applyEquals($query, $filters, ['status', 'organization_id', 'policy_code']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $actor): PolicyVersion
    {
        return DB::transaction(function () use ($data, $actor): PolicyVersion {
            $next = (int) PolicyVersion::query()
                ->where('organization_id', $data['organization_id'])
                ->where('policy_code', $data['policy_code'])
                ->lockForUpdate()
                ->max('version') + 1;

            $policy = new PolicyVersion($data);
            $policy->version = $next;
            $policy->status = PolicyStatus::Draft;
            $policy->created_by = $actor->id;
            $policy->save();

            $this->audit->log('policy.created', $policy, ['policy' => $policy->displayName()], $actor);

            return $policy;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PolicyVersion $policy, array $data, User $actor): PolicyVersion
    {
        $this->assertDraft($policy);

        unset($data['policy_code']);
        $policy->fill($data);
        $changed = array_keys($policy->getDirty());
        $policy->save();

        $this->audit->log('policy.updated', $policy, ['policy' => $policy->displayName(), 'changed' => $changed], $actor);

        return $policy;
    }

    public function approve(PolicyVersion $policy, User $actor): PolicyVersion
    {
        $this->assertDraft($policy);

        if ($policy->created_by !== null && $policy->created_by === $actor->id) {
            throw new AdminActionException(__('admin.errors.self_approval'));
        }

        $policy->forceFill(['status' => PolicyStatus::Approved, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
        $this->audit->log('policy.approved', $policy, ['policy' => $policy->displayName()], $actor);

        return $policy;
    }

    public function retire(PolicyVersion $policy, User $actor): PolicyVersion
    {
        if ($policy->status !== PolicyStatus::Approved) {
            throw new AdminActionException(__('admin.errors.invalid_transition', ['from' => $policy->status->label(), 'to' => PolicyStatus::Retired->label()]));
        }

        $policy->forceFill(['status' => PolicyStatus::Retired, 'effective_to' => $policy->effective_to ?? now()])->save();
        $this->audit->log('policy.retired', $policy, ['policy' => $policy->displayName()], $actor);

        return $policy;
    }

    public function delete(PolicyVersion $policy, User $actor): void
    {
        $this->assertDraft($policy);

        $this->audit->log('policy.deleted', $policy, ['policy' => $policy->displayName()], $actor);
        $policy->delete();
    }

    protected function assertDraft(PolicyVersion $policy): void
    {
        if ($policy->status !== PolicyStatus::Draft) {
            throw new AdminActionException(__('admin.errors.policy_immutable'));
        }
    }
}
