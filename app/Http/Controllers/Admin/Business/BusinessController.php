<?php

namespace App\Http\Controllers\Admin\Business;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Shared helpers for business-module admin controllers.
 */
abstract class BusinessController extends Controller
{
    /**
     * DataTables filters + search value mapped onto service filters.
     *
     * @return array<string, mixed>
     */
    protected function tableFilters(Request $request): array
    {
        $filters = collect((array) $request->input('filters', []))
            ->map(fn ($v) => is_scalar($v) ? (string) $v : null)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        $filters['search'] = (string) $request->input('search.value', '');

        return $filters;
    }

    /**
     * Organizations the user may pick when creating a record (super administrators only).
     *
     * @return array<int, string>
     */
    protected function organizationOptions(User $user): array
    {
        return Organization::query()->forUser($user)->orderBy('name')->pluck('name', 'id')->all();
    }
}
