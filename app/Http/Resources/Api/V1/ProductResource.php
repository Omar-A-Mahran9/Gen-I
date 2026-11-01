<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->displayName()]),
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand->id, 'name' => $this->brand->name]),
            'model_code' => $this->model_code,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'status' => $this->status->value,
            'source_reference' => $this->source_reference,
            'variants_count' => $this->whenCounted('variants'),
            'variants' => VariantResource::collection($this->whenLoaded('variants')),
            'media' => ProductMediaResource::collection($this->whenLoaded('media')),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
