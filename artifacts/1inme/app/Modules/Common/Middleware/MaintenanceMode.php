<?php

namespace App\Modules\Common\Middleware;

use App\Modules\Admin\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Per-area maintenance switch.
 *
 * Admins (admin guard) always bypass. The admin panel and its login screens
 * are never gated so the operator can still flip the toggles back off.
 *
 * Areas:
 *   - api        → /api/*                          (returns 503 JSON)
 *   - user_app   → /user/*                         (returns HTML maintenance page)
 *   - biolinks   → public biolink visitor pages    (returns HTML maintenance page)
 *   - marketing  → everything else on the public site (HTML maintenance page)
 */
class MaintenanceMode
{
    public const AREAS = ['marketing', 'user_app', 'api', 'biolinks'];

    public const AREA_LABELS = [
        'marketing' => 'Marketing site (landing, pricing, blog, policies)',
        'user_app'  => 'User dashboard (/user/*)',
        'api'       => 'API (mobile + JSON clients)',
        'biolinks'  => 'Public Link in Bio profile pages',
        'all'       => 'Entire site (admin-only lockdown)',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Never gate the admin panel itself, asset/health/webhook plumbing.
        if ($this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        // Logged-in admins always bypass so they can fix things while the
        // gate is up. This covers the back-office admin guard, a web-guard
        // user holding a platform admin role, and token-authenticated API
        // admins.
        if ($this->isAdminActor($request)) {
            return $next($request);
        }

        // App-wide admin-only lockdown: when on, EVERY surface is blocked for
        // non-admins regardless of the per-area toggles below. Admins were
        // already let through above.
        if ((bool) AppSetting::get('maintenance_admin_only_enabled', false)) {
            return $this->blockedResponse($request, 'all');
        }

        $area = $this->detectArea($request);
        if ($area === null) {
            return $next($request);
        }

        $key = 'maintenance_' . $area . '_enabled';
        if (!(bool) AppSetting::get($key, false)) {
            return $next($request);
        }

        return $this->blockedResponse($request, $area);
    }

    /**
     * True when the current request is made by a platform admin — covering the
     * back-office admin guard, a session web-guard user holding a platform
     * admin role, and a token-authenticated API caller holding one.
     *
     * This middleware is registered globally and runs BEFORE the route-level
     * `auth:sanctum` middleware, so bearer-token API callers are not yet on
     * the session web guard. We resolve the Sanctum token user here so
     * admin-role API clients keep full access during an admin-only lockdown.
     */
    private function isAdminActor(Request $request): bool
    {
        // Back-office admin (session) guard.
        if (Auth::guard('admin')->check()) {
            return true;
        }

        // Session web-guard user holding a platform admin role.
        if ($this->userHasAdminRole(Auth::guard('web')->user())) {
            return true;
        }

        // Token-authenticated API client holding a platform admin role.
        if ($request->bearerToken()) {
            try {
                if ($this->userHasAdminRole(Auth::guard('sanctum')->user())) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Sanctum guard unavailable / token resolution failed —
                // treat as a non-admin and fall through to the gate.
            }
        }

        return false;
    }

    /** True iff the given authenticatable is a platform admin (web role). */
    private function userHasAdminRole($user): bool
    {
        return $user !== null
            && method_exists($user, 'hasAdminRole')
            && $user->hasAdminRole();
    }

    /**
     * Build the 503 maintenance response for the given area, returning a JSON
     * envelope for API/JSON clients and the HTML maintenance page otherwise.
     */
    private function blockedResponse(Request $request, string $area)
    {
        $message = trim((string) AppSetting::get('maintenance_message', ''));
        $eta     = trim((string) AppSetting::get('maintenance_eta', ''));

        if ($area === 'api' || $request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'error' => [
                    'code'    => 'maintenance_mode',
                    'message' => $message !== '' ? $message : 'This service is temporarily down for maintenance.',
                    'eta'     => $eta !== '' ? $eta : null,
                    'area'    => $area,
                ],
            ], 503, [
                'Retry-After' => '300',
            ]);
        }

        $style = (string) AppSetting::get('maintenance_style', 'standard');

        return response()->view('maintenance', [
            'area'    => $area,
            'label'   => self::AREA_LABELS[$area] ?? $area,
            'message' => $message,
            'eta'     => $eta,
            'style'   => $style === 'upgrade' ? 'upgrade' : 'standard',
        ], 503, [
            'Retry-After' => '300',
        ]);
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        return $request->is(
            'admin',
            'admin/*',
            'admin-assets/*',
            'up',
            'webhooks/*',
            'storage/*',
            '.well-known/*',
        );
    }

    private function detectArea(Request $request): ?string
    {
        if ($request->is('api/*')) {
            return 'api';
        }

        if ($request->is('user', 'user/*')) {
            return 'user_app';
        }

        // Biolink visitor surface: viewer endpoints + the catch-all /{alias}
        // public profile + redirect routes. Identify them by a name prefix on
        // the matched route so we don't accidentally gate marketing pages.
        $route = $request->route();
        if ($route) {
            $name = (string) ($route->getName() ?? '');
            if (
                str_starts_with($name, 'redirect.')
                || str_starts_with($name, 'viewer.')
                || str_starts_with($name, 'public.companion.')
                || $name === 'feed.index'
                || $name === 'feed.notifications.read'
            ) {
                return 'biolinks';
            }
        }

        return 'marketing';
    }
}
