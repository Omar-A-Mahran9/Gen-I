<?php

namespace App\View\Composers;

use App\Services\Settings\SettingsService;
use App\Support\AdminMenu;
use App\Support\Locale;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Shares branding, locale, theme, navigation and header notification data
 * with the admin layouts. Partials included by a layout inherit this data.
 */
class AdminLayoutComposer
{
    public function __construct(
        protected SettingsService $settings,
        protected Locale $locale,
        protected AdminMenu $menu,
    ) {}

    public function compose(View $view): void
    {
        $user = auth()->user();
        $locale = $this->locale->current();

        $view->with([
            'brand' => [
                'name' => (string) $this->settings->get('app_name', config('app.name')),
                'logo' => $this->settings->assetUrl('logo'),
                'logo_dark' => $this->settings->assetUrl('logo_dark'),
                'favicon' => $this->settings->assetUrl('favicon'),
            ],
            'currentLocale' => $locale,
            'localeMeta' => $this->locale->meta($locale),
            'isRtl' => $this->locale->isRtl($locale),
            'availableLocales' => collect($this->locale->available())
                ->mapWithKeys(fn (string $code): array => [$code => $this->locale->meta($code)])
                ->all(),
            'localeFont' => $this->locale->font($locale),
            'defaultTheme' => (string) $this->settings->get('default_theme', 'light'),
            'authUser' => $user,
            'menu' => $user ? $this->menu->for($user) : [],
            'headerNotifications' => $user ? $user->notifications()->latest()->limit(5)->get() : new Collection,
            'unreadNotificationsCount' => $user ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
