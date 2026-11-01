<?php

use App\Services\Settings\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('settings')) {
    /**
     * Read an application setting (or the settings service when called without a key).
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $service = app(SettingsService::class);

        return $key === null ? $service : $service->get($key, $default);
    }
}

if (! function_exists('ltr_isolate')) {
    /**
     * Wrap left-to-right data (dates, times) in Unicode directional isolates
     * when the UI is right-to-left, so "2026-11-01 14:30" keeps its order in Arabic.
     */
    function ltr_isolate(string $value): string
    {
        return config('admin.locales.'.app()->getLocale().'.dir') === 'rtl' && $value !== ''
            ? "\u{2066}{$value}\u{2069}"
            : $value;
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date in the configured display timezone and date format.
     */
    function format_date(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ltr_isolate(Carbon::parse($value)
            ->setTimezone((string) settings('timezone', config('app.timezone')))
            ->translatedFormat((string) settings('date_format', 'Y-m-d')));
    }
}

if (! function_exists('format_datetime')) {
    /**
     * Format a timestamp in the configured display timezone, date and time format.
     */
    function format_datetime(CarbonInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return ltr_isolate(Carbon::parse($value)
            ->setTimezone((string) settings('timezone', config('app.timezone')))
            ->translatedFormat(settings('date_format', 'Y-m-d').' '.settings('time_format', 'H:i')));
    }
}
