<?php

use App\Http\Middleware\GeneralAccessMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR,
        );

        // This is an API without a login page: unauthenticated requests get a 401, never a redirect
        $middleware->redirectGuestsTo(static fn () => null);

        $middleware->append(SecurityHeadersMiddleware::class);

        $middleware->alias([
            'general_access' => GeneralAccessMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API clients always get JSON, so a missing token is a 401 instead of a redirect to a non-existent login route (500)
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // A route id like "abc" or "1e2" makes Postgres reject the cast (SQLSTATE 22P02); that is a missing record, not a server error
        $exceptions->render(static function (QueryException $exception, Request $request) {
            if ($exception->getCode() === '22P02' && ($request->is('api/*') || $request->expectsJson())) {
                return response()->json(['message' => 'Not found.'], 404);
            }

            return null;
        });
    })->create();
