<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'];

    public const TIME_FORMATS = ['H:i', 'h:i A'];

    public function authorize(): bool
    {
        return $this->user()->can('settings.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $locales = array_keys(config('admin.locales'));
        $logoMax = config('admin.uploads.logo_max_kb');
        $faviconMax = config('admin.uploads.favicon_max_kb');

        return [
            'app_name' => ['required', 'string', 'max:100'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'available_locales' => ['required', 'array', 'min:1'],
            'available_locales.*' => ['string', 'distinct', Rule::in($locales)],
            'default_locale' => ['required', 'string', Rule::in($locales), Rule::in((array) $this->input('available_locales', []))],
            'default_theme' => ['required', 'string', Rule::in(config('admin.themes'))],
            'timezone' => ['required', 'string', 'timezone:all'],
            'date_format' => ['required', 'string', Rule::in(self::DATE_FORMATS)],
            'time_format' => ['required', 'string', Rule::in(self::TIME_FORMATS)],
            // Raster images only: SVG uploads are rejected because they can carry scripts.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', "max:{$logoMax}"],
            'logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', "max:{$logoMax}"],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', "max:{$faviconMax}"],
            'logo_remove' => ['nullable', 'boolean'],
            'logo_dark_remove' => ['nullable', 'boolean'],
            'favicon_remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('admin.settings.fields');
    }
}
