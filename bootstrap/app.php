<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The ZKTeco biometric device and the Speso payment webhook are external
        // callers that cannot supply a Laravel CSRF token; both are authenticated
        // by other means instead (device protocol / HMAC signature respectively).
        $middleware->validateCsrfTokens(except: [
            'iclock/*',
            'webhooks/speso',
        ]);

        $middleware->alias([
            'admission.applicant' => \App\Http\Middleware\EnsureApplicantIsAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
