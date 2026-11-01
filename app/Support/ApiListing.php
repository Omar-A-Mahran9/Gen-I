<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared API listing conventions (docs/api-contracts.md §1):
 *   ?search=…  ?filter[field]=value  ?sort=-created_at  ?per_page=25 (max 100)
 */
class ApiListing
{
    public const MAX_PER_PAGE = 100;

    /**
     * Filters for the service query: allow-listed filter[...] keys plus search.
     *
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     */
    public static function filters(Request $request, array $allowed): array
    {
        $filters = collect((array) $request->query('filter', []))
            ->only($allowed)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : null)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        if (is_string($request->query('search')) && $request->query('search') !== '') {
            $filters['search'] = $request->query('search');
        }

        return $filters;
    }

    /**
     * Apply an allow-listed sort and paginate.
     *
     * @param  Builder<*>  $query
     * @param  array<string, string>  $sortable  public key => column
     */
    public static function paginate(Builder $query, Request $request, array $sortable, string $default = '-id'): LengthAwarePaginator
    {
        $sort = (string) $request->query('sort', $default);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        if (isset($sortable[$key])) {
            $query->orderBy($sortable[$key], $direction);
        } else {
            $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), self::MAX_PER_PAGE);

        return $query->paginate($perPage)->withQueryString();
    }
}
