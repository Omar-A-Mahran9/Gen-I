<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only active users holding the admin access permission may use the dashboard.
 * Deactivated users are signed out immediately.
 */
class EnsureUserCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => __('admin.auth.inactive')], 401);
            }

            return redirect()->route('admin.login')->withErrors(['email' => __('admin.auth.inactive')]);
        }

        abort_unless($user->can(config('admin.access_permission')), 403, __('admin.errors.no_admin_access'));

        return $next($request);
    }
}
