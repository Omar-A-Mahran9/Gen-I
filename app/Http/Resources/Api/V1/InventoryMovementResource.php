<?php

namespace App\Http\Resources\Api\V1;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryMovement
 */
class InventoryMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch' => $this->whenLoaded('branch', fn () => ['id' => $this->branch->id, 'code' => $this->branch->code]),
            'variant' => $this->whenLoaded('variant', fn () => ['id' => $this->variant->id, 'sku' => $this->variant->sku]),
            'quantity_delta' => $this->quantity_delta,
            'on_hand_after' => $this->on_hand_after,
            'reason' => $this->reason->value,
            'source_event_id' => $this->source_event_id,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
