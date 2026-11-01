<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_and_seeds_roles_when_missing(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Full name', 'First Admin')
            ->expectsQuestion('Email address', 'First@Geni.test')
            ->expectsQuestion('Password (input hidden)', 'Strong-Pass-123')
            ->expectsQuestion('Confirm password', 'Strong-Pass-123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'first@geni.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('Strong-Pass-123', $user->password));
        $this->assertTrue($user->can('users.delete'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'auditable_id' => $user->id]);

        // The created account can sign in to the dashboard.
        $this->post(route('admin.login.store'), ['email' => 'first@geni.test', 'password' => 'Strong-Pass-123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_it_refuses_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'taken@geni.test']);

        $this->artisan('app:create-admin', ['--name' => 'Dup', '--email' => 'taken@geni.test'])
            ->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'taken@geni.test')->count());
    }

    public function test_it_rejects_weak_or_mismatched_passwords(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Weak', '--email' => 'weak@geni.test'])
            ->expectsQuestion('Password (input hidden)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->artisan('app:create-admin', ['--name' => 'Mismatch', '--email' => 'mismatch@geni.test'])
            ->expectsQuestion('Password (input hidden)', 'Strong-Pass-123')
            ->expectsQuestion('Confirm password', 'Strong-Pass-124')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'weak@geni.test']);
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@geni.test']);
    }
}
