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

    /** Named auth routes that must always be allowed through untouched. */
    public const ALLOWED_ROUTE_NAMES = [
        'user.login.submit',
        'user.logout',
        'user.demo.login',
    ];

    /**
     * Path patterns (matched with {@see Request::is()}) for auth endpoints
     * that carry no route name (routes/api.php largely doesn't name its
     * routes).
     */
    public const ALLOWED_PATHS = [
        'api/v1/auth/login',
        'api/v1/auth/logout',
        'api/v1/auth/demo',
        // REST parity for the non-persisting AI coin-cost estimates above.
        'api/v1/links/*/ai-builder/estimate',
        'api/v1/brand-kits/estimate',
        'api/v1/ai/marketing-strategist/estimate',
    ];

    /*
     * -----------------------------------------------------------------------
     * Interactive-but-non-persisting write allowlist (drift-guarded)
     * -----------------------------------------------------------------------
     * Some POST/PUT endpoints are *interactive* yet save nothing: AI previews,
     * cost estimates, quotes, number lookups, draft renders. Blocking these
     * for the demo account shows a wrong, confusing "changes aren't saved"
     * banner on a feature that never saved anything, so they are allowed
     * through here.
     *
     * This list is hand-maintained and WILL rot as new features ship, so it is
     * kept honest by the `demo:check-allowlist` drift guard
     * ({@see \App\Console\Commands\CheckDemoAllowlist}). That command scans every
     * registered write route whose route-name OR URI ends in one of
     * {@see self::INTERACTIVE_VERB_SUFFIXES} (or a segment starting with
     * "preview") and FAILS if the route is neither listed as allowed below nor
     * consciously acknowledged as intentionally-blocked. So the moment a new
     * `.estimate` / `.suggest` / `.preview*` / `lookup` / `generate-art` / etc.
     * route is added, CI forces a developer to classify it here — it can never
     * silently drift out of sync. Run it via `composer check:demo-allowlist`.
     *
     * When adding an entry: verify the controller method genuinely persists
     * nothing (no save/create/update/delete). If it *does* persist despite an
     * interactive-looking name (e.g. `preview-complete`), add it to the
     * ACKNOWLEDGED_* lists instead so it stays blocked.
     */

    /** Interactive, non-persisting web routes a demo visitor may exercise. */
    public const ALLOWED_INTERACTIVE_ROUTE_NAMES = [
        'creator-profile.subscribe.preview-promo', // previews a promo-code discount
        'rm.public.quote',                          // restaurant estimated-bill quote
        'sb.public.quote',                          // service-booking price quote
        'user.ai.coach.suggest',                    // AI coach suggestions
        'user.ai.cost-estimate',                    // unified AI coin-cost estimate (read-only, no charge)
        'user.ai.marketing-strategist.estimate',    // AI credit-cost estimate
        'user.ai.mind.think',                       // AI reasoning preview
        'user.billing.companies.emails.preview',    // renders an email-template preview
        'user.brand-kits.estimate',                 // AI credit-cost estimate
        'user.links.ai-builder.estimate',           // AI credit-cost estimate
        'user.links.biolink.bulk.preview',          // bulk mail-merge dry-run preview
        'user.links.preview-draft',                 // renders an unsaved editor draft
        'user.links.qrcode.download',               // renders a link's QR image (no charge/save)
        'user.links.url.bulk.preview',              // bulk URL-import dry-run preview
        'user.qrcode.download',                      // renders a standalone QR image (no charge/save)
        'user.resume.cover-letters.estimate',       // AI credit-cost estimate
        'user.resume.tailor.estimate',              // AI credit-cost estimate
    ];

    /** Interactive, non-persisting API path patterns a demo visitor may exercise. */
    public const ALLOWED_INTERACTIVE_PATHS = [
        'api/v1/account/merge/preview',                 // account-merge dry-run preview
        'api/v1/ai/marketing-strategist/estimate',      // AI credit-cost estimate
        'api/v1/brand-kits/estimate',                   // AI credit-cost estimate
        'api/v1/billing/companies/*/emails/*/preview',  // email-template preview render
        'api/v1/links/*/ai-builder/estimate',           // AI credit-cost estimate
        'api/v1/restaurant/*/quote',                    // restaurant estimated-bill quote
        'api/v1/service-booking/*/quote',               // service-booking price quote
    ];

    /**
     * Routes whose name/URI *looks* interactive (matches the verb patterns)
     * but that actually PERSIST state, so they must stay blocked for the demo.
     * Listed only so the drift guard treats them as consciously reviewed
     * rather than as missing/unclassified. Each entry notes what it writes.
     */
    public const ACKNOWLEDGED_NONALLOWED_ROUTE_NAMES = [
        'user.payouts.preview-complete', // writes creator_payment_connections (marks provider connected)
        'user.qr-codes.generate-art',    // charges coins for AI image gen against the shared demo wallet
    ];

    /** Interactive-looking API paths that persist and must stay blocked. */
    public const ACKNOWLEDGED_NONALLOWED_PATHS = [
        'api/v1/dialer/lookup',         // persists a DialerLookup history row
        'api/v1/qr-codes/generate-art', // charges coins for AI image gen against the shared demo wallet
    ];

    /**
     * Trailing route-name / URI segments that signal an interactive,
     * non-persisting feature. The `demo:check-allowlist` drift guard treats
     * any write route whose last name/URI segment is one of these — or starts
     * with "preview" — as interactive, and requires it to be classified in one
     * of the lists above. Deliberately EXCLUDES persisting verbs such as
     * `generate` (creates content), `apply`, `confirm`, `complete`.
     */
    public const INTERACTIVE_VERB_SUFFIXES = [
        'estimate',
        'suggest',
        'think',
        'lookup',
        'generate-art',
        'check',
        'quote',
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

        // ACKNOWLEDGED_NONALLOWED_* are intentionally NOT checked here: they
        // match the interactive verb patterns but persist, so they stay blocked
        // — they exist only to keep the drift guard's classification complete.
        foreach (array_merge(self::ALLOWED_PATHS, self::ALLOWED_INTERACTIVE_PATHS) as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
