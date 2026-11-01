<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'timezone', 'currency'])]
class Organization extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class];
    }

    /**
     * Super administrators see every organization; other users only their own.
     *
     * @param  Builder<Organization>  $query
     */
    public function scopeForUser(Builder $query, ?User $user): void
    {
        if ($user?->isSuperAdmin()) {
            return;
        }

        $query->whereKey($user?->organization_id ?? 0);
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return static::query()->forUser(auth()->user())->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Company, $this> */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /** @return HasMany<Branch, $this> */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Tables whose rows prevent the organization from being deleted.
     *
     * @return array<int, string>
     */
    public static function dependentTables(): array
    {
        return ['users', 'companies', 'branches', 'categories', 'brands', 'products', 'policy_versions', 'price_lists', 'media_assets', 'inventory_movements'];
    }
}
