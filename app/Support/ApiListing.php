<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Shared API listing conventions (docs/api-contracts.md §1):
 *   ?search=…  ?filter[field]=value  ?sort=-created_at  ?page=2  ?per_page=25 (1–100)
 *
 * Only documented (allow-listed) filter and sort keys are accepted. Unknown keys and
 * malformed paging values are rejected with the standard 422 "validation_failed"
 * envelope instead of being silently ignored, so clients learn about typos.
 */
class ApiListing
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 25;

    /**
     * Filters for the service query: allow-listed filter[...] keys plus search.
     *
     * @param  array<int, string>  $allowed
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function filters(Request $request, array $allowed): array
    {
        $raw = $request->query('filter', []);

        if (! is_array($raw)) {
            throw ValidationException::withMessages(['filter' => [__('api.listing.filter_format')]]);
        }

        $unknown = array_diff(array_map('strval', array_keys($raw)), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['filter' => [__('api.listing.unknown_filter', [
                'keys' => implode(', ', $unknown),
                'allowed' => $allowed === [] ? '—' : implode(', ', $allowed),
            ])]]);
        }

        $filters = collect($raw)
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
     *
     * @throws ValidationException
     */
    public static function paginate(Builder $query, Request $request, array $sortable, string $default = '-id'): LengthAwarePaginator
    {
        $errors = [];
        $sort = $request->query('sort', $default);
        $key = is_string($sort) ? ltrim($sort, '-') : null;

        if ($request->has('sort') && ($key === null || ! isset($sortable[$key]))) {
            $errors['sort'] = [__('api.listing.invalid_sort', ['allowed' => implode(', ', array_keys($sortable)) ?: '—'])];
        }

        foreach (['per_page' => self::MAX_PER_PAGE, 'page' => null] as $param => $max) {
            if ($request->has($param) && ! self::isPositiveInteger($request->query($param), $max)) {
                $errors[$param] = [__('api.listing.invalid_'.$param, ['max' => $max])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        if ($key !== null && isset($sortable[$key])) {
            $query->orderBy($sortable[$key], str_starts_with((string) $sort, '-') ? 'desc' : 'asc');
        } else {
            $query->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
        }

        return $query->paginate((int) $request->query('per_page', self::DEFAULT_PER_PAGE))->withQueryString();
    }

    protected static function isPositiveInteger(mixed $value, ?int $max): bool
    {
        if (! is_string($value) || ! ctype_digit($value) || (int) $value < 1) {
            return false;
        }

        return $max === null || (int) $value <= $max;
    }
}
