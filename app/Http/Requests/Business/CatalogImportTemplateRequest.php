<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Category;
use App\Models\Product;
use App\Rules\CategoryCode;

/**
 * Blank import template. With ?category_code= the header also lists the "spec:<code>" columns
 * applicable to that category, which needs the category's organization.
 */
class CatalogImportTemplateRequest extends OrganizationScopedRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->filled('category_code')) {
            return [];
        }

        return $this->organizationRules() + [
            'category_code' => ['string', new CategoryCode, $this->existsInOrganization('categories', 'code')],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->filled('category_code') ? parent::after() : [];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('category_code')) {
            $this->merge(['category_code' => CategoryCode::normalize($this->input('category_code'))]);
        }
    }

    public function category(): ?Category
    {
        if (! $this->filled('category_code')) {
            return null;
        }

        return Category::query()->where('organization_id', $this->organizationId())->where('code', $this->validated('category_code'))->first();
    }
}
