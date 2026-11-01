<?php

namespace App\Models;

use App\Enums\ItemCondition;
use App\Enums\ItemKind;
use App\Enums\VariantStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saleable SKU (S2 §9.3): the exact variant used for pricing and stock.
 */
#[Fillable(['organization_id', 'product_id', 'sku', 'variant_name', 'item_kind', 'condition', 'warranty_policy_id'])]
class ProductVariant extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => VariantStatus::class,
            'item_kind' => ItemKind::class,
            'condition' => ItemCondition::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<PolicyVersion, $this> */
    public function warrantyPolicy(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'warranty_policy_id');
    }

    /** @return HasMany<VariantAttribute, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttribute::class, 'variant_id');
    }

    /** @return HasMany<Price, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'variant_id');
    }

    /** @return HasMany<InventoryBalance, $this> */
    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'variant_id');
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }
}
