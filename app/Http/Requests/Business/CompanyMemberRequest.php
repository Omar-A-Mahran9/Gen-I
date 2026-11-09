<?php

namespace App\Http\Requests\Business;

use App\Enums\ActiveStatus;
use App\Enums\MemberRole;
use App\Models\Company;
use App\Models\CompanyMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Company membership. Members must be users of the company's organization.
 * approval_limit is recorded only; spending authority is conditional scope.
 */
abstract class CompanyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('company'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');
        $isUpdate = $this->route('member') instanceof CompanyMember;

        return [
            'user_id' => $isUpdate ? ['prohibited'] : [
                'required', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $company->organization_id),
                Rule::unique('company_members', 'user_id')->where('company_id', $company->id),
            ],
            'member_role' => ['required', Rule::enum(MemberRole::class)],
            'approval_limit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'status' => [$isUpdate ? 'required' : 'nullable', Rule::enum(ActiveStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper(trim((string) ($this->input('currency') ?: $this->route('company')?->organization?->currency)))]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
