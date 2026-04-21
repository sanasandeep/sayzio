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
            'webhooks/*',
            'api/*',
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
