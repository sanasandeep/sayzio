<?php

namespace App\Modules\Common\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Task #3498 — global read-only write-guard for the read-only demo
 * account (`is_readonly_demo = true` on `users`, e.g. demo@sayzio.app).
 * The flag — not a hardcoded email — is what this guard keys on, so the
 * behavior only ever applies to accounts explicitly marked.
 *
 * Registered via `$middleware->web(append: ...)` and
 * `$middleware->api(append: ...)` in bootstrap/app.php so it runs inside
 * the route pipeline (after routing has resolved `$request->route()`,
 * unlike a truly global `$middleware->append()` middleware) and covers
 * every authenticated web form/AJAX POST and every Sanctum-authenticated
 * `/api/v1` write, without touching individual controllers.
 *
 * - GET/HEAD/OPTIONS always pass through so every editor/settings screen
 *   still renders fully (looking editable) for this account.
 * - A short allowlist of essential auth actions (login, logout,
 *   demo-login) is exempted so the account can still authenticate and
 *   sign out normally; those are not "saves".
 * - Every other state-changing method (POST/PUT/PATCH/DELETE) from this
 *   account is short-circuited BEFORE the controller/model ever runs, so
 *   nothing is persisted:
 *     - AJAX / JSON / `/api/*` requests get the standard `{error:{...}}`
 *       envelope with a friendly message.
 *     - Normal browser form posts get redirected back with a flash
 *       message.
 */
class BlockReadonlyDemoWrites
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /** Named routes that must always be allowed through untouched. */
    private const ALLOWED_ROUTE_NAMES = [
        'user.login.submit',
        'user.logout',
        'user.demo.login',
    ];

    /**
     * Path patterns for auth endpoints that carry no route name
     * (routes/api.php largely doesn't name its routes).
     */
    private const ALLOWED_PATHS = [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/demo',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if ($this->isAllowlisted($request)) {
            return $next($request);
        }

        $user = Auth::guard('web')->user() ?: Auth::guard('sanctum')->user();
        if (!$user || !$user->is_readonly_demo) {
            return $next($request);
        }

        $message = "This is a demo — changes aren't saved.";

        if ($request->is('api/*') || $request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => ['message' => $message, 'code' => 'demo_readonly'],
            ], 403);
        }

        return back()->with('error', $message);
    }

    private function isAllowlisted(Request $request): bool
    {
        $name = $request->route()?->getName();
        if ($name && in_array($name, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        foreach (self::ALLOWED_PATHS as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
