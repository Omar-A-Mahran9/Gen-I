<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Rules\CategoryCode;
use Illuminate\Validation\Rule;

abstract class CategoryRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'category';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'parent_id' => ['nullable', 'integer', $this->existsInOrganization('categories'), Rule::notIn(array_filter([$this->record()?->getKey()]))],
            'code' => ['required', 'string', new CategoryCode,
                Rule::unique('categories', 'code')->where('organization_id', $this->organizationId() ?? 0)->ignore($this->record()?->getKey())],
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => CategoryCode::normalize($this->input('code'))]);
    }
}
