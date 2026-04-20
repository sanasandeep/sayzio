<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/webhooks.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo('/user/login');
        $middleware->validateCsrfTokens(except: [
            '*/track/session',
            '*/track/heartbeat',
            'sp/*/track',
            'webhooks/*',
        ]);
        $middleware->alias([
            'onboarding.gate' => \App\Modules\User\Middleware\RedirectToOnboarding::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
