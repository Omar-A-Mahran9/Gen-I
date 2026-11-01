<?php

namespace App\Http\Requests\Business;

use Illuminate\Foundation\Http\FormRequest;

class AddressRequest extends FormRequest
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
        return [
            'label' => ['required', 'string', 'max:60'],
            'address_text' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'contact_phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
