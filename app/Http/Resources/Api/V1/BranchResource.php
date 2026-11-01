<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'code' => $this->code,
            'name' => $this->name,
            'address_text' => $this->address_text,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'location' => $this->latitude === null ? null : ['latitude' => (float) $this->latitude, 'longitude' => (float) $this->longitude, 'directions_url' => $this->directionsUrl()],
            'opening_hours' => $this->opening_hours,
            'status' => $this->status->value,
            'inventory_items_count' => $this->whenCounted('inventoryBalances'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
