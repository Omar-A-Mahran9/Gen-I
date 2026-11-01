<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\PolicyVersion;

/**
 * Policy drafts. rule_body must be a JSON object; its business schema per policy
 * type is pending GEN-I approval (BD-04/BD-06), so only structure is validated here.
 */
class PolicyVersionRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'policy';

    public function authorize(): bool
    {
        $policy = $this->record();

        return $policy ? $this->user()->can('update', $policy) : $this->user()->can('create', PolicyVersion::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'policy_code' => $this->record() ? ['prohibited'] : ['required', 'string', 'max:60', 'regex:/^[a-z0-9._\-]+$/'],
            'rule_body' => ['required', 'array'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = $this->input('rule_body');

        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $this->merge(['rule_body' => is_array($decoded) && $decoded !== [] && ! array_is_list($decoded) ? $decoded : 'invalid']);
        } elseif (is_array($body) && $body !== [] && array_is_list($body)) {
            $this->merge(['rule_body' => 'invalid']);
        }

        if ($this->filled('policy_code')) {
            $this->merge(['policy_code' => strtolower(trim((string) $this->input('policy_code')))]);
        }
    }
}
