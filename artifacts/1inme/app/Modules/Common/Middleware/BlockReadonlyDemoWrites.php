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
 *   still renders fully (looking editable) for this account. This already
 *   covers read-only lookups like the dialer universal search
 *   (`GET user/dialer/search`) and the live QR/biolink SVG previews.
 * - A short allowlist of essential auth actions (login, logout,
 *   demo-login) is exempted so the account can still authenticate and
 *   sign out normally; those are not "saves".
 * - A curated allowlist of *interactive-but-non-persisting* POST surfaces
 *   ({@see self::ALLOWED_INTERACTIVE_ROUTE_NAMES}) is exempted so a demo
 *   visitor can actually TRY showcase features that use POST purely to
 *   compute/render a result — never to save. Every route on that list has
 *   been verified to (a) write nothing to the database, and (b) NOT charge
 *   the coin wallet (no `OpenAiService` / `AiUsageCharger` call), so it is
 *   safe to run repeatedly from a shared public account:
 *     - QR generation (standalone + per-link): renders a PNG/SVG response.
 *     - Biolink draft preview: writes only a 10-minute cache key.
 *     - Bulk URL / biolink preview: validates + renders, persists nothing.
 *     - AI coin-cost *estimates*: pure arithmetic dry-runs, no AI call.
 *
 *   Deliberately NOT on this list (and therefore still blocked for the
 *   demo) are the AI-*generation* surfaces — AI companion send, ask-a-mind,
 *   persona/coach test, AI persona generate, and the AI artistic QR
 *   (`qr-codes.generate-art`) — plus the dialer live `lookup`. Those are
 *   genuine writes for a read-only public account: they either persist rows
 *   (companion messages, dialer-lookup log, saved artwork file) and/or
 *   charge real coins from the shared demo wallet on every anonymous call,
 *   and all AI generation additionally aborts when the AI engine is
 *   disabled. Opening them would defeat the read-only guarantee and expose
 *   the demo to cost/abuse; giving the demo a truly non-persisting,
 *   non-charging AI path is separate feature work.
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
     * Interactive-but-non-persisting POST surfaces the read-only demo may
     * exercise end to end (see the class docblock for the safety contract:
     * every entry writes nothing and charges no coins). Keep this list in
     * lockstep with the routes it names — a route that starts persisting or
     * charging must be removed from here.
     */
    private const ALLOWED_INTERACTIVE_ROUTE_NAMES = [
        // QR generation — renders an image response, saves nothing.
        'user.qrcode.download',
        'user.links.qrcode.download',
        // Live biolink draft preview — writes only a short-lived cache key.
        'user.links.preview-draft',
        // Bulk import previews — validate + render, persist nothing.
        'user.links.url.bulk.preview',
        'user.links.biolink.bulk.preview',
        // AI coin-cost estimates — pure dry-run arithmetic, no AI call.
        'user.ai.cost-estimate',
        'user.links.ai-builder.estimate',
        'user.brand-kits.estimate',
        'user.ai.marketing-strategist.estimate',
    ];

    /**
     * Path patterns for auth endpoints that carry no route name
     * (routes/api.php largely doesn't name its routes).
     */
    private const ALLOWED_PATHS = [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/demo',
        // REST parity for the non-persisting AI coin-cost estimates above.
        'api/v1/links/*/ai-builder/estimate',
        'api/v1/brand-kits/estimate',
        'api/v1/ai/marketing-strategist/estimate',
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
        if ($name && (
            in_array($name, self::ALLOWED_ROUTE_NAMES, true)
            || in_array($name, self::ALLOWED_INTERACTIVE_ROUTE_NAMES, true)
        )) {
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
