<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'name' => $this->displayName(),
            'status' => $this->status->value,
            'children_count' => $this->whenCounted('children'),
            'products_count' => $this->whenCounted('products'),
            'attributes' => AttributeDefinitionResource::collection($this->whenLoaded('attributeDefinitions')),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
