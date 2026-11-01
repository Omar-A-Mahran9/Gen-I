<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class VariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'variant_name' => $this->variant_name,
            'item_kind' => $this->item_kind->value,
            'condition' => $this->condition->value,
            'warranty_policy_id' => $this->warranty_policy_id,
            'status' => $this->status->value,
            'specifications' => VariantAttributeResource::collection($this->whenLoaded('attributeValues')),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
