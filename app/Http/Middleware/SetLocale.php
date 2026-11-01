<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the admin UI locale: session choice, then the user's saved
 * preference, then the default locale from settings (Arabic by default).
 */
class SetLocale
{
    public function __construct(protected Locale $locale) {}

    public function handle(Request $request, Closure $next): Response
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->user()?->locale,
        ];

        $locale = collect($candidates)->first(fn (?string $candidate): bool => $this->locale->isSupported($candidate))
            ?? $this->locale->default();

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
