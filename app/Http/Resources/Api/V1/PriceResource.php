<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Price;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Price
 */
class PriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price_list_id' => $this->price_list_id,
            'variant' => $this->whenLoaded('variant', fn () => ['id' => $this->variant->id, 'sku' => $this->variant->sku, 'name' => $this->variant->variant_name]),
            'min_quantity' => $this->min_quantity,
            'unit_price' => ['amount' => (string) $this->unit_price, 'currency' => $this->priceList?->currency],
            'tax_policy_id' => $this->tax_policy_id,
            'valid_from' => $this->valid_from?->toIso8601ZuluString(),
            'valid_to' => $this->valid_to?->toIso8601ZuluString(),
            'state' => $this->isFuture() ? 'future' : ($this->isCurrent() ? 'current' : 'past'),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
