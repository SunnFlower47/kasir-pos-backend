<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'force.https' => \App\Http\Middleware\ForceHttps::class,
            'security.headers' => \App\Http\Middleware\SecurityHeaders::class,
            'electron.origin' => \App\Http\Middleware\AllowElectronOrigin::class,
            'cors' => \App\Http\Middleware\HandleCors::class,
        ]);

        // CORS FIRST - handle OPTIONS before anything else
        $middleware->prepend(\App\Http\Middleware\HandleCors::class);

        // Security headers - enable in all environments
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Force HTTPS only in production
        // Use env() directly instead of app()->environment() to avoid reflection error
        if (env('APP_ENV') === 'production') {
            $middleware->append(\App\Http\Middleware\ForceHttps::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle API routes - return JSON responses
        $exceptions->render(function (Throwable $e, $request) {
            // Only handle API routes
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Exceptions\ApiExceptionHandler::handle($e, $request);
            }
        });

        // Log all exceptions (except validation and 404s)
        $exceptions->report(function (Throwable $e) {
            // Don't log validation exceptions or 404s
            if ($e instanceof ValidationException ||
                $e instanceof ModelNotFoundException ||
                $e instanceof NotFoundHttpException) {
                return;
            }

            // Log with context
            Log::error('Exception occurred', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        });
    })->create();
