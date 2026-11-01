<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * Locale and text-direction helpers for the admin UI.
 */
class Locale
{
    public function __construct(protected SettingsService $settings) {}

    /**
     * @return array<int, string>
     */
    public function available(): array
    {
        return $this->settings->availableLocales();
    }

    public function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, $this->available(), true);
    }

    public function default(): string
    {
        $default = (string) $this->settings->get('default_locale', config('app.locale'));

        return $this->isSupported($default) ? $default : $this->available()[0];
    }

    public function current(): string
    {
        return app()->getLocale();
    }

    public function direction(?string $locale = null): string
    {
        return config('admin.locales.'.($locale ?? $this->current()).'.dir', 'ltr');
    }

    public function isRtl(?string $locale = null): bool
    {
        return $this->direction($locale) === 'rtl';
    }

    /**
     * @return array{name: string, short: string, dir: string}
     */
    public function meta(?string $locale = null): array
    {
        return config('admin.locales.'.($locale ?? $this->current()));
    }

    /**
     * @return array{family: string|null, faces: array<int, string>}
     */
    public function font(?string $locale = null): array
    {
        return config('admin.fonts.'.($locale ?? $this->current()), ['family' => null, 'faces' => []]);
    }
}
