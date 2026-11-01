<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'status' => $this->status->value,
            'products_count' => $this->whenCounted('products'),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
