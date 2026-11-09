<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Brand;
use Illuminate\Validation\Validator;

abstract class BrandRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'brand';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * Unique normalized name per organization (S2: "Unique normalized name").
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return array_merge(parent::after(), [
            function (Validator $validator): void {
                if ($validator->errors()->has('name') || $this->organizationId() === null) {
                    return;
                }

                $duplicate = Brand::query()
                    ->where('organization_id', $this->organizationId())
                    ->where('name_normalized', Brand::normalize((string) $this->input('name')))
                    ->when($this->record(), fn ($q, $brand) => $q->whereKeyNot($brand->getKey()))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('name', __('validation.unique', ['attribute' => __('admin.fields.name')]));
                }
            },
        ]);
    }
}
