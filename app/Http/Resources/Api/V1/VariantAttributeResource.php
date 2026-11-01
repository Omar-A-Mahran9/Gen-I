<?php

namespace App\Http\Resources\Api\V1;

use App\Models\VariantAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VariantAttribute
 */
class VariantAttributeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'attribute_id' => $this->attribute_id,
            'code' => $this->attributeDefinition?->code,
            'label' => $this->attributeDefinition?->displayLabel(),
            'value_type' => $this->attributeDefinition?->value_type->value,
            'unit' => $this->attributeDefinition?->unit,
            'value' => $this->typedValue(),
            'source_reference' => $this->source_reference,
            'verified_at' => $this->verified_at?->toIso8601ZuluString(),
        ];
    }
}
