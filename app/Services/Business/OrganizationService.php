<?php

namespace App\Services\Business;

use App\Enums\OrganizationStatus;
use App\Exceptions\AdminActionException;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrganizationService
{
    use ScopedQuery;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Organization>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = Organization::query()->forUser($user)
            ->withCount(['companies', 'branches', 'users']);

        $this->applySearch($query, $filters['search'] ?? null, ['name']);
        $this->applyEquals($query, $filters, ['status', 'currency']);

        return $query;
    }

    /**
     * @param  array{name: string, timezone: string, currency: string}  $data
     */
    public function create(array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($data, $actor): Organization {
            $organization = new Organization($data);
            $organization->status = OrganizationStatus::Active;
            $organization->save();

            $this->audit->log('organization.created', $organization, $data, $actor);

            return $organization;
        });
    }

    /**
     * @param  array{name: string, timezone: string, currency: string}  $data
     */
    public function update(Organization $organization, array $data, User $actor): Organization
    {
        return DB::transaction(function () use ($organization, $data, $actor): Organization {
            $organization->fill($data);
            $changes = $organization->getDirty();
            $organization->save();

            if ($changes !== []) {
                $this->audit->log('organization.updated', $organization, ['changed' => array_keys($changes)], $actor);
            }

            return $organization;
        });
    }

    public function setStatus(Organization $organization, OrganizationStatus $status, User $actor): Organization
    {
        if ($organization->status === $status) {
            return $organization;
        }

        if ($status === OrganizationStatus::Suspended && $actor->organization_id === $organization->id) {
            throw new AdminActionException(__('admin.errors.cannot_suspend_own_organization'));
        }

        $from = $organization->status;
        $organization->forceFill(['status' => $status])->save();
        $this->audit->log('organization.status_changed', $organization, ['from' => $from->value, 'to' => $status->value], $actor);

        return $organization;
    }

    public function delete(Organization $organization, User $actor): void
    {
        foreach (Organization::dependentTables() as $table) {
            if (DB::table($table)->where('organization_id', $organization->id)->exists()) {
                throw new AdminActionException(__('admin.errors.has_dependents'));
            }
        }

        DB::transaction(function () use ($organization, $actor): void {
            $this->audit->log('organization.deleted', $organization, ['name' => $organization->name], $actor);
            $organization->delete();
        });
    }
}
