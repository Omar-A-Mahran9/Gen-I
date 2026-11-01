<?php

namespace App\Services\Business;

use App\Enums\ActiveStatus;
use App\Enums\CompanyStatus;
use App\Exceptions\AdminActionException;
use App\Models\Address;
use App\Models\Company;
use App\Models\CompanyMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    use ScopedQuery;

    /**
     * Permitted status transitions (S2 companies.status: pending, verified, suspended).
     */
    public const TRANSITIONS = [
        'pending' => ['verified', 'suspended'],
        'verified' => ['suspended'],
        'suspended' => ['verified'],
    ];

    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Company>
     */
    public function query(User $user, array $filters = []): Builder
    {
        $query = Company::query()->forUser($user)
            ->with(['organization:id,name', 'accountOwner:id,name'])
            ->withCount(['members', 'addresses']);

        $this->applySearch($query, $filters['search'] ?? null, ['legal_name', 'tax_reference']);
        $this->applyEquals($query, $filters, ['status', 'organization_id', 'account_owner_id']);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Company
    {
        return DB::transaction(function () use ($data, $actor): Company {
            $company = new Company($data);
            $company->status = CompanyStatus::Pending;
            $company->save();

            $this->audit->log('company.created', $company, ['legal_name' => $company->legal_name], $actor);

            return $company;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data, User $actor): Company
    {
        return DB::transaction(function () use ($company, $data, $actor): Company {
            $company->fill($data);
            $changed = array_keys($company->getDirty());
            $company->save();

            if ($changed !== []) {
                $this->audit->log('company.updated', $company, ['changed' => $changed], $actor);
            }

            return $company;
        });
    }

    public function setStatus(Company $company, CompanyStatus $status, User $actor): Company
    {
        $from = $company->status;

        if (! in_array($status->value, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new AdminActionException(__('admin.errors.invalid_transition', ['from' => $from->label(), 'to' => $status->label()]));
        }

        $company->forceFill(['status' => $status])->save();
        $this->audit->log('company.status_changed', $company, ['from' => $from->value, 'to' => $status->value], $actor);

        return $company;
    }

    public function delete(Company $company, User $actor): void
    {
        if ($company->members()->exists() || $company->priceLists()->exists()) {
            throw new AdminActionException(__('admin.errors.has_dependents'));
        }

        DB::transaction(function () use ($company, $actor): void {
            $this->audit->log('company.deleted', $company, ['legal_name' => $company->legal_name], $actor);
            $company->addresses()->delete();
            $company->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addMember(Company $company, array $data, User $actor): CompanyMember
    {
        $member = new CompanyMember($data + ['status' => ActiveStatus::Active->value]);
        $member->organization_id = $company->organization_id;
        $member->company_id = $company->id;
        $member->save();

        $this->audit->log('company.member_added', $company, ['user_id' => $member->user_id, 'member_role' => $member->member_role->value], $actor);

        return $member;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMember(CompanyMember $member, array $data, User $actor): CompanyMember
    {
        $member->fill(collect($data)->only(['member_role', 'approval_limit', 'currency', 'status'])->all());
        $changed = array_keys($member->getDirty());
        $member->save();

        if ($changed !== []) {
            $this->audit->log('company.member_updated', $member->company, ['user_id' => $member->user_id, 'changed' => $changed], $actor);
        }

        return $member;
    }

    public function removeMember(CompanyMember $member, User $actor): void
    {
        $this->audit->log('company.member_removed', $member->company, ['user_id' => $member->user_id], $actor);
        $member->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveAddress(Company $company, array $data, User $actor, ?Address $address = null): Address
    {
        $address ??= new Address;
        $isNew = ! $address->exists;
        $address->fill($data);
        $address->organization_id = $company->organization_id;
        $address->company_id = $company->id;
        $address->save();

        $this->audit->log($isNew ? 'company.address_added' : 'company.address_updated', $company, ['address_id' => $address->id, 'label' => $address->label], $actor);

        return $address;
    }

    public function deleteAddress(Address $address, User $actor): void
    {
        $this->audit->log('company.address_removed', $address->company, ['address_id' => $address->id, 'label' => $address->label], $actor);
        $address->delete();
    }
}
