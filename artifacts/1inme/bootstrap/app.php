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
        // Dev-only startup fast-path: answer "/" with an instant 200 splash for
        // a short window after boot so the Replit dev readiness probe (hard-wired
        // to poll "/" with a tight latency bound) recognizes the healthy server
        // instead of rejecting the heavy home render. No-op in production.
        $middleware->prepend(\App\Modules\Common\Middleware\DevStartupProbe::class);

        // Production startup fast-path: the autoscale promote probe also polls
        // the base path "/" (besides the configured "/up"), and a cold home
        // render over the distant RDS exceeds its deadline, failing the
        // publish. Answers probe UAs and the first seconds after boot with an
        // instant 200. No-op in dev.
        $middleware->prepend(\App\Modules\Common\Middleware\ProdStartupProbe::class);

        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo('/user/login');
        $middleware->validateCsrfTokens(except: [
            '*/track/session',
            '*/track/heartbeat',
            'sp/*/track',
            // Public Buzz (Social Proof) email capture — cross-origin embeds
            // POST from arbitrary sites; gating is server-side (notification
            // must resolve to an active capture-type notification + spam check).
            'sp/*/subscribe',
            // Anonymous landing-page CTA tracking — endpoint is allow-listed
            // server-side (see MarketingEventController::ALLOWED).
            'marketing-events/track',
            // AI Companion: cross-origin embed posts JSON from arbitrary
            // sites — origin allow-listing inside the controller plus the
            // `cmp_*` routing constraint are the access controls here.
            'companion/*/message',
            'companion/*/session',
            'companion/*/rate',
            // Site-wide AI assistant ("Zio Bot"): the marketing-site widget
            // posts cross-origin from 1inme.com with no app session/CSRF
            // cookie. Visitors are forced to the anonymous "marketing"
            // surface server-side and every endpoint is rate-limited +
            // visitor-token bound, mirroring the companion endpoints above.
            'assistant/*',
            // Conversational Biolink visitor flow. Visitor has no app
            // session; rate-limited and bound to opaque `cvs_*` ids.
            'cv/*/start',
            'cv/*/answer',
            'cv/*/drop',
            'webhooks/*',
            'api/*',
            // RFC 8058 one-click unsubscribe POST originates from inbox
            // providers (Gmail, Apple Mail), not a browser session, so
            // it cannot present a CSRF token. The signed URL itself is
            // the authenticator.
            'newsletter/unsubscribe/*',
            // RFC 8058 one-click unsubscribe POST for Zio Digest broadcast
            // emails — same rationale as the newsletter one above.
            'digest/unsubscribe/*',
            // RFC 8058 one-click unsubscribe POST for the mobile-app launch
            // announcement email — same rationale as the newsletter one above.
            'app-launch/unsubscribe/*',
            // RFC 8058 one-click unsubscribe POST for the weekly
            // backlink digest — same rationale as the newsletter one
            // above (inbox provider POSTs cannot present a CSRF token;
            // the signed URL is the authenticator).
            'user/notifications/backlink-digest/unsubscribe/*',
            // RFC 8058 one-click unsubscribe POST for the periodic email
            // verification reminder — same rationale as above.
            'user/notifications/email-verification-reminder/unsubscribe/*',
        ]);
        // Per-area Maintenance Mode gate. Runs on every request so the
        // admin-managed switch in app_settings can take parts of the site
        // offline (marketing, user app, api, biolinks) without a deploy.
        $middleware->append(\App\Modules\Common\Middleware\MaintenanceMode::class);

        // Session-time enforcement of admin temporary holds: an already
        // signed-in user who gets suspended is logged out on their next
        // web request (no-op for admin/API guards). See Task #2106.
        // The audience-prompt persona cookie (`ap_type_{link_id}`) is written
        // client-side as a plain cookie, so it must be excluded from cookie
        // encryption or EncryptCookies nulls it on every request (breaking
        // subscriber persona stamping + visitor-type block targeting). The
        // name is dynamic (per-link id), so we swap in a subclass that
        // supports the prefix instead of an exact-name `except:` list.
        $middleware->web(replace: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class => \App\Modules\Common\Middleware\EncryptCookies::class,
        ]);

        $middleware->web(append: [
            \App\Modules\User\Middleware\EnsureNotSuspended::class,
            // Task #3498 — blocks every state-changing request from the
            // read-only demo account (is_readonly_demo) before it reaches
            // any controller/model.
            \App\Modules\Common\Middleware\BlockReadonlyDemoWrites::class,
        ]);

        // API-guard (Sanctum) counterpart of the read-only demo write-guard
        // above — covers the mobile app / REST API path.
        // LogSlowRequests is prepended so it wraps the full request lifecycle
        // (including auth, metering, and controller execution) for an accurate
        // end-to-end duration and query count.
        $middleware->api(prepend: [
            \App\Modules\Api\Middleware\LogSlowRequests::class,
        ]);
        $middleware->api(append: [
            \App\Modules\Common\Middleware\BlockReadonlyDemoWrites::class,
        ]);

        $middleware->web(append: [
            \App\Modules\User\Middleware\RequiresNameMiddleware::class,
        ]);

        $middleware->alias([
            'onboarding.gate'   => \App\Modules\User\Middleware\RedirectToOnboarding::class,
            'api.optional_auth' => \App\Modules\Api\Middleware\OptionalSanctum::class,
            'api.meter'         => \App\Modules\Api\Middleware\MeterApiUsage::class,
            'workspace.scope'   => \App\Modules\User\Middleware\SetActiveWorkspace::class,
            'workspace.can'     => \App\Modules\User\Middleware\RequireWorkspacePermission::class,
            'workspace.owner'   => \App\Modules\User\Middleware\RequireWorkspaceOwner::class,
            'workspace.2fa'     => \App\Modules\User\Middleware\EnsureTwoFactorPolicy::class,
            'portal.session'    => \App\Modules\User\Middleware\ResolvePortalSession::class,
            'user.can'          => \App\Modules\User\Middleware\UserPermission::class,
            'contacts.sync-on-open' => \App\Modules\User\Middleware\SyncGoogleContactsOnOpen::class,
            'brand.primary'     => \App\Modules\Common\Middleware\RedirectToPrimaryBrandDomain::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // AI features throw InsufficientCoinsForAiException when the user
        // tries to spend more coins than they have in their wallet. AI
        // usage is now charged straight from the coin wallet, so surface
        // it as a friendly redirect to the wallet top-up page (or JSON
        // for API/AJAX) rather than a 500 — the message includes a CTA.
        $exceptions->render(function (\App\Services\AI\InsufficientCoinsForAiException $e, \Illuminate\Http\Request $request) {
            $msg = "You need {$e->required} coins to do that — you have {$e->balance}. Top up your wallet to continue.";
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'message'  => $msg,
                        'code'     => 'insufficient_coins',
                        'required' => $e->required,
                        'balance'  => $e->balance,
                        'top_up'   => route('user.wallet.buy'),
                    ],
                ], 402);
            }
            return redirect()->route('user.wallet.buy')->with('error', $msg);
        });

        // Developer-experience safety net for a fundamentally un-migrated DB.
        // When a query fails because a core table does not exist (Postgres
        // undefined_table 42P01 / SQLite/MySQL "no such table"), show one
        // clear "run migrations" page instead of a raw stack trace on every
        // route. Gated to non-production so genuine production errors are
        // never masked; API/JSON clients still get the standard envelope below.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            if (app()->environment('production')) {
                return null;
            }
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            if (!\App\Modules\Common\Support\DatabaseErrors::isMissingTable($e)) {
                return null;
            }

            return response()->view('setup-required', [], 503);
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
