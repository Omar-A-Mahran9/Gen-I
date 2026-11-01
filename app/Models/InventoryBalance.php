<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Current branch balance (S2 §9.4). Only InventoryService writes balances,
 * always together with an immutable movement. Available = on_hand - reserved.
 */
class InventoryBalance extends Model
{
    use BelongsToOrganization;

    /** @var array<int, string> */
    protected $guarded = ['*'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'version' => 'integer',
            'source_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }
}
