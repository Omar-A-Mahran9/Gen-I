<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Company;
use Illuminate\Validation\Rule;

class CompanyRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'company';

    public function authorize(): bool
    {
        $company = $this->record();

        return $company ? $this->user()->can('update', $company) : $this->user()->can('create', Company::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = $this->organizationId() ?? 0;
        $ignore = $this->record()?->getKey();

        return $this->organizationRules() + [
            'legal_name' => ['required', 'string', 'max:200', Rule::unique('companies', 'legal_name')->where('organization_id', $org)->ignore($ignore)],
            'tax_reference' => ['nullable', 'string', 'max:80', Rule::unique('companies', 'tax_reference')->where('organization_id', $org)->ignore($ignore)],
            'account_owner_id' => ['nullable', 'integer', $this->existsInOrganization('users')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name')),
            'tax_reference' => filled($this->input('tax_reference')) ? trim((string) $this->input('tax_reference')) : null,
        ]);
    }
}
