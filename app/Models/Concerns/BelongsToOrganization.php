<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping (S2: every business table carries org_id).
 *
 * - forUser(): super administrators see every organization; other users see
 *   only their own organization; users without an organization see nothing.
 * - Route model binding applies the same scope, so out-of-scope records
 *   resolve to 404 (no ID probing across organizations).
 *
 * @mixin Model
 */
trait BelongsToOrganization
{
    public function initializeBelongsToOrganization(): void
    {
        $this->mergeCasts(['organization_id' => 'integer']);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForUser(Builder $query, ?User $user): void
    {
        if ($user?->isSuperAdmin()) {
            return;
        }

        if ($user?->organization_id === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where($this->qualifyColumn('organization_id'), $user->organization_id);
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return static::query()
            ->forUser(auth()->user())
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
