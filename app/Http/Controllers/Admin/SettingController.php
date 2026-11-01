<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingsService;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SettingController extends Controller
{
    protected const UPLOAD_KEYS = ['logo', 'logo_dark', 'favicon'];

    public function __construct(
        protected SettingsService $settings,
        protected AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        Gate::authorize('settings.view');

        return view('admin.settings.edit', [
            'settings' => $this->settings->all(),
            'customAssets' => collect(self::UPLOAD_KEYS)->mapWithKeys(fn (string $key): array => [$key => $this->settings->hasCustomAsset($key)])->all(),
            'assetUrls' => collect(self::UPLOAD_KEYS)->mapWithKeys(fn (string $key): array => [$key => $this->settings->assetUrl($key)])->all(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'dateFormats' => UpdateSettingsRequest::DATE_FORMATS,
            'timeFormats' => UpdateSettingsRequest::TIME_FORMATS,
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse|RedirectResponse
    {
        $before = $this->settings->all();
        $values = Arr::only($request->validated(), [
            'app_name', 'contact_email', 'contact_phone', 'available_locales', 'default_locale',
            'default_theme', 'timezone', 'date_format', 'time_format',
        ]);

        DB::transaction(function () use ($request, $values): void {
            $this->settings->set($values);

            foreach (self::UPLOAD_KEYS as $key) {
                if ($request->hasFile($key)) {
                    $this->settings->storeUpload($key, $request->file($key));
                } elseif ($request->boolean("{$key}_remove")) {
                    $this->settings->removeUpload($key);
                }
            }
        });

        $after = $this->settings->all();
        $changed = collect($after)
            ->filter(fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value)
            ->keys()
            ->all();

        if ($changed !== []) {
            $this->audit->log('settings.updated', null, ['changed' => $changed], $request->user());
        }

        return $this->success($request, __('admin.settings.messages.updated'), route('admin.settings.edit'));
    }
}
