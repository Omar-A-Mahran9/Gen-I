<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the permission-filtered sidebar tree from config('admin.menu').
 */
class AdminMenu
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function for(?User $user): array
    {
        $items = $this->filter(config('admin.menu', []), $user);

        // Drop section headings that have no visible items after them.
        $result = [];
        foreach ($items as $index => $item) {
            if (isset($item['heading'])) {
                $next = $items[$index + 1] ?? null;
                if ($next === null || isset($next['heading'])) {
                    continue;
                }
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function filter(array $items, ?User $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (($item['visible'] ?? true) === false || ! $this->permitted($item, $user)) {
                continue;
            }

            if (isset($item['children'])) {
                $item['children'] = $this->filter($item['children'], $user);

                if ($item['children'] === []) {
                    continue;
                }

                $item['active'] = collect($item['children'])->contains(fn (array $child): bool => $child['active']);
                $item['url'] = null;
            } elseif (! isset($item['heading'])) {
                if (! isset($item['route']) || ! Route::has($item['route'])) {
                    continue;
                }

                $item['url'] = route($item['route']);
                $item['active'] = request()->routeIs(...(array) ($item['active'] ?? $item['route']));
            }

            $visible[] = $item;
        }

        return array_values($visible);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function permitted(array $item, ?User $user): bool
    {
        $permissions = (array) ($item['permission'] ?? []);

        if ($permissions === []) {
            return $user !== null;
        }

        return $user !== null && collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));
    }
}
