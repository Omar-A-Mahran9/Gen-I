<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retail pricing context (S2 §9.4). Contract (company) lists are conditional scope.
 */
#[Fillable(['organization_id', 'code', 'currency'])]
class PriceList extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ActiveStatus::class];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Price, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
