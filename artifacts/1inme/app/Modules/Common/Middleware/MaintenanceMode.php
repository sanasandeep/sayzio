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
        'biolinks'  => 'Public biolink profile pages',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Never gate the admin panel itself, asset/health/webhook plumbing.
        if ($this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        // Logged-in admins always bypass so they can fix things while the
        // gate is up.
        if (Auth::guard('admin')->check()) {
            return $next($request);
        }

        $area = $this->detectArea($request);
        if ($area === null) {
            return $next($request);
        }

        $key = 'maintenance_' . $area . '_enabled';
        if (!(bool) AppSetting::get($key, false)) {
            return $next($request);
        }

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

        return response()->view('maintenance', [
            'area'    => $area,
            'label'   => self::AREA_LABELS[$area] ?? $area,
            'message' => $message,
            'eta'     => $eta,
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
