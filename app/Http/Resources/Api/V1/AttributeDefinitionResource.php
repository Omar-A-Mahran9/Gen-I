<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttributeDefinition
 */
class AttributeDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'code' => $this->code,
            'label' => $this->label,
            'label_ar' => $this->label_ar,
            'value_type' => $this->value_type->value,
            'unit' => $this->unit,
            'comparison_order' => $this->comparison_order,
        ];
    }
}
