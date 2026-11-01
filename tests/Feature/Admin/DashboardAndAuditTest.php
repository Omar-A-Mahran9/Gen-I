<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_real_counts_inside_the_metronic_layout(): void
    {
        $admin = $this->superAdmin(['name' => 'Dashboard Owner']);
        User::factory()->count(3)->create();
        User::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('id="kt_app_sidebar"', false)
            ->assertSee('id="kt_app_header"', false)
            ->assertSee('id="kt_app_footer"', false)
            ->assertSee('id="kt_scrolltop"', false)
            ->assertSee('data-kt-element="theme-mode-menu"', false)
            ->assertSee(__('admin.dashboard.welcome', ['name' => 'Dashboard Owner']))
            ->assertSee(__('admin.dashboard.business.title'))
            ->assertSee('data-geni-business-stat="products"', false);

        $this->assertSame(5, $response->viewData('stats')['users']);
        $this->assertSame(4, $response->viewData('stats')['active_users']);
        $this->assertSame(1, $response->viewData('stats')['inactive_users']);
        $this->assertSame(4, $response->viewData('stats')['roles']);
    }

    public function test_dashboard_hides_sections_the_user_cannot_access(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(__('admin.dashboard.recent_activity.title'))
            ->assertDontSee(__('admin.dashboard.recent_logins.title'));
    }

    public function test_audit_log_lists_and_filters_entries(): void
    {
        $admin = $this->superAdmin();
        $logger = app(AuditLogger::class);
        $logger->log('settings.updated', null, ['changed' => ['app_name']], $admin);
        $logger->log('user.created', $admin, [], $admin);

        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();

        $this->actingAs($admin)->getJson(route('admin.audit-logs.index', [
            'draw' => 1, 'start' => 0, 'length' => 10,
            'filters' => ['event' => 'settings.updated'],
            'columns' => [['data' => 'created_at']], 'order' => [['column' => 0, 'dir' => 'desc']],
        ]))->assertOk()->assertJsonPath('recordsFiltered', 1);
    }

    public function test_audit_logger_redacts_secrets(): void
    {
        $log = app(AuditLogger::class)->log('test.event', null, [
            'password' => 'Secret-123',
            'nested' => ['api_token' => 'abc', 'safe' => 'visible'],
            'remember_token' => 'xyz',
        ]);

        $this->assertSame('[redacted]', $log->properties['password']);
        $this->assertSame('[redacted]', $log->properties['nested']['api_token']);
        $this->assertSame('[redacted]', $log->properties['remember_token']);
        $this->assertSame('visible', $log->properties['nested']['safe']);
    }

    public function test_error_pages_use_the_admin_layout(): void
    {
        $this->get('/admin/does-not-exist')
            ->assertNotFound()
            ->assertSee(__('admin.errors.pages.404.title'))
            ->assertSee('assets/admin/css/style.bundle', false);
    }
}
