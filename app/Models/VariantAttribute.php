<?php

namespace App\Models;

use App\Enums\AttributeValueType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Typed exact-variant specification value (S2 §9.3). Exactly one typed value is set.
 */
#[Fillable(['organization_id', 'variant_id', 'attribute_id', 'text_value', 'number_value', 'boolean_value', 'source_reference', 'verified_at'])]
class VariantAttribute extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['boolean_value' => 'boolean', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** @return BelongsTo<AttributeDefinition, $this> */
    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'attribute_id');
    }

    public function typedValue(): string|float|bool|null
    {
        return match ($this->attributeDefinition?->value_type) {
            AttributeValueType::Number => $this->number_value === null ? null : (float) $this->number_value,
            AttributeValueType::Boolean => $this->boolean_value,
            default => $this->text_value,
        };
    }
}
