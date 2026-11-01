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
 * Hierarchical catalog category (S2 §9.3). Cycles are prevented in CatalogService.
 */
#[Fillable(['organization_id', 'parent_id', 'code', 'name_en', 'name_ar'])]
class Category extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ActiveStatus::class];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<AttributeDefinition, $this> */
    public function attributeDefinitions(): HasMany
    {
        return $this->hasMany(AttributeDefinition::class)->orderBy('comparison_order')->orderBy('code');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'ar' && filled($this->name_ar) ? $this->name_ar : $this->name_en;
    }

    /**
     * IDs of this category and all of its ancestors (root last).
     *
     * @return array<int, int>
     */
    public function lineageIds(): array
    {
        $ids = [];
        $current = $this;
        $guard = 0;

        while ($current !== null && $guard++ < 50) {
            $ids[] = $current->id;
            $current = $current->parent_id ? Category::query()->find($current->parent_id) : null;
        }

        return $ids;
    }
}
