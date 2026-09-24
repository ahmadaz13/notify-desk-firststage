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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
        $middleware->alias([
            'financial.idempotency' => \App\Http\Middleware\EnsureFinancialIdempotency::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'return.today' => \App\Http\Middleware\ReturnToToday::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Client credential secrets/notes are never flashed back as old input (§18.3).
        $exceptions->dontFlash(['credential_secret', 'credential_note']);
    })->create();
