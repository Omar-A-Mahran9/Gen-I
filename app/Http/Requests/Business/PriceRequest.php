<?php

namespace App\Http\Requests\Business;

use App\Enums\PolicyStatus;
use App\Models\Price;
use App\Models\PriceList;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Effective-dated price. New prices cannot start in the past (history is never rewritten).
 */
abstract class PriceRequest extends FormRequest
{
    public function priceList(): PriceList
    {
        $price = $this->route('price');

        return $price instanceof Price ? $price->priceList : $this->route('price_list');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->priceList());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $list = $this->priceList();
        $isUpdate = $this->route('price') instanceof Price;

        return [
            'variant_id' => $isUpdate ? ['prohibited'] : ['required', 'integer', Rule::exists('product_variants', 'id')->where('organization_id', $list->organization_id)],
            'min_quantity' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1', 'max:1000000'],
            'unit_price' => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
            'tax_policy_id' => [$isUpdate ? 'sometimes' : 'required', 'integer',
                Rule::exists('policy_versions', 'id')->where('organization_id', $list->organization_id)->where('status', PolicyStatus::Approved->value)],
            'valid_from' => ['nullable', 'date', 'after_or_equal:'.now()->subMinute()->toDateTimeString()],
            'valid_to' => ['nullable', 'date', 'after:valid_from', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('min_quantity') && ! ($this->route('price') instanceof Price)) {
            $this->merge(['min_quantity' => 1]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
