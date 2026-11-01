<?php

namespace App\Http\Requests\Business;

use App\Enums\MovementReason;
use App\Models\Branch;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual stock change (UC19: stock adjustments require a reason and permission).
 */
class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.adjust');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', Rule::in(array_map(fn (MovementReason $r) => $r->value, MovementReason::manual()))],
            'note' => ['nullable', 'required_if:reason,adjustment', 'string', 'max:500'],
        ];
    }

    /**
     * Branch within the user's organization scope (404 otherwise).
     */
    public function branch(): Branch
    {
        return Branch::query()->forUser($this->user())->findOrFail($this->integer('branch_id'));
    }

    /**
     * Variant within the branch's organization (404 otherwise).
     */
    public function variant(): ProductVariant
    {
        return ProductVariant::query()->where('organization_id', $this->branch()->organization_id)->findOrFail($this->integer('variant_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
