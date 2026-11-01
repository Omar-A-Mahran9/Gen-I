<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
            'locale' => ['nullable', 'string', Rule::in(app(Locale::class)->available())],
            // Only super administrators assign organizations; other actors' users stay in their organization.
            'organization_id' => $this->user()->isSuperAdmin()
                ? ['nullable', 'integer', Rule::exists('organizations', 'id')]
                : ['prohibited'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            // The admin form always sends is_active (hidden 0 + checkbox); when absent, new accounts are active.
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : true,
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
