<?php

namespace App\Http\Requests\Business;

use App\Http\Requests\OrganizationScopedRequest;
use App\Models\Branch;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BranchRequest extends OrganizationScopedRequest
{
    protected ?string $routeModel = 'branch';

    public function authorize(): bool
    {
        $branch = $this->record();

        return $branch ? $this->user()->can('update', $branch) : $this->user()->can('create', Branch::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->organizationRules() + [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('branches', 'code')->where('organization_id', $this->organizationId() ?? 0)->ignore($this->record()?->getKey())],
            'name' => ['required', 'string', 'max:120'],
            'address_text' => ['required', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'string', 'email', 'max:254'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'opening_hours' => ['required', 'array'],
            'opening_hours.*' => ['nullable', 'array', 'max:3'],
            'opening_hours.*.*.open' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.*.close' => ['nullable', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return array_merge(parent::after(), [
            function (Validator $validator): void {
                foreach ((array) $this->input('opening_hours', []) as $day => $intervals) {
                    if (! in_array($day, Branch::DAYS, true)) {
                        $validator->errors()->add('opening_hours', __('admin.errors.invalid_day', ['day' => $day]));

                        continue;
                    }

                    foreach ((array) $intervals as $i => $interval) {
                        $open = $interval['open'] ?? null;
                        $close = $interval['close'] ?? null;

                        if (filled($open) xor filled($close)) {
                            $validator->errors()->add("opening_hours.{$day}.{$i}.close", __('admin.errors.opening_hours_incomplete'));
                        } elseif (filled($open) && $close <= $open) {
                            $validator->errors()->add("opening_hours.{$day}.{$i}.close", __('admin.errors.opening_hours_order'));
                        }
                    }
                }
            },
        ]);
    }
}
