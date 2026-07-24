<?php

use App\Http\Middleware\EnsureBranchContext;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Enable Sanctum cookie-based SPA authentication on the /api routes.
        $middleware->statefulApi();

        // Resolve + enforce the active branch on scoped routes.
        $middleware->alias([
            'branch' => EnsureBranchContext::class,
            // Not auto-registered by spatie/laravel-permission on Laravel 11+.
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // This is an API-only backend with no named 'login' web route. Without this, the
        // "auth" middleware's default un-authenticated redirect calls route('login') for any
        // request that doesn't explicitly ask for JSON, which throws RouteNotFoundException
        // and turns what should be a clean 401 into a 500.
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Render every API exception through the standard envelope.
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage(), 'VALIDATION_ERROR', 422, $e->errors());
            }
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Unauthenticated.', 'UNAUTHENTICATED', 401);
            }
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error($e->getMessage() ?: 'This action is unauthorized.', 'FORBIDDEN', 403);
            }
        });
        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('Resource not found.', 'NOT_FOUND', 404);
            }
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'Request failed.',
                    'HTTP_'.$e->getStatusCode(),
                    $e->getStatusCode(),
                );
            }
        });
    })->create();
