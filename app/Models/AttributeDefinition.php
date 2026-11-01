<?php

namespace App\Models;

use App\Enums\AttributeValueType;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Typed specification dictionary entry for a category (S2 §9.3 "attribute_defs").
 */
#[Fillable(['organization_id', 'category_id', 'code', 'label', 'label_ar', 'value_type', 'unit', 'comparison_order'])]
class AttributeDefinition extends Model
{
    use BelongsToOrganization;

    protected $table = 'attribute_defs';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value_type' => AttributeValueType::class, 'comparison_order' => 'integer'];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<VariantAttribute, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(VariantAttribute::class, 'attribute_id');
    }

    public function displayLabel(): string
    {
        return app()->getLocale() === 'ar' && filled($this->label_ar) ? $this->label_ar : $this->label;
    }
}
