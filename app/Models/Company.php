<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Corporate account / procurement entity (S2 §9.2) inside an organization.
 * Companies are customers, not parents of branches.
 */
#[Fillable(['organization_id', 'legal_name', 'tax_reference', 'account_owner_id'])]
class Company extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => CompanyStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    /** @return HasMany<CompanyMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CompanyMember::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasMany<PriceList, $this> */
    public function priceLists(): HasMany
    {
        return $this->hasMany(PriceList::class);
    }
}
