<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AuthenticateExternalIntegration;
use App\Http\Middleware\AuthenticateIphoneIntegration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        // The production container is reachable only through Apache, which
        // supplies the HTTPS scheme and the /mia forwarded prefix.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'external.integration' => AuthenticateExternalIntegration::class,
            'iphone.integration' => AuthenticateIphoneIntegration::class,
        ]);
        $middleware->validateCsrfTokens(except: ['telegram/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
