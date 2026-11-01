<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\AccountUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_shows_roles_and_status(): void
    {
        $user = $this->userWithRole('manager');

        $this->actingAs($user)->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee(__('admin.roles.names.manager'))
            ->assertSee(__('admin.common.active'));
    }

    public function test_user_can_update_name_and_language(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->put(route('admin.profile.update'), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'locale' => 'en',
        ])->assertSessionHasNoErrors()->assertSessionHas('locale', 'en');

        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_changing_email_requires_current_password(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->putJson(route('admin.profile.update'), ['name' => $user->name, 'email' => 'new@geni.test'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->actingAs($user)->putJson(route('admin.profile.update'), ['name' => $user->name, 'email' => 'new@geni.test', 'current_password' => 'password'])
            ->assertOk();

        $this->assertSame('new@geni.test', $user->fresh()->email);
    }

    public function test_profile_form_cannot_change_roles_or_status(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->putJson(route('admin.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => ['super-admin'],
            'is_active' => 0,
        ])->assertOk();

        $user->refresh();
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue($user->is_active);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->putJson(route('admin.profile.password'), [
            'current_password' => 'wrong',
            'password' => 'Another-Pass-123',
            'password_confirmation' => 'Another-Pass-123',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->actingAs($user)->putJson(route('admin.profile.password'), [
            'current_password' => 'password',
            'password' => 'Another-Pass-123',
            'password_confirmation' => 'Another-Pass-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('Another-Pass-123', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'profile.password_changed', 'user_id' => $user->id]);
    }

    public function test_notifications_can_be_listed_and_marked_as_read(): void
    {
        $user = $this->userWithRole('staff');
        $user->notify(new AccountUpdated('roles_changed'));
        $user->notify(new AccountUpdated('password_reset'));

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(__('admin.notifications.types.roles_changed.title'))
            ->assertSee('data-geni-unread-badge', false);

        $this->actingAs($user)->get(route('admin.notifications.index'))->assertOk()->assertSee(__('admin.notifications.types.password_reset.title'));

        $first = $user->notifications()->first();
        $this->actingAs($user)->postJson(route('admin.notifications.read', $first->id))->assertOk()->assertJsonPath('unread', 1);
        $this->assertNotNull($first->fresh()->read_at);

        $this->actingAs($user)->postJson(route('admin.notifications.read-all'))->assertOk()->assertJsonPath('unread', 0);
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_users_cannot_read_other_users_notifications(): void
    {
        $owner = $this->userWithRole('staff');
        $other = User::factory()->create()->assignRole('staff');
        $owner->notify(new AccountUpdated('updated'));

        $this->actingAs($other)->postJson(route('admin.notifications.read', $owner->notifications()->first()->id))->assertNotFound();
        $this->assertSame(1, $owner->unreadNotifications()->count());
    }
}
