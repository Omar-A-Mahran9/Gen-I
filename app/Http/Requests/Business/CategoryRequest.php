<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Category;
use Illuminate\Validation\Rule;

class CategoryRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'category';

    public function authorize(): bool
    {
        $category = $this->record();

        return $category ? $this->user()->can('update', $category) : $this->user()->can('create', Category::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'parent_id' => ['nullable', 'integer', $this->existsInOrganization('categories'), Rule::notIn(array_filter([$this->record()?->getKey()]))],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('categories', 'code')->where('organization_id', $this->organizationId() ?? 0)->ignore($this->record()?->getKey())],
            'name_en' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtolower(trim((string) $this->input('code')))]);
    }
}
