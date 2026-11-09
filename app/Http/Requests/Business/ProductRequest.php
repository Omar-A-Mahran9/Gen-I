<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use Illuminate\Validation\Rule;

abstract class ProductRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'product';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'category_id' => ['required', 'integer', $this->existsInOrganization('categories')],
            'brand_id' => ['required', 'integer', $this->existsInOrganization('brands')],
            'model_code' => ['required', 'string', 'max:100',
                Rule::unique('products', 'model_code')
                    ->where('organization_id', $this->organizationId() ?? 0)
                    ->where('brand_id', (int) $this->input('brand_id'))
                    ->ignore($this->record()?->getKey())],
            'name_en' => ['required', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source_reference' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['model_code' => trim((string) $this->input('model_code'))]);
    }
}
