<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved manufacturer identity (S2 §9.3). Uniqueness is on the normalized name.
 */
#[Fillable(['organization_id', 'name'])]
class Brand extends Model
{
    use BelongsToOrganization, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => ActiveStatus::class];
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            $brand->name = trim(preg_replace('/\s+/u', ' ', $brand->name));
            $brand->name_normalized = self::normalize($brand->name);
        });
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
