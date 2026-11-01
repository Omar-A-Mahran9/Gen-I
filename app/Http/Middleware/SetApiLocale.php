<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the API locale from the Accept-Language header (ar default, en supported).
 */
class SetApiLocale
{
    public function __construct(protected Locale $locale) {}

    public function handle(Request $request, Closure $next): Response
    {
        $preferred = $request->getPreferredLanguage($this->locale->available());
        $locale = $request->headers->has('Accept-Language') && $this->locale->isSupported($preferred)
            ? $preferred
            : $this->locale->default();

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
