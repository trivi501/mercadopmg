<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->api(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->throttleApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Formato estandarizado de errores para la API: {message, code, errors?}
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $code, $message] = match (true) {
                $e instanceof ValidationException => [422, 'validation_error', $e->getMessage()],
                $e instanceof AuthenticationException => [401, 'unauthenticated', 'No autenticado.'],
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException, $e instanceof UnauthorizedException => [403, 'forbidden', 'No tiene permiso para realizar esta acción.'],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'Recurso no encontrado.'],
                $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Demasiadas solicitudes. Intente más tarde.'],
                default => [null, null, null],
            };

            if ($status === null) {
                return null;
            }

            return response()->json(array_filter([
                'message' => $message,
                'code' => $code,
                'errors' => $e instanceof ValidationException ? $e->errors() : null,
            ]), $status, $e instanceof ThrottleRequestsException ? $e->getHeaders() : []);
        });
    })->create();
