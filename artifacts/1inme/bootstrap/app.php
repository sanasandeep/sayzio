<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
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
            // AI Companion: cross-origin embed posts JSON from arbitrary
            // sites — origin allow-listing inside the controller plus the
            // `cmp_*` routing constraint are the access controls here.
            'companion/*/message',
            'companion/*/session',
            'companion/*/rate',
            'webhooks/*',
            'api/*',
            // RFC 8058 one-click unsubscribe POST originates from inbox
            // providers (Gmail, Apple Mail), not a browser session, so
            // it cannot present a CSRF token. The signed URL itself is
            // the authenticator.
            'newsletter/unsubscribe/*',
        ]);
        $middleware->alias([
            'onboarding.gate'   => \App\Modules\User\Middleware\RedirectToOnboarding::class,
            'api.optional_auth' => \App\Modules\Api\Middleware\OptionalSanctum::class,
            'workspace.scope'   => \App\Modules\User\Middleware\SetActiveWorkspace::class,
            'workspace.can'     => \App\Modules\User\Middleware\RequireWorkspacePermission::class,
            'workspace.owner'   => \App\Modules\User\Middleware\RequireWorkspaceOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // AI features throw InsufficientAiCreditsException when the user
        // tries to spend more credits than they have. Surface it as a
        // friendly redirect to the top-up page (or JSON for API/AJAX)
        // rather than a 500 — the message includes a clear CTA.
        $exceptions->render(function (\App\Services\AI\InsufficientAiCreditsException $e, \Illuminate\Http\Request $request) {
            $msg = "You need {$e->required} AI credits to do that — you have {$e->balance}. Top up to continue.";
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'message'  => $msg,
                        'code'     => 'insufficient_ai_credits',
                        'required' => $e->required,
                        'balance'  => $e->balance,
                        'top_up'   => route('user.ai-credits.show'),
                    ],
                ], 402);
            }
            return redirect()->route('user.ai-credits.show')->with('error', $msg);
        });

        // Standardize JSON error envelope for /api/* routes.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => [
                        'message' => 'The given data was invalid.',
                        'code'    => 'validation_failed',
                        'details' => $e->errors(),
                    ],
                ], 422);
            }

            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'error' => ['message' => 'Unauthenticated', 'code' => 'unauthenticated'],
                ], 401);
            }

            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'error' => ['message' => $e->getMessage() ?: 'Forbidden', 'code' => 'forbidden'],
                ], 403);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return response()->json([
                    'error' => ['message' => 'Route not found', 'code' => 'not_found'],
                ], 404);
            }

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return response()->json([
                    'error' => ['message' => 'Method not allowed', 'code' => 'method_not_allowed'],
                ], 405);
            }

            if ($e instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return response()->json([
                    'error' => ['message' => 'Too many requests', 'code' => 'rate_limited'],
                ], 429);
            }

            return null;
        });
    })->create();
