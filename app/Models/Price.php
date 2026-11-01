<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Effective-dated variant price (S2 §9.4). History is never overwritten:
 * a new price closes the open-ended previous one.
 */
#[Fillable(['organization_id', 'price_list_id', 'variant_id', 'min_quantity', 'unit_price', 'tax_policy_id', 'valid_from', 'valid_to', 'created_by'])]
class Price extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'min_quantity' => 'integer',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<PolicyVersion, $this> */
    public function taxPolicy(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'tax_policy_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<Price>  $query
     */
    public function scopeEffectiveAt(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();
        $query->where('valid_from', '<=', $at)->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', $at));
    }

    public function isFuture(): bool
    {
        return $this->valid_from->isFuture();
    }

    public function isCurrent(): bool
    {
        return ! $this->isFuture() && ($this->valid_to === null || $this->valid_to->isFuture());
    }
}
