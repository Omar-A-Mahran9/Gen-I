<?php

namespace App\Http\Requests\Business;

use App\Models\ProductVariant;
use App\Rules\TypedAttributeValue;
use App\Services\Business\ProductService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Typed specification values: each value must match its attribute's value type (S2).
 */
class VariantAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('variant')->product);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'values' => ['present', 'array'],
            'values.*.attribute_id' => ['required', 'integer', 'distinct'],
            'values.*.value' => ['nullable'],
            'values.*.source_reference' => ['nullable', 'required_with:values.*.value', 'string', 'max:2000'],
            'values.*.verified_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ProductVariant $variant */
                $variant = $this->route('variant');
                $definitions = app(ProductService::class)->applicableAttributes($variant->product)->keyBy('id');

                foreach ((array) $this->input('values', []) as $i => $row) {
                    $definition = $definitions->get((int) ($row['attribute_id'] ?? 0));
                    $value = $row['value'] ?? null;

                    if ($definition === null) {
                        $validator->errors()->add("values.{$i}.attribute_id", __('admin.errors.attribute_not_applicable'));

                        continue;
                    }

                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (! TypedAttributeValue::passes($definition->value_type, $value)) {
                        $validator->errors()->add("values.{$i}.value", __('admin.errors.attribute_value_type', ['attribute' => $definition->displayLabel(), 'type' => $definition->value_type->label()]));
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
