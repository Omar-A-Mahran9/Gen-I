<?php

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_is_the_default_locale_and_renders_rtl(): void
    {
        $this->assertSame('ar', config('app.locale'));
        $this->assertSame('en', config('app.fallback_locale'));

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSee('style.bundle.rtl.css', false)
            ->assertSee('IBM Plex Sans Arabic', false)
            ->assertSee(__('admin.auth.heading', [], 'ar'));
    }

    public function test_language_switcher_changes_locale_and_direction(): void
    {
        $this->get(route('admin.locale.switch', 'en'))->assertRedirect()->assertSessionHas('locale', 'en');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr"', false)
            ->assertSee('assets/admin/css/style.bundle.css', false)
            ->assertDontSee('style.bundle.rtl.css', false)
            ->assertDontSee('IBM Plex Sans Arabic', false)
            ->assertSee('Welcome back');

        $this->get(route('admin.locale.switch', 'ar'));
        $this->get(route('admin.login'))->assertSee('<html lang="ar" dir="rtl"', false);
    }

    public function test_unsupported_locale_is_rejected(): void
    {
        $this->get(route('admin.locale.switch', 'fr'))->assertNotFound();
    }

    public function test_locale_choice_is_saved_on_the_signed_in_user(): void
    {
        $user = $this->superAdmin(['locale' => null]);

        $this->actingAs($user)->get(route('admin.locale.switch', 'en'))->assertRedirect();
        $this->assertSame('en', $user->fresh()->locale);

        // A fresh session uses the saved preference.
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertSee('<html lang="en" dir="ltr"', false)->assertSee('Dashboard');
    }

    public function test_navigation_is_translated(): void
    {
        $user = $this->superAdmin(['locale' => 'ar']);

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertSee('المستخدمون')
            ->assertSee('الإعدادات')
            ->assertSee('تسجيل الخروج');
    }

    public function test_validation_messages_are_translated(): void
    {
        $this->post(route('admin.login.store'), [])->assertSessionHasErrors();
        $this->assertSame('حقل البريد الإلكتروني مطلوب.', session('errors')->first('email'));

        $this->get(route('admin.locale.switch', 'en'));
        $this->post(route('admin.login.store'), [])->assertSessionHasErrors();
        $this->assertSame('The Email address field is required.', session('errors')->first('email'));
    }

    public function test_default_locale_follows_settings(): void
    {
        settings()->set(['default_locale' => 'en']);

        $this->get(route('admin.login'))->assertSee('<html lang="en" dir="ltr"', false);
    }
}
