<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = $this->superAdmin();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'app_name' => 'GEN-I Admin',
            'contact_email' => 'support@example.test',
            'contact_phone' => '+20 100 000 0000',
            'available_locales' => ['ar', 'en'],
            'default_locale' => 'ar',
            'default_theme' => 'dark',
            'timezone' => 'Africa/Cairo',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        ], $overrides);
    }

    public function test_settings_page_shows_current_values(): void
    {
        $this->actingAs($this->admin)->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('name="app_name"', false)
            ->assertSee('data-kt-image-input', false);
    }

    public function test_admin_can_update_settings_and_cache_is_refreshed(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(config('app.name'));

        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload())
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('success');

        $settings = app(SettingsService::class);
        $settings->flush();
        $this->assertSame('GEN-I Admin', $settings->get('app_name'));
        $this->assertSame('dark', $settings->get('default_theme'));
        $this->assertSame(['ar', 'en'], $settings->get('available_locales'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.updated']);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('GEN-I Admin')->assertSee('data-bs-theme-mode="dark"', false);
    }

    public function test_logo_upload_is_stored_and_replaced(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png', 300, 80),
        ]))->assertSessionHasNoErrors();

        $first = app(SettingsService::class)->get('logo');
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith('branding/', $first);

        app()->forgetInstance(SettingsService::class);
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo2.webp', 300, 80),
        ]))->assertSessionHasNoErrors();

        $second = app(SettingsService::class)->get('logo');
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
    }

    public function test_logo_can_be_removed(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload(['logo' => UploadedFile::fake()->image('logo.png')]));
        $path = app(SettingsService::class)->get('logo');

        app()->forgetInstance(SettingsService::class);
        $this->actingAs($this->admin)->put(route('admin.settings.update'), $this->payload(['logo_remove' => '1']))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($path);
        $this->assertFalse(app(SettingsService::class)->hasCustomAsset('logo'));
    }

    public function test_unsafe_or_oversized_uploads_are_rejected(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $php = UploadedFile::fake()->createWithContent('logo.php', '<?php echo 1;');
        $huge = UploadedFile::fake()->image('logo.png')->size(config('admin.uploads.logo_max_kb') + 1);

        foreach ([$svg, $php, $huge] as $file) {
            $this->actingAs($this->admin)->putJson(route('admin.settings.update'), $this->payload(['logo' => $file]))
                ->assertStatus(422)->assertJsonValidationErrors(['logo']);
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_settings_validation(): void
    {
        $this->actingAs($this->admin)->putJson(route('admin.settings.update'), $this->payload([
            'app_name' => '',
            'available_locales' => ['en'],
            'default_locale' => 'ar',
            'default_theme' => 'neon',
            'timezone' => 'Mars/Base',
        ]))->assertStatus(422)->assertJsonValidationErrors(['app_name', 'default_locale', 'default_theme', 'timezone']);
    }

    public function test_settings_update_requires_permission(): void
    {
        $viewer = $this->userWithRole('staff');
        $viewer->givePermissionTo('settings.view');

        $this->actingAs($viewer)->get(route('admin.settings.edit'))->assertOk()->assertSee('<fieldset disabled', false);
        $this->actingAs($viewer)->putJson(route('admin.settings.update'), $this->payload())->assertForbidden();
    }
}
