<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store and service branch (S2 §9.1 "branches: Stores and service branches").
 * GEN-I stores are branches; there is no separate stores table.
 */
#[Fillable(['organization_id', 'code', 'name', 'address_text', 'city', 'phone', 'email', 'latitude', 'longitude', 'opening_hours'])]
class Branch extends Model
{
    use BelongsToOrganization, HasFactory;

    public const DAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
            'opening_hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return HasMany<InventoryBalance, $this> */
    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function directionsUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
    }
}
