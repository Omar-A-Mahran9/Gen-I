<?php

namespace App\Http\Requests\Admin\Profile;

use App\Models\User;
use App\Support\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Only name, email and language can be changed here; roles and status cannot.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
            'locale' => ['nullable', 'string', Rule::in(app(Locale::class)->available())],
            // Changing the sign-in email requires the current password.
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->input('email') !== $user->email),
                'nullable', 'string', 'current_password:web',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
