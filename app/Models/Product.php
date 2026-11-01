<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product family / model identity (S2 §9.3). Price and stock attach to exact variants (BR02).
 */
#[Fillable(['organization_id', 'category_id', 'brand_id', 'model_code', 'name_en', 'name_ar', 'description', 'source_reference'])]
class Product extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ProductStatus::class];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('display_order');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' && filled($this->name_ar) ? $this->name_ar : $this->name_en;
    }
}
