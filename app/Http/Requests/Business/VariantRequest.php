<?php

namespace App\Http\Requests\Business;

use App\Enums\ItemCondition;
use App\Enums\ItemKind;
use App\Enums\PolicyStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\Sku;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Exact saleable variant. SKU is unique per organization (S2).
 */
abstract class VariantRequest extends FormRequest
{
    public function product(): Product
    {
        $variant = $this->route('variant');

        return $variant instanceof ProductVariant ? $variant->product : $this->route('product');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->product());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = $this->product()->organization_id;
        $variant = $this->route('variant');

        return [
            'sku' => ['required', 'string', new Sku,
                Rule::unique('product_variants', 'sku')->where('organization_id', $org)->ignore($variant instanceof ProductVariant ? $variant->id : null)],
            'variant_name' => ['required', 'string', 'max:160'],
            'item_kind' => ['required', Rule::enum(ItemKind::class)],
            'condition' => ['required', Rule::enum(ItemCondition::class)],
            'warranty_policy_id' => ['nullable', 'integer',
                Rule::exists('policy_versions', 'id')->where('organization_id', $org)->where('status', PolicyStatus::Approved->value)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sku' => Sku::normalize($this->input('sku'))]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
