<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Typed application settings stored in the `settings` table and cached.
 *
 * Secrets must never be stored here.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.all';

    public const UPLOAD_DISK = 'public';

    public const UPLOAD_DIRECTORY = 'branding';

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $resolved = null;

    /**
     * Known settings: key => [type, group, default].
     *
     * @return array<string, array{type: string, group: string, default: mixed}>
     */
    public function definitions(): array
    {
        return [
            'app_name' => ['type' => 'string', 'group' => 'general', 'default' => config('app.name')],
            'contact_email' => ['type' => 'string', 'group' => 'general', 'default' => null],
            'contact_phone' => ['type' => 'string', 'group' => 'general', 'default' => null],
            'logo' => ['type' => 'file', 'group' => 'branding', 'default' => null],
            'logo_dark' => ['type' => 'file', 'group' => 'branding', 'default' => null],
            'favicon' => ['type' => 'file', 'group' => 'branding', 'default' => null],
            'default_locale' => ['type' => 'string', 'group' => 'localization', 'default' => config('app.locale')],
            'available_locales' => ['type' => 'json', 'group' => 'localization', 'default' => array_keys(config('admin.locales'))],
            'timezone' => ['type' => 'string', 'group' => 'localization', 'default' => config('app.timezone')],
            'date_format' => ['type' => 'string', 'group' => 'localization', 'default' => 'Y-m-d'],
            'time_format' => ['type' => 'string', 'group' => 'localization', 'default' => 'H:i'],
            'default_theme' => ['type' => 'string', 'group' => 'appearance', 'default' => 'light'],
        ];
    }

    /**
     * All settings with defaults applied.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $defaults = array_map(fn (array $definition): mixed => $definition['default'], $this->definitions());

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
                ->get(['key', 'value', 'type'])
                ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $this->castFromStorage($setting->value, $setting->type)])
                ->all());
        } catch (QueryException) {
            // The settings table does not exist yet (fresh install before migrations).
            $stored = [];
        }

        $stored = array_filter($stored, fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->resolved = array_merge($defaults, $stored);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * Persist several settings at once.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $definitions = $this->definitions();

        foreach ($values as $key => $value) {
            $definition = $definitions[$key] ?? ['type' => 'string', 'group' => 'general'];

            Setting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->castForStorage($value, $definition['type']),
                    'type' => $definition['type'],
                    'group' => $definition['group'],
                ],
            );
        }

        $this->flush();
    }

    /**
     * Store an uploaded branding file on the public disk, replacing the previous one.
     */
    public function storeUpload(string $key, UploadedFile $file): string
    {
        $previous = $this->storedValue($key);
        $path = $file->store(self::UPLOAD_DIRECTORY, self::UPLOAD_DISK);

        $this->set([$key => $path]);
        $this->deleteStoredFile($previous);

        return $path;
    }

    public function removeUpload(string $key): void
    {
        $previous = $this->storedValue($key);

        $this->set([$key => null]);
        $this->deleteStoredFile($previous);
    }

    /**
     * Public URL of a branding asset, falling back to the placeholder from config/admin.php.
     */
    public function assetUrl(string $key): string
    {
        $path = $this->get($key);

        if (is_string($path) && $path !== '' && Storage::disk(self::UPLOAD_DISK)->exists($path)) {
            return Storage::disk(self::UPLOAD_DISK)->url($path);
        }

        return asset(config('admin.brand.'.$key, config('admin.brand.logo')));
    }

    public function hasCustomAsset(string $key): bool
    {
        $path = $this->get($key);

        return is_string($path) && $path !== '' && Storage::disk(self::UPLOAD_DISK)->exists($path);
    }

    /**
     * Locales enabled in settings, limited to locales the application supports.
     *
     * @return array<int, string>
     */
    public function availableLocales(): array
    {
        $supported = array_keys(config('admin.locales'));
        $enabled = array_values(array_intersect($supported, (array) $this->get('available_locales', $supported)));

        return $enabled !== [] ? $enabled : $supported;
    }

    public function flush(): void
    {
        $this->resolved = null;
        Cache::forget(self::CACHE_KEY);
    }

    protected function storedValue(string $key): ?string
    {
        $value = Setting::query()->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function deleteStoredFile(?string $path): void
    {
        if ($path !== null && str_starts_with($path, self::UPLOAD_DIRECTORY.'/')) {
            Storage::disk(self::UPLOAD_DISK)->delete($path);
        }
    }

    protected function castForStorage(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'json' => json_encode(array_values((array) $value), JSON_UNESCAPED_UNICODE),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    protected function castFromStorage(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'json' => json_decode($value, true),
            'bool' => $value === '1',
            default => $value,
        };
    }
}
