<?php

namespace App\Http\Requests\Business;

use App\Enums\AttributeValueType;
use App\Models\AttributeDefinition;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class AttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');
        $attribute = $this->route('attribute');

        return [
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('attribute_defs', 'code')->where('category_id', $category->id)->ignore($attribute instanceof AttributeDefinition ? $attribute->id : null)],
            'label' => ['required', 'string', 'max:120'],
            'label_ar' => ['nullable', 'string', 'max:120'],
            'value_type' => ['required', Rule::enum(AttributeValueType::class)],
            'unit' => ['nullable', 'string', 'max:30'],
            'comparison_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower(trim((string) $this->input('code'))),
            'comparison_order' => $this->input('comparison_order') ?? 0,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
