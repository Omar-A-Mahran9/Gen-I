<?php

namespace Tests\Feature\Authentication;

use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_login_page_renders_with_metronic_assets(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('assets/admin/css/style.bundle.rtl.css', false)
            ->assertSee('assets/admin/plugins/global/plugins.bundle.js', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="remember"', false)
            ->assertDontSee('tailwind', false);
    }

    public function test_user_can_log_in_and_reach_the_dashboard(): void
    {
        $user = $this->superAdmin(['email' => 'admin@geni.test']);

        $this->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login', 'user_id' => $user->id]);

        // Authenticated session persists across requests.
        $this->get(route('admin.dashboard'))->assertOk()->assertSee($user->name);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_session_is_regenerated_on_login(): void
    {
        $this->superAdmin(['email' => 'admin@geni.test']);
        $this->get(route('admin.login'));
        $before = session()->getId();

        $this->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->superAdmin(['email' => 'admin@geni.test']);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'wrong-password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed']);
        $this->assertStringNotContainsString('wrong-password', json_encode(AuditLog::query()->latest('id')->first()->properties));
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('admin.login.store'), [])->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $this->superAdmin(['email' => 'inactive@geni.test', 'is_active' => false]);

        $this->post(route('admin.login.store'), ['email' => 'inactive@geni.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('admin.auth.inactive')]);

        $this->assertGuest();
    }

    public function test_users_without_admin_access_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'customer@geni.test']);

        $this->post(route('admin.login.store'), ['email' => 'customer@geni.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('admin.errors.no_admin_access')]);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->superAdmin(['email' => 'admin@geni.test']);

        for ($i = 0; $i < LoginRequest::MAX_ATTEMPTS; $i++) {
            $this->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'wrong']);
        }

        $response = $this->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'password']);

        // Even the correct password is refused while locked out.
        $response->assertSessionHasErrors('email');
        $this->assertNotSame(__('auth.failed'), session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_remember_me_sets_a_recaller_cookie(): void
    {
        $this->superAdmin(['email' => 'admin@geni.test']);

        $response = $this->post(route('admin.login.store'), ['email' => 'admin@geni.test', 'password' => 'password', 'remember' => '1']);

        $response->assertCookie(auth()->guard('web')->getRecallerName());
    }

    public function test_user_can_log_out(): void
    {
        $user = $this->superAdmin();

        $this->actingAs($user)->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.logout', 'user_id' => $user->id]);
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_logout_requires_post_with_csrf_protection(): void
    {
        $this->actingAs($this->superAdmin())->get('/admin/logout')->assertStatus(405);
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_page(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.login'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->superAdmin();
        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_protected_pages_reject_guests(): void
    {
        foreach (['admin.dashboard', 'admin.users.index', 'admin.roles.index', 'admin.settings.edit', 'admin.profile.edit', 'admin.notifications.index', 'admin.audit-logs.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }
}
