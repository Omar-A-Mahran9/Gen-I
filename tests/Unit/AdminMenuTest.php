<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_hides_items_and_empty_sections_without_permission(): void
    {
        $keys = fn (User $user): array => collect(app(AdminMenu::class)->for($user))->pluck('key')->all();

        $staffKeys = $keys($this->userWithRole('staff'));
        $this->assertContains('dashboard', $staffKeys);
        $this->assertContains('profile', $staffKeys);
        $this->assertNotContains('access', $staffKeys);
        $this->assertNotContains('settings', $staffKeys);
        $this->assertNotContains('administration', $staffKeys);

        $managerMenu = collect(app(AdminMenu::class)->for($this->userWithRole('manager')));
        $access = $managerMenu->firstWhere('key', 'access');
        $this->assertSame(['users', 'roles'], collect($access['children'])->pluck('key')->all());
        $this->assertNull($managerMenu->firstWhere('key', 'settings'));

        $this->assertSame([], app(AdminMenu::class)->for(null));
    }
}
