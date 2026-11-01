<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Base request for organization-scoped records (shared by admin and API controllers).
 *
 * - On create, super administrators choose the organization; everyone else is
 *   bound to their own organization (any submitted value is ignored).
 * - On update, the organization is the existing record's and cannot change.
 */
abstract class OrganizationScopedRequest extends FormRequest
{
    /**
     * The route parameter holding the record being updated (null for create).
     */
    protected ?string $routeModel = null;

    public function record(): ?Model
    {
        if ($this->routeModel === null) {
            return null;
        }

        $model = $this->route($this->routeModel);

        return $model instanceof Model ? $model : null;
    }

    public function organizationId(): ?int
    {
        $record = $this->record();

        if ($record !== null) {
            return (int) $record->getAttribute('organization_id');
        }

        if ($this->user()->isSuperAdmin()) {
            return $this->filled('organization_id') ? (int) $this->input('organization_id') : null;
        }

        return $this->user()->organization_id;
    }

    /**
     * Rules for the organization field (only meaningful on create).
     *
     * @return array<string, mixed>
     */
    protected function organizationRules(): array
    {
        if ($this->record() !== null) {
            return [];
        }

        return [
            'organization_id' => [
                Rule::requiredIf(fn (): bool => $this->user()->isSuperAdmin()),
                'nullable', 'integer', Rule::exists(Organization::class, 'id'),
            ],
        ];
    }

    /**
     * Organization-aware exists rule: the referenced row must belong to the same organization.
     */
    protected function existsInOrganization(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('organization_id', $this->organizationId() ?? 0);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->organizationId() === null && ! $validator->errors()->has('organization_id')) {
                    $validator->errors()->add('organization_id', __('admin.errors.no_organization'));
                }
            },
        ];
    }

    /**
     * Validated data with the resolved organization applied.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();
        unset($data['organization_id']);

        if ($this->record() === null) {
            $data['organization_id'] = $this->organizationId();
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
