<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Seeds the approved default settings (docs/database-design.md §6, TD-07):
 * Arabic default locale, Arabic + English available, light theme.
 *
 * Safe to run repeatedly: a key is written only when it does not exist yet, so values
 * changed later through the Settings page are never overwritten. No secrets are stored.
 */
class SettingsSeeder extends Seeder
{
    /**
     * Keys seeded from SettingsService::definitions() defaults.
     *
     * @var array<int, string>
     */
    public const KEYS = ['default_locale', 'available_locales', 'default_theme'];

    public function run(SettingsService $settings): void
    {
        $definitions = $settings->definitions();
        $existing = Setting::query()->whereIn('key', self::KEYS)->pluck('key')->all();

        $missing = collect(self::KEYS)
            ->diff($existing)
            ->mapWithKeys(fn (string $key): array => [$key => $definitions[$key]['default']])
            ->all();

        if ($missing !== []) {
            $settings->set($missing);
        }
    }
}
