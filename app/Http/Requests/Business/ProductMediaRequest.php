<?php

namespace App\Http\Requests\Business;

use App\Enums\MediaClassification;
use App\Enums\ScanStatus;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Product image upload/update. Raster images only (no SVG); the variant must belong to the product.
 * Changing scan status or classification is a review action requiring catalog.approve.
 */
class ProductMediaRequest extends FormRequest
{
    public function product(): Product
    {
        $placement = $this->route('media');

        return $placement instanceof ProductMedia ? $placement->product : $this->route('product');
    }

    public function authorize(): bool
    {
        $allowed = $this->user()->can('update', $this->product());

        if ($this->hasAny(['scan_status', 'classification'])) {
            $allowed = $allowed && $this->user()->can('approve', $this->product());
        }

        return $allowed;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('media') instanceof ProductMedia;
        $max = config('admin.uploads.product_image_max_kb');

        return [
            'image' => $isUpdate ? ['prohibited'] : ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', "max:{$max}", 'dimensions:min_width=64,min_height=64,max_width=8000,max_height=8000'],
            'alt_text' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:300'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $this->product()->id)],
            'rights_reference' => ['nullable', 'string', 'max:2000'],
            'scan_status' => ['sometimes', Rule::enum(ScanStatus::class)],
            'classification' => ['sometimes', Rule::enum(MediaClassification::class)],
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
