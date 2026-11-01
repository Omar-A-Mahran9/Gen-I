<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureTokenUserIsActive;
use App\Http\Middleware\EnsureUserCanAccessAdmin;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
        then: function (): void {
            Route::middleware('web')
                ->prefix(config('admin.prefix', 'admin'))
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->api(append: [SetApiLocale::class]);

        // Resolve the locale before authentication/throttling so their errors are translated
        // (inserted after StartSession in the priority list).
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetLocale::class);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, SetApiLocale::class);

        $middleware->alias([
            'admin.access' => EnsureUserCanAccessAdmin::class,
            'active' => EnsureTokenUserIsActive::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectGuestsTo(fn (): string => route('admin.login'));
        $middleware->redirectUsersTo(fn (): string => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent error envelope for the versioned API (docs/api-contracts.md §2).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $requestId = $request->attributes->get('request_id');
            $envelope = fn (int $status, string $code, string $message, mixed $details = null, array $headers = []) => response()->json([
                'error' => array_filter([
                    'code' => $code,
                    'message' => $message,
                    'details' => $details,
                    'request_id' => $requestId,
                ], fn (mixed $value): bool => $value !== null),
            ], $status, $headers);

            return match (true) {
                $e instanceof ValidationException => $envelope(422, 'validation_failed', __('api.errors.validation_failed'), $e->errors()),
                $e instanceof AuthenticationException => $envelope(401, 'unauthenticated', __('api.errors.unauthenticated')),
                $e instanceof HttpExceptionInterface => match ($e->getStatusCode()) {
                    403 => $envelope(403, 'forbidden', __('api.errors.forbidden')),
                    404 => $envelope(404, 'not_found', __('api.errors.not_found')),
                    405 => $envelope(405, 'method_not_allowed', __('api.errors.method_not_allowed')),
                    409 => $envelope(409, 'conflict', $e->getMessage() ?: __('api.errors.conflict')),
                    429 => $envelope(429, 'rate_limited', __('api.errors.rate_limited'), null, $e->getHeaders()),
                    503 => $envelope(503, 'service_unavailable', __('api.errors.service_unavailable')),
                    default => $envelope($e->getStatusCode(), 'http_error', $e->getMessage() ?: __('api.errors.server_error')),
                },
                default => $envelope(500, 'server_error', config('app.debug') ? $e->getMessage() : __('api.errors.server_error')),
            };
        });
    })->create();
