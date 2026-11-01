<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InventoryBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryBalance
 */
class InventoryBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'code' => $this->branch->code, 'name' => $this->branch->name]),
            'variant' => $this->whenLoaded('variant', fn () => ['id' => $this->variant->id, 'sku' => $this->variant->sku, 'name' => $this->variant->variant_name]),
            'on_hand' => $this->on_hand,
            'reserved' => $this->reserved,
            'available' => $this->available(),
            'version' => $this->version,
            'source_updated_at' => $this->source_updated_at?->toIso8601ZuluString(),
        ];
    }
}
