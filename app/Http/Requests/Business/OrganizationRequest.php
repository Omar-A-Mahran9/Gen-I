<?php

namespace App\Http\Requests\Business;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class OrganizationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique(Organization::class, 'name')->ignore($organization instanceof Organization ? $organization->id : null)],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'currency' => strtoupper(trim((string) $this->input('currency'))),
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
