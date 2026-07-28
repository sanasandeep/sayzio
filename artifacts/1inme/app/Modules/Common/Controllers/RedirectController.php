<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\AppLinkResolver;
use App\Modules\Common\Services\AutoUtmBuilder;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\Common\Services\SmartRedirectResolver;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\AbVariant;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\BiolinkExperimentService;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RedirectController extends Controller
{
    public function __construct(
        protected LinkTrackingService $trackingService
    ) {}

    /**
     * Bare /handle → creator profile fallback.
     *
     * Invoked only after link/biolink alias resolution misses, so existing
     * short links always win the bare path (the non-breaking collision
     * default). On a handle match we delegate to the very same controller
     * that powers /@handle, so unpublished/owner checks, the 18+ age gate,
     * region gating, viewer blocks, and the canonical @-form URL all behave
     * identically across both entry points. Returns null (→ "not found"
     * page) when nothing usable matches.
     */
    private function tryCreatorProfileFallback(Request $request, string $alias, string $host): mixed
    {
        // Creator profiles live on platform hosts only; custom domains are
        // biolink-only surfaces, so never resolve an arbitrary handle there.
        if (!\App\Modules\Common\Support\PlatformHosts::isPlatformHost($host)) {
            return null;
        }

        // Same case-insensitive handle rule as the /@handle route.
        $handle = ltrim($alias, '@');
        if ($handle === '' || strlen($handle) > 60) {
            return null;
        }

        $exists = \App\Modules\User\Models\User::query()
            ->whereRaw('LOWER(handle) = ?', [strtolower($handle)])
            ->exists();
        if (!$exists) {
            return null;
        }

        // Render the exact /@handle profile (controller enforces all gating).
        return app(\App\Modules\Common\Controllers\CreatorProfilePublicController::class)
            ->show($handle, $request);
    }

    public function handle(Request $request, string $alias)
    {
        // The public /{alias} short-link surface is the most-trafficked
        // entry point on the platform. When the database is unreachable
        // or un-migrated, every resolution query throws — degrade to a
        // branded, self-contained 503 "temporarily unavailable" page
        // (with Retry-After) instead of surfacing a raw 500. Unrelated
        // query errors (application bugs) are re-thrown untouched.
        try {
            return $this->handleResolved($request, $alias);
        } catch (\Throwable $e) {
            if (!\App\Modules\Common\Support\DatabaseErrors::isUnavailable($e)) {
                throw $e;
            }

            Log::warning('Short-link resolution degraded: database unavailable', [
                'alias' => $alias,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->view('common.link-service-unavailable', ['alias' => $alias], 503)
                ->header('Retry-After', '120');
        }
    }

    private function handleResolved(Request $request, string $alias)
    {
        // Resolve to the link via primary alias OR any of its additional aliases.
        // Host-aware: requests on a known custom domain only match links bound to
        // that domain; an unknown/disabled host gets a "domain not connected" notice.
        $host = $request->getHost();
        $link = Link::resolveByAlias($alias, $host);
        if (!$link) {
            // Bare /handle → creator profile fallback (non-breaking).
            // Only attempted when no link/biolink alias resolved, so
            // existing short links always keep priority. Resolution
            // reuses the exact /@handle controller, so all gating
            // (unpublished / 18+ age gate / region / blocks / canonical)
            // behaves identically to the @-prefixed entry point.
            if ($response = $this->tryCreatorProfileFallback($request, $alias, $host)) {
                return $response;
            }

            // Show "Domain not connected" only for hosts that are
            // genuinely an unfinished custom-domain attempt: a row exists
            // in `domains` but it isn't yet verified and active. Platform
            // hosts (APP_URL, Replit dev/deployed URLs, anything else not
            // claimed as a verified custom domain) and verified+active
            // custom domains both fall through to "short link not found".
            if (\App\Modules\Common\Support\PlatformHosts::isPendingCustomDomain($host)) {
                return response()->view('common.domain-not-connected', ['host' => $host], 404);
            }
            return response()->view('common.short-link-not-found', ['alias' => $alias], 404);
        }
        // Admin template-design drafts are internal working copies — never a
        // public page. Only the owning (bridged admin) account may view them,
        // which keeps the editor's live-preview iframe working while hiding
        // unreleased template designs from everyone else.
        if (is_array($link->settings['_template_draft'] ?? null)) {
            $viewer = auth()->guard('web')->user();
            if (! $viewer || (int) $viewer->id !== (int) $link->user_id) {
                return response()->view('common.short-link-not-found', ['alias' => $alias], 404);
            }
        }

        $link->load('pixels');
        // Stash the alias the visitor actually used so views and tracking can
        // distinguish e.g. "/john" vs "/john-instagram" hits on the SAME page.
        $link->setAttribute('_used_alias', $alias);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        // Moderation gate. A biolink hidden by an admin returns a takedown
        // page to ALL non-owner visitors. The owner still sees the page so
        // they can fix it / appeal — they get the appeal banner inline via
        // the report partial in the biolink view.
        if ($link->moderation_state === 'hidden') {
            $viewerId = optional($request->user())->id
                ?? \App\Modules\Common\Services\ViewerSession::id();
            if ((int) $viewerId !== (int) $link->user_id) {
                return response()->view('common.link-moderated', ['link' => $link], 451);
            }
        }

        // Visibility gating (public/registered/followers/subscribers) for the
        // biolink family AND short-link / file / ics / vcf links. Enforced
        // here — before splash / password / preview pages and before the
        // visit is tracked — so a restricted link never leaks its
        // interstitial, download page, or analytics to an unauthorized
        // viewer. Owners (and signed owner-previews) bypass the gate.
        if ($gated = $this->enforceVisibility($request, $link)) {
            return $gated;
        }

        $settings = $link->settings ?? [];

        if (!empty($settings['expire_on_first_click']) && (int) $link->total_clicks >= 1) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        if (!empty($settings['country_restrictions'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            $allowedCountries = array_map('strtoupper', $settings['country_restrictions']);
            if ($visitorCountry === null) {
                abort(403, 'This link is restricted by region and your location could not be determined.');
            }
            if (!in_array(strtoupper($visitorCountry), $allowedCountries)) {
                return response()->view('common.link-expired', ['link' => $link, 'reason' => 'banned_country'], 403);
            }
        }

        // Banned-locations blocklist (separate from the allowlist above so
        // owners can either allow-only-these or block-just-these).
        if (!empty($settings['country_blocklist'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            if ($link->isCountryBlocked($visitorCountry)) {
                return response()->view('common.link-expired', ['link' => $link, 'reason' => 'banned_country'], 403);
            }
        }

        if (!empty($settings['device_targeting'])) {
            $ua = $request->userAgent() ?? '';
            $deviceType = 'desktop';
            if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) {
                $deviceType = 'mobile';
            } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
                $deviceType = 'tablet';
            }
            $allowedDevices = array_map('strtolower', $settings['device_targeting']);
            if (!in_array($deviceType, $allowedDevices)) {
                abort(403, 'This link is not available on your device.');
            }
        }

        // Per-biolink visitor rate limiting & bot-flood throttling.
        // Aborts the public render with HTTP 429 when the visitor's
        // per-IP or per-fingerprint counter has exceeded the per-link
        // override (defaults: 30 IP / 60 fingerprint hits per minute).
        // The decision is memoized on the request so the downstream
        // tracking service tags the click row consistently without
        // bumping the counters a second time. Scoped to biolink renders
        // — short-link / file / url redirects keep their existing
        // throughput unchanged.
        $rateLimiter = app(\App\Modules\Common\Services\VisitorRateLimiter::class);
        $rlUa = $request->userAgent() ?: $request->header('X-1INME-Client');
        if ($link->isBiolinkFamily() && $rateLimiter->shouldThrottle($link, $request, $rlUa)) {
            // Still record the throttled hit so creators see it in the
            // "Blocked attempts this week" stat. The tracking service
            // reads the same memoized decision and tags is_throttled=true
            // / is_bot=true so the row is excluded from default analytics.
            try { $this->trackingService->track($link, $request, $alias, 'web'); }
            catch (\Throwable $e) { /* analytics row is best-effort */ }
            return response()->view('common.rate-limited', [], 429)
                ->header('Retry-After', '60');
        }

        if ($link->is_password_protected && !session("link_unlocked_{$link->id}")) {
            if (!$request->has('password')) {
                return view('common.link-password', compact('link'));
            }

            if (!Hash::check($request->input('password'), $link->password)) {
                return view('common.link-password', [
                    'link' => $link,
                    'error' => 'Incorrect password.',
                ]);
            }

            session(["link_unlocked_{$link->id}" => true]);
        }

        // Splash (intermediate "transition") page — shown once per browser
        // session per link, before the visitor reaches the actual destination.
        // Bypassed when the user clicks the CTA / countdown completes and the
        // page reloads with ?_continue=1.
        if ($link->hasSplashEnabled()
            && !$request->boolean('_continue')
            && !session("link_splash_seen_{$link->id}")) {
            session(["link_splash_seen_{$link->id}" => true]);
            $splash = $link->getSplashConfig();
            $continueUrl = $request->fullUrlWithQuery(['_continue' => 1]);
            $destinationUrl = match ($link->type) {
                'url'     => $link->getDestinationUrl(),
                default   => $continueUrl,
            };
            return response()->view('common.splash', compact('link', 'splash', 'continueUrl', 'destinationUrl'));
        }

        // Optional preview / interstitial page for url/ics/vcf so owners can
        // capture engagement (dwell time) and fire marketing pixels even when
        // the underlying action would otherwise be an immediate redirect or
        // file download. The preview page IS the tracked visitor interaction
        // (one click record + engagement session, mirroring biolink semantics);
        // the follow-up `?_continue=1` request just performs the action and
        // is intentionally NOT tracked again to avoid double-counting.
        $previewableTypes = ['url', 'ics', 'vcf'];
        $previewEnabled   = in_array($link->type, $previewableTypes, true)
            && !empty($settings['show_preview_page']);

        if ($previewEnabled && !$request->boolean('_continue')) {
            $this->trackingService->track($link, $request, $alias, 'web');
            return response()->view('common.preview-page', compact('link'));
        }

        // Track once per visitor click. The app-opener interstitial sends
        // users back here with `?_web=1` for the in-browser fallback — we
        // must NOT re-track that bounce or it inflates click counts.
        $trackedClick = null;
        if (!$previewEnabled && !$request->boolean('_web')) {
            $trackedClick = $this->trackingService->track($link, $request, $alias, 'web');
        }

        // A/B variant resolution from the dedicated `ab_variants` table
        // (the browser-extension's "Shorten as A/B test" feature). Runs
        // BEFORE smart_rules so an active test always wins. Sticky per
        // visitor via a signed cookie keyed by the link id; first-time
        // visitors are bucketed deterministically by hashing their
        // visitor id (IP + UA) with the link id, then the result is
        // pinned via the cookie so subsequent hits skip the random pick.
        $abCookie    = null;
        $abFinalUrl  = null;
        if ($link->type === 'url' && $link->hasActiveAbTest()) {
            [$abFinalUrl, $abCookie] = $this->resolveAbVariant($link, $request);
        }

        // Smart redirect rules — evaluated for ALL link types so that
        // device/country/language/time/AB rules can override the link's
        // normal behavior with a custom destination URL. For 'url' links
        // the resolver also returns the link's own destination as the
        // fallback. For other link types (biolink/file/vcf/ics) we only
        // override when a rule actually matched; otherwise we fall through
        // to the type's normal behavior (landing page / file download).
        $smartCookie = null;
        $finalUrl    = null;
        $smart       = app(SmartRedirectResolver::class)->resolve($link, $request);
        if ($link->type === 'url') {
            $finalUrl    = $smart['url'];
            $smartCookie = $smart['cookie'];
        }

        // Extension-AB wins over the link's normal destination when a
        // variant resolved successfully.
        if ($abFinalUrl !== null) {
            $finalUrl    = $abFinalUrl;
            $smartCookie = $abCookie ?? $smartCookie;
        }

        // Stamp the matched smart-link rule id onto the buffered click so the
        // per-rule analytics breakdown can attribute hits. The click row hasn't
        // been written yet (PersistLinkClicksJob does that after the response),
        // so we mutate the still-buffered payload through the PendingClick handle;
        // the job persists matched_rule_id only when the column exists.
        if ($trackedClick && !empty($smart['rule']['id'])) {
            $trackedClick->setMatchedRuleId((string) $smart['rule']['id']);
        }

        // Link Insurance — when every destination is down and the
        // owner provided a fallback message, render a tiny "temporarily
        // unavailable" page instead of redirecting clickers to a known-
        // broken URL. Only kicks in for url-type links; biolinks/files
        // continue through their normal handling.
        if ($link->type === 'url'
            && $link->insurance_state === 'down'
            && !empty($link->insurance_fallback_message)) {
            return response()->view('common.link-down', [
                'message' => $link->insurance_fallback_message,
            ], 503);
        }

        // Track which destination actually served this click so the
        // dashboard can compare original vs effective traffic. We only
        // tally url-type links because biolink landing pages don't have
        // a "destination URL" per click.
        if ($link->type === 'url' && $finalUrl) {
            if ($link->insurance_state === 'failover' && $link->insurance_active_url) {
                $link->newQuery()->whereKey($link->id)->increment('insurance_failover_serve_count');
                \App\Modules\User\Models\LinkBackup::where('link_id', $link->id)
                    ->where('url', $link->insurance_active_url)
                    ->increment('serve_count');
            } elseif ($link->insurance_enabled) {
                $link->newQuery()->whereKey($link->id)->increment('insurance_primary_serve_count');
            }
        }

        if ($link->type !== 'url' && !empty($smart['rule'])) {
            // A user-defined rule matched on a non-url link → redirect to
            // the rule's URL instead of showing the landing/download.
            $resp = redirect()->away($smart['url'], 302);
            if ($smart['cookie']) $resp->withCookie($smart['cookie']);
            return $resp;
        }

        // Mobile app opener — for url-type links pointing at known apps,
        // serve a tiny interstitial that tries the native app's deep link
        // first and falls back to the web. Bypassed with ?_web=1 (used by
        // the "Continue in browser" button on the interstitial itself).
        // Deep-link / "open in app" interstitial. url-type links resolve the
        // target app from their destination URL and default ON; file-type
        // links resolve from the file's public URL and are opt-in via the same
        // settings.open_in_app field.
        $deepLinkType = in_array($link->type, ['url', 'file'], true);
        $openInApp    = $settings['open_in_app'] ?? ($link->type === 'url');
        if ($deepLinkType && $openInApp && !$request->boolean('_web')) {
            $ua = $request->userAgent() ?? '';
            $isIos     = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);
            $isAndroid = (bool) preg_match('/Android/i', $ua);
            if ($isIos || $isAndroid) {
                $resolveUrl = $link->type === 'url'
                    ? $finalUrl
                    : optional($link->fileLink)->publicUrl();
                $matched = $resolveUrl ? AppLinkResolver::resolve($resolveUrl) : null;
                if ($matched) {
                    $appUrl = $isIos ? ($matched['ios'] ?? null) : ($matched['android'] ?? null);
                    if ($appUrl) {
                        $webUrl = $request->fullUrlWithQuery(['_web' => 1]);
                        $resp = response()->view('common.app-opener', [
                            'app'    => $matched,
                            'appUrl' => $appUrl,
                            'webUrl' => $webUrl,
                        ]);
                        if ($smartCookie) $resp->withCookie($smartCookie);
                        return $resp;
                    }
                }
            }
        }

        // Owner-scoped "draft preview" — when the editor iframe loads with
        // ?_preview=1&_draft=1 (signature must still be valid, ignoring the
        // draft + cache-buster params), merge the cached unsaved form state
        // into the link's settings BEFORE rendering. This is what powers the
        // live device preview so creators can see colour/font/theme/layout
        // tweaks without hitting Save first.
        if ($link->isBiolinkFamily()) {
            $this->applyDraftOverrides($request, $link);
            $this->applyPreviewSimulation($request);
            // Read-time safety net: if a scheduled theme just started but
            // the per-minute activation cron hasn't flipped the row to
            // `active` yet, overlay the theme's snapshot in-memory so
            // visitors don't see the old look during that gap.
            app(\App\Modules\User\Services\BiolinkThemeResolver::class)->applyActiveTheme($link);
        }

        // Auto-pixel interstitial — when the link is opted-in (auto_pixel)
        // and the link's workspace has at least one tracking pixel ID
        // configured, serve a tiny <5KB HTML page that loads the pixel
        // scripts, fires PageView + a custom LinkClick event, then
        // window.location.replace()s to the destination. Workspaces with
        // no pixels configured stay a direct 302 with zero perf cost.
        if ($link->type === 'url'
            && $finalUrl
            && (bool) ($link->auto_pixel ?? false)
            && \Illuminate\Support\Facades\Schema::hasColumn('links', 'auto_pixel')) {
            $ws = $link->workspace_id
                ? \App\Modules\User\Models\Workspace::query()->find($link->workspace_id)
                : null;
            $px = $ws ? (array) (data_get($ws->settings, 'pixels', []) ?? []) : [];
            $providers = [];
            if (!empty($px['meta_id']))   $providers[] = 'meta';
            if (!empty($px['tiktok_id'])) $providers[] = 'tiktok';
            if (!empty($px['google_id'])) $providers[] = 'google';
            if (!empty($providers)) {
                $resp = response()->view('common.auto-pixel-interstitial', [
                    'pixels'        => $px,
                    'providers'     => $providers,
                    'destination'   => $finalUrl,
                    'alias'         => $link->alias,
                    'workspaceName' => $ws?->name ?? '',
                    'beaconUrl'     => url('/api/v1/links/' . $link->alias . '/pixel-fire'),
                ]);
                if ($smartCookie) $resp->withCookie($smartCookie);
                return $resp;
            }
        }

        // Whole biolink family (biolink/conversational/slides/ai_chat)
        // renders through the same page engine — framing headers, A/B
        // plumbing, and lazy social refresh. The specific public template
        // is chosen by biolinkViewFor() based on the link type.
        if ($link->isBiolinkFamily()) {
            return tap(
                $this->applyBiolinkFramingHeaders(
                    response()->view($this->biolinkViewFor($link), compact('link')),
                    $request
                ),
                function () use ($link, $request) {
                    $this->scheduleLazySocialRefresh();
                    // Record this visit against the assigned A/B variant. The
                    // variant was already chosen and the renderer-override
                    // attached inside biolinkViewFor() so the visit count
                    // reflects whatever the visitor actually saw.
                    $variant = $link->_abAssignedVariant ?? null;
                    $exp = $link->_abActiveExperiment ?? null;
                    if ($exp && $variant) {
                        app(BiolinkExperimentService::class)->recordVisit($exp, $variant);
                    }
                }
            );
        }

        return match ($link->type) {
            'url' => tap(
                redirect()->away($finalUrl, $link->redirect_type ?: 301),
                fn ($r) => $smartCookie && $r->withCookie($smartCookie)
            ),
            'file' => $this->handleFileDownload($link),
            // Every event link always renders the full public event page —
            // `?ics=1` remains the direct .ics download path, handled inside
            // handleEventTicketingPage() regardless of ticketing/RSVP state.
            'ics' => $this->handleEventTicketingPage($request, $link),
            'vcf' => $this->handleVcfDownload($link),
            'reviews' => $this->handleReviewsPage($request, $link),
            'resume' => $this->handleResumePage($request, $link),
            'paid_page' => $this->handlePaidPage($request, $link),
            'brand_kit' => $this->handleBrandKitPage($request, $link),
            'calendar' => $this->handleCalendarPage($request, $link),
            'updates' => $this->handleUpdatesPage($request, $link),
            default => abort(404),
        };
    }

    /**
     * Add permissive framing headers to biolink-type responses so the in-app
     * editor preview iframe (and any third-party embed of a bio page) can
     * render without the browser blocking it. When the request is an
     * owner-scoped preview (signed `?_preview=1`), also force no-store so
     * the editor sees fresh content on each refresh without polluting URLs
     * with cache-busting query strings.
     *
     * Pick the public template for a biolink. When the owner has switched
     * the page into "Conversational" mode and there's a published flow
     * available, render the chat UI instead of the static block list.
     */
    /**
     * Sticky weighted variant assignment for the extension's A/B test
     * feature. Reads/sets `_abx_{link_id}` cookie storing the assigned
     * variant id; first-time visitors are bucketed deterministically by
     * hashing (visitor IP + UA + link id) into the cumulative weights.
     * Per-variant counters are bumped on every hit so the popup leader
     * callout updates in near-real-time.
     *
     * @return array{0:?string,1:?\Symfony\Component\HttpFoundation\Cookie}
     */
    protected function resolveAbVariant(Link $link, Request $request): array
    {
        $variants = $link->abVariants()->get();
        if ($variants->isEmpty()) return [null, null];

        $cookieName = '_abx_' . $link->id;
        $existing   = $request->cookie($cookieName);

        $chosen = null;
        if (is_string($existing) && ctype_digit($existing)) {
            $chosen = $variants->firstWhere('id', (int) $existing);
        }

        if (!$chosen) {
            // Deterministic bucketing — same visitor (same IP + UA)
            // always lands on the same variant before the cookie is set,
            // so a request that loses its cookie before the response
            // commits still picks the same bucket.
            $total = (int) $variants->sum('weight');
            if ($total <= 0) return [null, null];

            $seed = sprintf('%s|%s|%d', (string) $request->ip(), (string) $request->userAgent(), (int) $link->id);
            $hash = hexdec(substr(hash('sha256', $seed), 0, 8));
            $r    = ($hash % $total) + 1;
            $running = 0;
            foreach ($variants as $v) {
                $running += (int) $v->weight;
                if ($r <= $running) { $chosen = $v; break; }
            }
        }

        if (!$chosen) return [null, null];

        // Per-variant counters. `visitors` increments only on first
        // assignment (no existing cookie); `clicks` on every hit.
        try {
            if (!is_string($existing) || (int) $existing !== (int) $chosen->id) {
                AbVariant::whereKey($chosen->id)->increment('visitors');
            }
            AbVariant::whereKey($chosen->id)->increment('clicks');
        } catch (\Throwable $e) {
            \Log::warning('AB variant counter bump failed', ['err' => $e->getMessage(), 'link_id' => $link->id]);
        }

        $cookie = \Symfony\Component\HttpFoundation\Cookie::create(
            $cookieName,
            (string) $chosen->id,
            time() + (60 * 60 * 24 * 30), // 30 days
            '/',
            null,
            $request->isSecure(),
            true,  // httpOnly
            false,
            \Symfony\Component\HttpFoundation\Cookie::SAMESITE_LAX
        );

        return [(string) $chosen->url, $cookie];
    }

    protected function biolinkViewFor(Link $link): string
    {
        // Pick the visitor's variant + override the rendered block tree
        // BEFORE the view is resolved so all biolink-family types (biolink,
        // conversational, slides, ai_chat) share the same A/B plumbing.
        $this->applyBiolinkAbExperiment($link);

        // The link type now drives the renderer. Legacy `biolink` rows that
        // still carry a `settings.biolink.mode` are honoured as a fallback
        // so anything that hasn't been migrated yet keeps working.
        $type = $link->type;
        if ($type === 'biolink') {
            $mode = data_get($link->settings, 'biolink.mode', 'list');
            if ($mode === 'conversational') $type = 'conversational';
            elseif ($mode === 'slides')     $type = 'slides';
        }

        if ($type === 'restaurant_menu') {
            // Restaurant menu always renders its own page; if the owner
            // hasn't set up a menu config row yet, fall back to the block
            // page so the URL is never a dead end.
            return $link->restaurantMenu()->exists() ? 'common.restaurant-menu' : 'common.biolink';
        }

        if ($type === 'store_menu') {
            // Store menu renders its own catalog page; fall back to the block
            // page when the owner hasn't created a store config row yet.
            return $link->storeMenu()->exists() ? 'common.store-menu' : 'common.biolink';
        }

        if ($type === 'service_booking') {
            // Service booking renders its own page; fall back to the block
            // page until the owner sets up a booking config row.
            return $link->serviceBooking()->exists() ? 'common.service-booking' : 'common.biolink';
        }

        if ($type !== 'conversational' && $type !== 'slides' && $type !== 'ai_chat') {
            return 'common.biolink';
        }

        $req = request();
        $isOwnerPreview = $req && $req->boolean('_preview')
            && $req->hasValidSignatureWhileIgnoring(['_draft', '_t', '_sim_country', '_sim_device'], false);

        if ($type === 'ai_chat') {
            // The full-page AI chat needs a companion bound to the link to
            // run; without one (or if it's disabled) fall back to the block
            // page so the URL is never a dead end.
            $companion = $link->aiCompanion();
            return ($companion && !$companion->is_disabled) ? 'common.ai-chat' : 'common.biolink';
        }

        if ($type === 'slides') {
            $q = \App\Modules\User\Models\LinkSlideDeck::withoutGlobalScope('workspace')
                ->where('link_id', $link->id);
            if (!$isOwnerPreview) $q->where('is_published', true);
            return $q->exists() ? 'common.biolink-slides' : 'common.biolink';
        }

        // Conversational — same draft preview unlock as before.
        if ($isOwnerPreview) {
            session([
                'cv_preview_link_'.$link->id => now()->addMinutes(30)->getTimestamp(),
            ]);
        }
        $q = \App\Modules\User\Models\ConversationFlow::where('link_id', $link->id);
        if (!$isOwnerPreview) $q->where('is_published', true);
        return $q->exists() ? 'common.biolink-conversational' : 'common.biolink';
    }

    protected function applyBiolinkFramingHeaders($response, Request $request)
    {
        $response->headers->set('X-Frame-Options', 'ALLOWALL');
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        if ($request->boolean('_preview') && $request->hasValidSignatureWhileIgnoring(['_draft', '_t', '_sim_country', '_sim_device'], false)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }
        return $response;
    }

    /**
     * Merge any cached "draft" page-settings overrides into $link in-place
     * so the device-preview iframe renders the owner's unsaved edits.
     * Gated by the same signed-URL proof of ownership the editor already
     * uses for the preview iframe; `_draft` and `_t` are ignored when
     * validating the signature so the editor can append them client-side.
     */
    protected function applyDraftOverrides(Request $request, Link $link): void
    {
        if (!$request->boolean('_preview') || !$request->boolean('_draft')) {
            return;
        }
        if (!$request->hasValidSignatureWhileIgnoring(['_draft', '_t', '_sim_country', '_sim_device'], false)) {
            return;
        }
        $draft = \Illuminate\Support\Facades\Cache::get("biolink_draft:{$link->id}");
        if (!is_array($draft) || empty($draft['biolink']) || !is_array($draft['biolink'])) {
            return;
        }
        $settings = $link->settings ?? [];
        $settings['biolink'] = array_merge($settings['biolink'] ?? [], $draft['biolink']);
        $link->settings = $settings;
    }

    /**
     * Owner-scoped "simulate as" preview — when the editor iframe loads
     * with `?_preview=1` plus `_sim_country=US` and/or `_sim_device=mobile`
     * (signature must still be valid, those params are in the ignored
     * list), stash the simulated values on the request so
     * BiolinkBlock::detectCountry/detectDevice (and therefore
     * isVisible()) honor them. Lets creators preview their geo/device
     * targeting without spoofing headers or VPN-hopping.
     */
    protected function applyPreviewSimulation(Request $request): void
    {
        if (!$request->boolean('_preview')) return;
        if (!$request->hasValidSignatureWhileIgnoring(['_draft', '_t', '_sim_country', '_sim_device'], false)) {
            return;
        }
        $simCountry = $request->query('_sim_country');
        if (is_string($simCountry) && preg_match('/^[A-Za-z]{2}$/', $simCountry)) {
            $request->attributes->set('biolink_sim_country', strtoupper($simCountry));
        }
        $simDevice = $request->query('_sim_device');
        if (is_string($simDevice) && in_array($simDevice, ['mobile', 'tablet', 'desktop'], true)) {
            $request->attributes->set('biolink_sim_device', $simDevice);
        }
    }

    /**
     * Enforce a link's visibility tier (public/registered/followers/
     * subscribers). Returns a 401 gated response when the viewer doesn't
     * meet the tier, or null to allow the request to proceed.
     *
     * Applies to the whole biolink family AND to short-link / file / ics /
     * vcf links — the `links.visibility` column is the single source of
     * truth for all of them, so a creator can lock a short link or shared
     * file behind the same registered/followers/subscribers gate.
     *
     * Owners (the link's creator) always bypass the gate. Public visibility
     * is a no-op so this is cheap to call on every request.
     */
    protected function enforceVisibility(Request $request, Link $link)
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return null;

        // Only the biolink family and the redirect/download types carry a
        // meaningful visibility gate. Any other type stays public.
        if (!$link->isBiolinkFamily()
            && !in_array($link->type, ['url', 'file', 'ics', 'vcf', 'reviews', 'paid_page', 'brand_kit'], true)) {
            return null;
        }

        $viewerId = ViewerSession::id() ?: optional($request->user())->id;
        if ($viewerId && (int) $viewerId === (int) $link->user_id) {
            return null; // owner sees their own page in any tier
        }

        // Owner-scoped preview signal from the in-app editor iframe. The
        // editor signs the URL with `_preview=1` for the link owner; if the
        // signature is valid we trust it as proof of ownership for rendering.
        // This guarantees the preview is never blocked by visibility tiers
        // even if the iframe loses the session cookie (SameSite / 3rd-party
        // cookie behavior on a custom domain).
        if ($request->boolean('_preview') && $request->hasValidSignatureWhileIgnoring(['_draft', '_t', '_sim_country', '_sim_device'], false)) {
            return null;
        }

        $gatedRespond = function (string $reason) use ($link, $request) {
            $resp = response()->view('common.gated', ['link' => $link, 'reason' => $reason], 401);
            return $this->applyBiolinkFramingHeaders($resp, $request);
        };

        if ($vis === 'registered' && ! $viewerId) {
            return $gatedRespond('registered');
        }
        if ($vis === 'followers') {
            $following = $viewerId && Follow::where('follower_id', $viewerId)
                ->where('creator_id', $link->user_id)->exists();
            if (! $following) {
                return $gatedRespond('followers');
            }
        }
        if ($vis === 'subscribers') {
            $subscribed = $viewerId && Subscriber::where('user_id', $link->user_id)
                ->where('status', 'active')
                ->whereIn('email', function ($q) use ($viewerId) {
                    $q->select('email')->from('users')->where('id', $viewerId);
                })->exists();
            if (! $subscribed) {
                return $gatedRespond('subscribers');
            }
        }
        return null;
    }

    /**
     * Lazy refresh of cached social-account follower counts referenced by
     * a biolink view. Fires AFTER the response has been sent so it never
     * blocks the page render — counts always serve from the cached value.
     */
    protected function scheduleLazySocialRefresh(): void
    {
        app()->terminating(function () {
            if (! app()->bound('biolink.referenced_social_connections')) return;
            $ids = (array) app('biolink.referenced_social_connections');
            if (empty($ids)) return;

            try {
                $stale = \App\Modules\User\Models\SocialAccountConnection::whereIn('id', $ids)
                    ->where(function ($w) {
                        $w->whereNull('last_refreshed_at')
                          ->orWhere('last_refreshed_at', '<', now()->subHours(4));
                    })
                    ->limit(10) // safety cap
                    ->get();
                if ($stale->isEmpty()) return;

                $registry = app(\App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry::class);
                foreach ($stale as $conn) {
                    $registry->refresh($conn);
                }
            } catch (\Throwable $e) {
                \Log::warning('Lazy social refresh failed', ['err' => $e->getMessage()]);
            }
        });
    }

    protected function handleFileDownload(Link $link)
    {
        $fileLink = $link->fileLink;
        if (!$fileLink) abort(404);

        if (!$fileLink->show_download_page) {
            $disk = $fileLink->disk ?: 'public';
            if (!Storage::disk($disk)->exists($fileLink->stored_path)) {
                abort(404, 'File not found.');
            }
            $fileLink->increment('download_count');
            return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
        }

        return view('common.file-download', compact('link', 'fileLink'));
    }

    public function manifest(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || !$link->isBiolinkFamily()) abort(404);

        if (!$link->isAccessible()) {
            abort(404);
        }

        $bs = $link->settings['biolink'] ?? [];
        if (empty($bs['manifest']['enabled'])) {
            abort(404);
        }
        $m = $bs['manifest'] ?? [];
        $favicons = $bs['favicons'] ?? [];

        $icons = [];
        if ($link->favicon) {
            $icons[] = ['src' => \App\Support\PublicStorageUrl::resolve($link->favicon), 'sizes' => '64x64', 'type' => 'image/png'];
        }
        if (!empty($favicons['apple_touch_icon'])) {
            $icons[] = ['src' => $favicons['apple_touch_icon'], 'sizes' => '180x180', 'type' => 'image/png'];
        }
        if (!empty($favicons['icon_512'])) {
            $icons[] = ['src' => $favicons['icon_512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'];
        }

        $startUrl = !empty($m['start_url']) ? $m['start_url'] : url('/' . $link->alias);
        $categories = !empty($m['categories']) ? array_map('trim', explode(',', $m['categories'])) : [];

        $manifest = [
            'name' => $m['name'] ?? $link->title ?? 'Sayzio Bio',
            'short_name' => $m['short_name'] ?? \Illuminate\Support\Str::limit($link->title ?? 'Bio', 12, ''),
            'description' => $m['description'] ?? '',
            'start_url' => $startUrl,
            'display' => $m['display'] ?? 'standalone',
            'orientation' => $m['orientation'] ?? 'any',
            'theme_color' => $m['theme_color'] ?? '#7c3aed',
            'background_color' => $m['background_color'] ?? '#0a0612',
            'icons' => $icons,
        ];

        if (!empty($categories)) {
            $manifest['categories'] = $categories;
        }

        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    public function rawFileDownload(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'file') abort(404);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        // Moderation gate — mirrors handle(). A file hidden by an admin is
        // inaccessible to non-owners through the direct download path too.
        if ($link->moderation_state === 'hidden') {
            $viewerId = optional($request->user())->id
                ?? \App\Modules\Common\Services\ViewerSession::id();
            if ((int) $viewerId !== (int) $link->user_id) {
                return response()->view('common.link-moderated', ['link' => $link], 451);
            }
        }

        // Visibility gate — mirrors handle(). A file restricted to
        // registered/followers/subscribers cannot be bypassed by appending
        // /download to the alias.
        if ($gated = $this->enforceVisibility($request, $link)) {
            return $gated;
        }

        $settings = $link->settings ?? [];
        if (!empty($settings['country_restrictions'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            $allowedCountries = array_map('strtoupper', $settings['country_restrictions']);
            if ($visitorCountry === null) {
                abort(403, 'This link is restricted by region and your location could not be determined.');
            }
            if (!in_array(strtoupper($visitorCountry), $allowedCountries)) {
                return response()->view('common.link-expired', ['link' => $link, 'reason' => 'banned_country'], 403);
            }
        }

        // Banned-locations blocklist (separate from the allowlist above so
        // owners can either allow-only-these or block-just-these).
        if (!empty($settings['country_blocklist'])) {
            $visitorCountry = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($request->ip());
            if ($link->isCountryBlocked($visitorCountry)) {
                return response()->view('common.link-expired', ['link' => $link, 'reason' => 'banned_country'], 403);
            }
        }

        if (!empty($settings['device_targeting'])) {
            $ua = $request->userAgent() ?? '';
            $deviceType = 'desktop';
            if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) {
                $deviceType = 'mobile';
            } elseif (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) {
                $deviceType = 'tablet';
            }
            if (!in_array($deviceType, array_map('strtolower', $settings['device_targeting']))) {
                abort(403, 'This link is not available on your device.');
            }
        }

        if ($link->is_password_protected && !session("link_unlocked_{$link->id}")) {
            abort(403, 'This link is password protected.');
        }

        $fileLink = $link->fileLink;
        if (!$fileLink) abort(404);

        $mode = $request->query('mode', 'download');
        $disk = $fileLink->disk ?? 'public';

        if (!Storage::disk($disk)->exists($fileLink->stored_path)) {
            abort(404, 'File not found.');
        }

        if ($mode === 'preview') {
            $mimeType = $fileLink->mime_type ?: 'application/octet-stream';
            $allowedPreviewMimes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                'application/pdf',
            ];
            if (!in_array($mimeType, $allowedPreviewMimes)) {
                return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
            }
            return Storage::disk($disk)->response($fileLink->stored_path, $fileLink->original_name, [
                'Content-Type' => $mimeType,
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'none'; script-src 'none'",
            ]);
        }

        $fileLink->increment('download_count');
        return Storage::disk($disk)->download($fileLink->stored_path, $fileLink->original_name);
    }

    public function handleBlockClick(Request $request, string $alias, int $blockId)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || !$link->isBiolinkFamily()) abort(404);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        // Same visibility enforcement as the biolink page itself, so private
        // tiers cannot be bypassed by deep-linking directly to a block click URL.
        if ($gated = $this->enforceVisibility($request, $link)) {
            return $gated;
        }

        // A/B test bookkeeping: figure out which variant this visitor was
        // assigned (if any) so we can attribute the click + fall back to
        // the variant snapshot when the live row is gone (typical for
        // Variant A blocks once the creator starts editing live).
        $abService = app(BiolinkExperimentService::class);
        $abExp = $abService->activeFor($link);
        $abVariant = $abExp ? $abService->assignVariant($request, $abExp) : null;

        $block = BiolinkBlock::where('id', $blockId)->where('link_id', $link->id)->first();
        if (!$block && $abExp && $abVariant) {
            $block = $abService->findSnapshotBlock($abExp, $blockId, $abVariant);
        }
        if (!$block) abort(404);

        // Task #1094 — refuse the click-through once the block has hit its
        // time-based expiry or click-count cap. Without this, late-arriving
        // taps from stale tabs (or anyone holding the redirect URL) would
        // continue to consume + offload clicks beyond the cap. We honor
        // expired_action so a "show" configuration still lands on a
        // friendly explainer page instead of a hard 410.
        if ($block->isExpired()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        $s = $block->settings ?? [];
        $linkData = $s['_link'] ?? [];

        $overrideUrl = $request->query('to');
        if ($overrideUrl) {
            $parsed = parse_url($overrideUrl);
            $destinationUrl = (isset($parsed['scheme']) && in_array($parsed['scheme'], ['http', 'https'])) ? $overrideUrl : '#';
        } else {
            $destinationUrl = $linkData['url'] ?? $s['link'] ?? $s['url'] ?? '#';
        }

        if ($destinationUrl === '#' || empty($destinationUrl)) {
            abort(404, 'No destination URL configured.');
        }

        // Stash the alias actually used so the Auto-UTM token resolver can
        // emit the matching `utm_campaign` value when multiple aliases
        // point at the same biolink page.
        $link->setAttribute('_used_alias', $alias);
        $destinationUrl = app(AutoUtmBuilder::class)->build($destinationUrl, $link, $block);

        // Honor an explicit ?source=… tag (e.g. "ar" from the AR Business Card
        // renderer) so block-click rows are attributed to the surface that
        // sent the visitor, falling back to "web" for normal biolink taps.
        $rawSource = (string) $request->query('source', '');
        $sourceTag = preg_match('/^[a-z0-9_-]{1,32}$/', $rawSource) ? $rawSource : 'web';

        // trackBlockClick now returns null when the block has hit its
        // cap or end_date — that's the authoritative concurrent gate
        // (a single conditional UPDATE inside the service). The
        // pre-check above is just a fast path; this covers the race
        // between two simultaneous clicks at click_count = cap - 1.
        $tracked = $this->trackingService->trackBlockClick($link, $block, $destinationUrl, $request, $alias, $sourceTag);
        if ($tracked === null && !app(\App\Modules\Common\Services\BotDetector::class)->isBot($request->userAgent() ?? '')) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        if ($abExp && $abVariant) {
            $abService->recordClick($abExp, $abVariant);
        }

        return redirect()->away($destinationUrl, 302);
    }

    /**
     * Resolve the visitor's assigned A/B variant for this biolink and
     * stash both the active experiment and the variant-specific block
     * tree on the link instance so the blade renderer can pick them up
     * without re-querying. No-op when no experiment is running.
     */
    protected function applyBiolinkAbExperiment(Link $link): void
    {
        $service = app(BiolinkExperimentService::class);
        $exp = $service->activeFor($link);
        if (!$exp) return;

        $variant = $service->assignVariant(request(), $exp);
        $blocks  = $service->renderableBlocks($exp, $variant);

        $link->setAttribute('_abActiveExperiment', $exp);
        $link->setAttribute('_abAssignedVariant', $variant);
        $link->setAttribute('_abVariantBlocks', $blocks);
    }

    /**
     * Public event page for `ics` links with ticketing enabled (Task
     * #3589). Shows tiers + a buy/RSVP form instead of the plain .ics
     * download — the raw calendar file is still reachable via the ?ics=1
     * query so calendar apps / "Add to calendar" links keep working.
     */
    protected function handleEventTicketingPage(Request $request, Link $link)
    {
        if ($request->boolean('ics')) {
            return $this->handleIcsDownload($link);
        }

        $link->load(['icsData', 'user']);
        $tiers = $link->eventTicketTiers()->where('is_active', true)->get();
        $rsvpAvailable = self::isRsvpAvailable($link, $tiers);
        $extras = $this->eventPageExtras($link);

        return response()->view('common.event-page', array_merge(compact('link', 'tiers', 'rsvpAvailable'), $extras));
    }

    /**
     * Task #3674: free (non-ticketed) events accept RSVPs by default —
     * organizers no longer have to flip a separate "enable RSVP" switch.
     * Paid-ticket events keep their existing buy flow untouched, and an
     * explicit `rsvp_disabled` opt-out (if an organizer ever sets one) is
     * still honored.
     */
    public static function isRsvpAvailable(Link $link, ?\Illuminate\Support\Collection $tiers = null): bool
    {
        $s = (array) ($link->settings ?? []);
        if (!empty($s['ticketing_enabled'])) return false;

        $tiers ??= $link->eventTicketTiers()->where('is_active', true)->get();
        if ($tiers->isNotEmpty()) return false;

        return empty($s['rsvp_disabled']);
    }

    /**
     * The organizer's other public events (upcoming first, backfilled with
     * recent past events so the section isn't empty once everything else has
     * happened). Shared by the web event pages and the mobile API shape so
     * both surfaces list the same events regardless of whether the host has
     * a public handle.
     */
    public static function sameHostEvents(Link $link, int $limit = 4): \Illuminate\Support\Collection
    {
        $sameHostBase = fn () => Link::where('type', 'ics')
            ->where('id', '!=', $link->id)
            ->where('user_id', $link->user_id)
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->with(['icsData', 'eventTicketTiers' => fn ($q) => $q->where('is_active', true)]);

        $sameHostEvents = $sameHostBase()
            ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()->subDay()))
            ->get()
            ->sortBy(fn ($l) => $l->icsData?->start_date)
            ->values();

        if ($sameHostEvents->count() < $limit) {
            $excludeIds = $sameHostEvents->pluck('id')->push($link->id)->all();
            $pastEvents = $sameHostBase()
                ->whereNotIn('id', $excludeIds)
                ->get()
                ->sortByDesc(fn ($l) => $l->icsData?->start_date)
                ->values()
                ->take($limit - $sameHostEvents->count());
            $sameHostEvents = $sameHostEvents->concat($pastEvents);
        }

        return $sameHostEvents->take($limit)->values();
    }

    /**
     * Shared recommendation + Interested-count data for the two public
     * event surfaces (RSVP page and ticketed event page) — Task #3593.
     * Similar events match on shared hashtags; same-host events list the
     * organizer's other upcoming public events. Both exclude the current
     * event and cap at 4 results.
     *
     * Task #3769: the recommendation lookups (a `LIKE`-on-settings scan plus
     * several joined aggregate queries) are optional "nice to have" widgets,
     * not core to the RSVP/event page, and they must NEVER be able to delay
     * or blank the page — including on a cold cache, where a synchronous
     * compute-then-cache would still make the *first* request in every TTL
     * window pay the full slow-query cost inline. So this method never
     * computes them itself: it only ever does a cheap cache read. On a hit
     * it returns the cached recommendations; on a miss it returns them empty
     * immediately (`extrasPending = true`) and the view lazy-fetches the
     * real data client-side, off the render path, via
     * eventPageExtrasFragment()/GET /{alias}/event-extras below (which does
     * the actual lock-guarded, failure-safe computation + caching).
     *
     * Interest counts are a single cheap COUNT per status on this one link,
     * so they're computed inline here rather than deferred.
     */
    protected function eventPageExtras(Link $link): array
    {
        $default = [
            'similarEvents' => collect(),
            'sameHostEvents' => collect(),
            'interestCounts' => ['interested' => 0, 'not_interested' => 0],
            'extrasPending' => false,
        ];

        try {
            $default['interestCounts'] = [
                'interested'     => $link->eventInterests()->where('status', \App\Modules\User\Models\EventInterest::INTERESTED)->count(),
                'not_interested' => $link->eventInterests()->where('status', \App\Modules\User\Models\EventInterest::NOT_INTERESTED)->count(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Event interest counts failed; degrading gracefully', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $ids = Cache::get("event_page_extras_ids:{$link->id}");
            if ($ids !== null) {
                return [
                    'similarEvents' => $this->hydrateLinksPreservingOrder($ids['similar_ids'] ?? []),
                    'sameHostEvents' => $this->hydrateLinksPreservingOrder($ids['same_host_ids'] ?? []),
                    'interestCounts' => $default['interestCounts'],
                    'extrasPending' => false,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Event page recommendation cache read failed; degrading gracefully', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Cache miss (or cache read failure): render instantly without the
        // recommendation widgets. The view will fetch them asynchronously.
        $default['extrasPending'] = true;
        return $default;
    }

    /**
     * Off-render-path counterpart to eventPageExtras(): actually computes
     * (and caches, 60s TTL) the similar/same-host recommendation ids, then
     * returns just the recommendations HTML fragment. Called client-side by
     * event-page-recommendations.blade.php AFTER the core page has already
     * rendered, so this is the only place the slow queries can ever run —
     * never inline on the page request itself. Still lock-guarded (10s) so
     * concurrent lazy-fetches for the same event don't pile onto the same
     * slow queries; a request that loses the lock race just returns
     * whatever is (or isn't yet) cached rather than waiting.
     */
    public function eventPageExtrasFragment(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);

        $result = ['similarEvents' => collect(), 'sameHostEvents' => collect()];

        try {
            $cacheKey = "event_page_extras_ids:{$link->id}";
            $ids = Cache::get($cacheKey);

            if ($ids === null) {
                $lock = Cache::lock($cacheKey . ':lock', 10);
                if ($lock->get()) {
                    try {
                        $ids = $this->computeEventPageExtraIds($link);
                        Cache::put($cacheKey, $ids, 60);
                    } finally {
                        $lock->release();
                    }
                } else {
                    // Another request is already computing this — don't
                    // pile onto the same slow queries; the next lazy-fetch
                    // (or page load) will find it cached.
                    $ids = null;
                }
            }

            if ($ids !== null) {
                $result = [
                    'similarEvents' => $this->hydrateLinksPreservingOrder($ids['similar_ids'] ?? []),
                    'sameHostEvents' => $this->hydrateLinksPreservingOrder($ids['same_host_ids'] ?? []),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Event page recommendation extras failed; degrading gracefully', [
                'link_id' => $link->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->view('common.partials.event-page-recommendations', array_merge(['link' => $link], $result));
    }

    /**
     * Re-fetch links by id, preserving the given order. Only plain scalar
     * ids are cached (never Eloquent collections/models) — a known footgun
     * with the file cache driver under multiple workers is that cached
     * Eloquent structures can deserialize as `__PHP_Incomplete_Class`.
     */
    protected function hydrateLinksPreservingOrder(array $ids): \Illuminate\Support\Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $links = Link::whereIn('id', $ids)->where('is_active', true)->with('icsData')->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $links->get($id))->filter()->values();
    }

    /**
     * Computes the raw ids/counts behind eventPageExtras(). Kept separate so
     * the cache/lock/fallback wrapper above only ever caches plain arrays.
     */
    protected function computeEventPageExtraIds(Link $link): array
    {
        $ics = $link->icsData;
        $hashtags = $ics ? $ics->hashtagList() : [];

        $similarEvents = collect();
        if (!empty($hashtags)) {
            $likes = array_map(fn ($h) => '%"' . $h . '"%', $hashtags);
            $similarEvents = Link::where('type', 'ics')
                ->where('id', '!=', $link->id)
                ->where('is_active', true)
                ->where('visibility', 'public')
                ->with(['icsData', 'eventTicketTiers' => fn ($q) => $q->where('is_active', true)])
                ->whereHas('icsData', function ($w) use ($likes) {
                    $w->where(function ($or) use ($likes) {
                        foreach ($likes as $like) {
                            $or->orWhereRaw('hashtags::text ilike ?', [$like]);
                        }
                    })->where('start_date', '>=', now()->subDay());
                })
                ->limit(4)
                ->get();
        }

        // Fall back to same-category or same-location events when no hashtag
        // overlap was found (or the event has no hashtags at all) so a
        // "Similar events" section still has something useful to show.
        if ($similarEvents->isEmpty()) {
            $category = ($link->settings ?? [])['event_category'] ?? null;
            $location = $ics->location ?? null;
            if ($category || $location) {
                $similarEvents = Link::where('type', 'ics')
                    ->where('id', '!=', $link->id)
                    ->where('is_active', true)
                    ->where('visibility', 'public')
                    ->with(['icsData', 'eventTicketTiers' => fn ($q) => $q->where('is_active', true)])
                    ->where(function ($or) use ($category, $location) {
                        if ($category) $or->orWhereRaw("settings->>'event_category' = ?", [$category]);
                        if ($location) $or->orWhereHas('icsData', fn ($w) => $w->where('location', $location));
                    })
                    ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()->subDay()))
                    ->limit(4)
                    ->get();
            }
        }

        // Organizer's other events must show up regardless of whether the
        // host has a public handle — see sameHostEvents() for the
        // upcoming-first, past-event-backfill logic (shared with mobile).
        $sameHostEvents = self::sameHostEvents($link);

        $interestCounts = [
            'interested'     => $link->eventInterests()->where('status', \App\Modules\User\Models\EventInterest::INTERESTED)->count(),
            'not_interested' => $link->eventInterests()->where('status', \App\Modules\User\Models\EventInterest::NOT_INTERESTED)->count(),
        ];

        return [
            'similar_ids' => $similarEvents->pluck('id')->all(),
            'same_host_ids' => $sameHostEvents->pluck('id')->all(),
            'interest_counts' => $interestCounts,
        ];
    }

    protected function handleIcsDownload(Link $link)
    {
        $icsData = $link->icsData;
        if (!$icsData) abort(404);

        $content = $icsData->toIcs();
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $icsData->event_name) . '.ics';

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function handleVcfDownload(Link $link)
    {
        $vcfData = $link->vcfData;
        if (!$vcfData) abort(404);

        $content = $vcfData->toVcf();
        $filename = trim($vcfData->first_name . '_' . $vcfData->last_name, '_ ') . '.vcf';
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        return response($content, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Render the public, standalone "Reviews" page — a full review wall with
     * a rating summary, native + 3rd-party reviews, and a no-login submission
     * form. Honours the same permissive framing headers as biolink pages so
     * the in-app editor preview iframe can render it.
     */
    protected function handleReviewsPage(Request $request, Link $link)
    {
        $settings = $link->settings['reviews'] ?? [];
        $source   = $settings['source'] ?? 'both';
        $sort     = $settings['sort'] ?? 'recent';

        $summary = app(\App\Modules\User\Support\ReviewSummaryService::class)
            ->summary((int) $link->user_id, (int) $link->id, $source);

        $items = \App\Modules\User\Support\ReviewFeed::build(
            (int) $link->user_id,
            (int) $link->id,
            $source,
            $sort,
            (int) ($settings['limit'] ?? 24),
            (array) ($settings['providers'] ?? [])
        );

        $questions = \App\Modules\User\Models\ReviewQuestion::query()
            ->where('user_id', $link->user_id)
            ->active()
            ->where(fn ($q) => $q->whereNull('link_id')->orWhere('link_id', $link->id))
            ->orderBy('sort_order')
            ->get();

        return $this->applyBiolinkFramingHeaders(
            response()->view('common.reviews-page', compact('link', 'settings', 'summary', 'items', 'questions')),
            $request
        );
    }

    /**
     * Resume / Portfolio link. Bridges the unified link to the standalone
     * resume builder: resolves the associated resume version (falling back
     * to the owner's default), then delegates to the existing public resume
     * renderer so all visibility / password / expiry gates, the view
     * counter and the PDF-download affordance are reused verbatim. Link-
     * level gates have already run in handle() before this match.
     */
    protected function handleResumePage(Request $request, Link $link)
    {
        $resume = $link->resume_id
            ? \App\Modules\User\Models\Resume::find($link->resume_id)
            : null;

        $user = $resume?->user ?: \App\Modules\User\Models\User::find($link->user_id);
        abort_unless($user, 404);

        // Fall back to the owner's default resume when the link never
        // captured a specific version (or the version was deleted).
        if (!$resume) {
            $resume = $user->resumes()->where('is_default', true)->first()
                ?? $user->resumes()->first();
        }
        abort_unless($resume, 404);

        $slug = $resume->is_default ? null : $resume->effectiveSlug();

        return app(PublicResumeController::class)
            ->show($request, $user->publicHandle(), $slug);
    }

    /**
     * Paid Page link. Repackages the creator's monetized feed (posts /
     * tiers / PPV / tipping) as a themeable, shareable page. Reuses the
     * exact per-creator feed data + paywall stack that powers /@handle
     * (via CreatorProfilePublicController::buildFeedViewData) and the same
     * creator-post-card partial — so reactions, comments, unlock and
     * subscribe all flow through the existing handle-based routes. The
     * page-level public/gated toggle is enforced by enforceVisibility()
     * (links.visibility) before this runs; here we only apply the chosen
     * design template and the 18+ / region gates that are content-based.
     */
    protected function handlePaidPage(Request $request, Link $link)
    {
        $creator = \App\Modules\User\Models\User::find($link->user_id);
        abort_unless($creator, 404);

        $viewer  = \App\Modules\Common\Services\ViewerSession::user() ?? auth()->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;

        // 18+ age gate (content-based — applies even when the link itself is
        // publicly visible). Owners and viewers who have affirmed 18+ bypass.
        if (!$isOwner && $creator->isAdultProfile()
            && !\App\Modules\Common\Services\AgeGate::passed($request, $viewer)) {
            return $this->applyBiolinkFramingHeaders(
                response()->view('public.age-gate', ['creator' => $creator]),
                $request
            );
        }

        // Region gate (profile-level country lists). Owners bypass.
        if (!$isOwner) {
            $decision = app(\App\Modules\Common\Services\CountryGate::class)
                ->decide($creator, null, $request->ip());
            if (empty($decision['allowed'])) {
                return response()->view('public.region-blocked', [
                    'creator' => $creator,
                    'reason'  => $decision['reason'] ?? 'The creator has restricted this content in your region.',
                ], 451);
            }
        }

        $feed = app(CreatorProfilePublicController::class)
            ->buildFeedViewData($creator, $viewer, (bool) $isOwner);

        $paidSettings = $link->settings['paid_page'] ?? [];
        $template = \App\Modules\User\Support\PaidPageTemplates::applyCustomBackground(
            \App\Modules\User\Support\PaidPageTemplates::get($paidSettings['template'] ?? null),
            $paidSettings
        );

        return $this->applyBiolinkFramingHeaders(
            response()->view('public.paid-page', array_merge($feed, [
                'link'     => $link,
                'creator'  => $creator,
                'viewer'   => $viewer,
                'isOwner'  => $isOwner,
                'template' => $template,
            ])),
            $request
        );
    }

    /**
     * Render the standalone Brand / Press Kit page. The per-link config in
     * settings['brand_kit'] (seeded from the owner's saved AI Brand Kit) is
     * normalised then handed to a dedicated public view. Page-level visibility
     * (public vs registered-gated) has already been enforced by
     * enforceVisibility() in handle() before this match.
     */
    protected function handleBrandKitPage(Request $request, Link $link)
    {
        $creator = \App\Modules\User\Models\User::find($link->user_id);
        abort_unless($creator, 404);

        $config = \App\Modules\User\Support\BrandKitPageTemplates::normalize(
            is_array($link->settings['brand_kit'] ?? null) ? $link->settings['brand_kit'] : []
        );
        $template = \App\Modules\User\Support\BrandKitPageTemplates::get($config['template'] ?? null);

        return $this->applyBiolinkFramingHeaders(
            response()->view('public.brand-kit', [
                'link'     => $link,
                'creator'  => $creator,
                'config'   => $config,
                'template' => $template,
            ]),
            $request
        );
    }

    /**
     * Public, standalone followable Calendar page. Renders the calendar's
     * events (with optional search / hashtag / time-range filters), a follow
     * affordance, and ICS / Google subscribe links. Honours the same
     * permissive framing headers as biolink pages so the in-app editor
     * preview iframe can render it. Link-level visibility/password/expiry
     * gates already ran in handle() before this match.
     */
    protected function handleCalendarPage(Request $request, Link $link)
    {
        $calendar = $link->calendar
            ?: \App\Modules\User\Models\Calendar::where('link_id', $link->id)->first();
        abort_unless($calendar, 404);

        $viewer  = \App\Modules\Common\Services\ViewerSession::user() ?? $request->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $calendar->user_id;
        $isFollowing = $viewer ? $calendar->isFollowedBy($viewer) : false;

        $query = $calendar->events();

        // Default to upcoming; allow ?past=1 to include history.
        if (!$request->boolean('past')) {
            $query->where(function ($q) {
                $q->where('start_at', '>=', now()->startOfDay())
                  ->orWhere('end_at', '>=', now());
            });
        }

        if ($tag = $request->query('tag')) {
            $tag = \App\Modules\User\Models\CalendarEvent::normalizeHashtags($tag)[0] ?? null;
            if ($tag) {
                $query->whereJsonContains('hashtags', $tag);
            }
        }

        // Explicit date-range filter (overrides the upcoming-only default above).
        $from = trim((string) $request->query('from', ''));
        $to   = trim((string) $request->query('to', ''));
        if ($from !== '') {
            try { $query->where('start_at', '>=', \Illuminate\Support\Carbon::parse($from)->startOfDay()); } catch (\Throwable $e) { $from = ''; }
        }
        if ($to !== '') {
            try { $query->where('start_at', '<=', \Illuminate\Support\Carbon::parse($to)->endOfDay()); } catch (\Throwable $e) { $to = ''; }
        }

        // Location filter (matches against the event location text).
        $location = trim((string) $request->query('location', ''));
        if ($location !== '') {
            $locLike = '%' . str_replace(['%', '_'], ['\%', '\_'], $location) . '%';
            $query->where('location', 'ilike', $locLike);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'ilike', $like)
                  ->orWhere('description', 'ilike', $like)
                  ->orWhere('location', 'ilike', $like);
            });
        }

        $events = $query->orderBy('start_at')->orderBy('id')->get();

        // Distinct hashtag chips for the filter bar (from upcoming+all events).
        $allTags = $calendar->events()
            ->whereNotNull('hashtags')
            ->pluck('hashtags')
            ->flatMap(fn ($t) => is_array($t) ? $t : [])
            ->unique()
            ->sort()
            ->values()
            ->all();

        $icsUrl = route('public.calendars.ics', $calendar->id);
        // Google Calendar "subscribe from URL" deep link (https → cid).
        $googleUrl = 'https://calendar.google.com/calendar/r?cid=' . urlencode($icsUrl);

        return $this->applyBiolinkFramingHeaders(
            response()->view('common.calendar-page', [
                'link'        => $link,
                'calendar'    => $calendar,
                'events'      => $events,
                'allTags'     => $allTags,
                'viewer'      => $viewer,
                'isOwner'     => $isOwner,
                'isFollowing' => $isFollowing,
                'icsUrl'      => $icsUrl,
                'googleUrl'   => $googleUrl,
                'filters'     => [
                    'q'        => $search ?? '',
                    'tag'      => $tag ?? '',
                    'past'     => $request->boolean('past'),
                    'from'     => $from ?? '',
                    'to'       => $to ?? '',
                    'location' => $location ?? '',
                ],
            ]),
            $request
        );
    }

    /**
     * Public Updates / Changelog page renderer.
     *
     * Fetches published entries newest-first, paginates them at the per-page
     * setting set in the editor, and renders the standalone public page view.
     * Link-level gates (password / expiry / visibility) already ran in handle()
     * before this match arm is reached.
     */
    protected function handleUpdatesPage(Request $request, Link $link)
    {
        $settings = array_merge(
            \App\Modules\User\Controllers\UpdatesController::DEFAULT_SETTINGS,
            $link->settings['updates'] ?? []
        );

        $perPage = max(1, min(100, (int) ($settings['per_page'] ?? 10)));

        $entries = \App\Modules\User\Models\UpdateEntry::where('link_id', $link->id)
            ->published()
            ->orderByDesc('published_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $creator = \App\Modules\User\Models\User::find($link->user_id);

        $pageTitle = ($link->title ?: $settings['heading'])
            . ($creator ? ' — ' . $creator->name : '');

        $themeClass = 'dark';

        return response()->view('common.updates-page', compact(
            'link', 'settings', 'entries', 'creator', 'pageTitle', 'themeClass'
        ));
    }

    public function subscribe(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);

        // A/B conversion attribution. The actual increment fires only on
        // a successful submission below; we resolve here once so the
        // helper stays cheap (it short-circuits when no experiment is
        // active for this biolink).
        [$abExp, $abVariant] = app(BiolinkExperimentService::class)
            ->resolveAssignment($request, $link);
        $recordAbConversion = function () use ($abExp, $abVariant) {
            if ($abExp && $abVariant) {
                app(BiolinkExperimentService::class)->recordConversion($abExp, $abVariant);
            }
        };

        $data = $request->validate([
            'block_id' => 'required|integer',
            'type' => 'required|in:email,whatsapp_channel,whatsapp_number,contact_form',
            'email' => 'nullable|email|max:200',
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'channel_url' => 'nullable|url|max:500',
            'message' => 'nullable|string|max:5000',
        ]);

        $block = BiolinkBlock::where('id', $data['block_id'])->where('link_id', $link->id)->first();
        if (!$block) {
            return response()->json(['success' => false, 'message' => 'Invalid block.'], 400);
        }

        $typeMap = [
            'email_subscribe' => 'email',
            'whatsapp_channel_subscribe' => 'whatsapp_channel',
            'whatsapp_number_subscribe' => 'whatsapp_number',
            'contact_form' => 'contact_form',
        ];
        if (($typeMap[$block->type] ?? null) !== $data['type']) {
            return response()->json(['success' => false, 'message' => 'Invalid subscription type.'], 400);
        }

        // Contact-form messages are inserted as fresh rows (no dedupe — the
        // same visitor can send many messages). Honeypot + heuristics still
        // apply, with the message body included in the spam scan text.
        if ($data['type'] === 'contact_form') {
            if (empty($data['email']) || empty($data['message'])) {
                return response()->json(['success' => false, 'message' => 'Email and message are required.'], 422);
            }

            $spamCheck = app(SpamChecker::class)->check([
                'honeypot' => $request->input('_hp'),
                'ip'       => $request->ip(),
                'text'     => trim(implode(' ', array_filter([
                    $data['name'] ?? null, $data['email'] ?? null, $data['message'] ?? null,
                ]))),
                'scope'    => 'contact_form:' . $link->id,
            ]);

            $subscriber = Subscriber::create([
                'user_id'       => $link->user_id,
                'link_id'       => $link->id,
                'block_id'      => $block->id,
                'type'          => 'contact_form',
                'email'         => $data['email'],
                'name'          => $data['name'] ?? null,
                'status'        => 'active',
                'source'        => $alias,
                'metadata'      => ['message' => $data['message']],
                'subscribed_at' => now(),
                'is_spam'       => $spamCheck['is_spam'],
            ]);

            if (! $spamCheck['is_spam']) {
                try {
                    $subscriber->setRelation('block', $block);
                    $subscriber->setRelation('link', $link);
                    app(\App\Modules\User\Services\InboxForwarder::class)
                        ->dispatchForSubscriber($link->user_id, $subscriber);
                } catch (\Throwable $e) {
                    logger()->warning('Inbox forwarder (contact_form) failed: ' . $e->getMessage());
                }
            }

            $recordAbConversion();
            return response()->json(['success' => true, 'message' => 'Message sent — thanks for getting in touch!']);
        }

        if ($data['type'] === 'email') {
            if (empty($data['email'])) {
                return response()->json(['success' => false, 'message' => 'Email is required.'], 422);
            }
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'email')
                ->where('email', $data['email'])
                ->first();
        } elseif ($data['type'] === 'whatsapp_number') {
            $phone = preg_replace('/[^0-9+]/', '', $data['phone'] ?? '');
            if (empty($phone)) {
                $phone = 'anon_' . substr(md5($request->ip() . $block->id), 0, 12);
            }
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'whatsapp_number')
                ->where('phone', $phone)
                ->first();
            $data['phone'] = $phone;
        } else {
            $fingerprint = substr(md5($request->ip() . $request->userAgent()), 0, 16);
            $existing = Subscriber::where('user_id', $link->user_id)
                ->where('type', 'whatsapp_channel')
                ->where('block_id', $block->id)
                ->where('source', $fingerprint)
                ->first();
            $data['_fingerprint'] = $fingerprint;
        }

        if ($existing) {
            if ($existing->status === 'unsubscribed') {
                $existing->update(['status' => 'active', 'unsubscribed_at' => null]);
                return response()->json(['success' => true, 'message' => 'Re-subscribed successfully!']);
            }
            return response()->json(['success' => true, 'message' => 'Already subscribed.']);
        }

        // Spam heuristics — bots autofill the honeypot, scatter links into the
        // name field, or hammer the endpoint from one IP. Flagged captures are
        // still stored (creators can review the Spam tab) but hidden from the
        // default inbox view and excluded from unread badges.
        $spamCheck = app(SpamChecker::class)->check([
            'honeypot' => $request->input('_hp'),
            'ip'       => $request->ip(),
            'text'     => trim(implode(' ', array_filter([
                $data['name'] ?? null, $data['email'] ?? null,
                $data['phone'] ?? null, $data['channel_url'] ?? null,
            ]))),
            'scope'    => 'subscribe:' . $link->id,
            'user_id'  => $link->user_id,
            'email'    => $data['email'] ?? null,
            'phone'    => $data['phone'] ?? null,
        ]);

        // Stamp the visitor's self-identified persona (set by the audience-prompt
        // Alpine component before they subscribed) onto the subscriber row so that
        // the Subscribers list and CSV export can be filtered/segmented by persona.
        $visitorType = null;
        $apCookieRaw = $request->cookie('ap_type_' . $link->id);
        if ($apCookieRaw && preg_match('/^(student|professional|business|creator|other)$/', $apCookieRaw)) {
            $visitorType = $apCookieRaw;
        }

        $subscriber = Subscriber::create([
            'user_id'      => $link->user_id,
            'link_id'      => $link->id,
            'block_id'     => $block->id,
            'type'         => $data['type'],
            'email'        => $data['email'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'name'         => $data['name'] ?? null,
            'channel_url'  => $data['channel_url'] ?? null,
            'status'       => 'active',
            'source'       => $data['type'] === 'whatsapp_channel' ? ($data['_fingerprint'] ?? $alias) : $alias,
            'subscribed_at' => now(),
            'is_spam'      => $spamCheck['is_spam'],
            'spam_reason'  => $spamCheck['is_spam'] ? $spamCheck['reason'] : null,
            'visitor_type' => $visitorType,
        ]);

        // Account-level forwarding rules — fan out to the owner's email/webhook
        // destinations whose source filter matches this subscriber's source.
        if (! $spamCheck['is_spam']) {
            try {
                $subscriber->setRelation('block', $block);
                $subscriber->setRelation('link', $link);
                app(\App\Modules\User\Services\InboxForwarder::class)
                    ->dispatchForSubscriber($link->user_id, $subscriber);
            } catch (\Throwable $e) {
                logger()->warning('Inbox forwarder (subscriber) failed: ' . $e->getMessage());
            }
        }

        $recordAbConversion();
        return response()->json(['success' => true, 'message' => 'Subscribed successfully!']);
    }

    public function rsvpForm(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);
        if (!self::isRsvpAvailable($link)) abort(404);
        $link->load(['icsData', 'user']);
        $submitted = (bool) $request->session()->get('rsvp_submitted_' . $link->id);
        $extras = $this->eventPageExtras($link);
        return view('common.rsvp-form', array_merge(compact('link', 'submitted'), $extras));
    }

    public function rsvpSubmit(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);
        $s = (array) ($link->settings ?? []);
        if (!self::isRsvpAvailable($link)) abort(404);

        // Badge-gated events (Task #3593): RSVPing requires the signed-in
        // account to already hold the event's `required_badge_id`. Guests
        // (not signed in) can never satisfy a badge requirement.
        $link->loadMissing('icsData');
        $requiredBadgeId = $link->icsData?->required_badge_id;
        if ($requiredBadgeId) {
            $user = $request->user();
            $hasBadge = $user && $user->accountBadges()->where('account_badges.id', $requiredBadgeId)->exists();
            if (!$hasBadge) {
                $message = 'This event requires an invite badge you don\'t have yet.';
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 403);
                }
                return back()->withErrors(['response' => $message]);
            }
        }

        $allowPlusOnes = !empty($s['rsvp_allow_plus_ones']);
        $collectPhone  = !empty($s['rsvp_collect_phone']);
        $rsvpSettings  = (array) ($s['rsvp_settings'] ?? []);

        // Closed-deadline guard — rendered on the form too, but a client
        // could still POST so re-check here.
        if (!empty($rsvpSettings['deadline'])) {
            try {
                $deadline = new \DateTime($rsvpSettings['deadline']);
                if ($deadline < new \DateTime()) {
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'RSVPs are closed for this event.'], 422);
                    }
                    return back()->withErrors(['response' => 'RSVPs are closed for this event.']);
                }
            } catch (\Throwable $e) {}
        }

        $rules = [
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:160'],
            'response'  => ['required', 'in:yes,no,maybe'],
            'plus_ones' => ['nullable', 'integer', 'min:0', 'max:20'],
            'message'   => ['nullable', 'string', 'max:1000'],
            'occurrences'   => ['nullable', 'array', 'max:50'],
            'occurrences.*' => ['string', 'max:64'],
            'answers'       => ['nullable', 'array', 'max:50'],
        ];
        if ($collectPhone) $rules['phone'] = ['nullable', 'string', 'max:40'];
        if (!empty($rsvpSettings['collect_company'])) $rules['company'] = ['nullable', 'string', 'max:191'];
        if (!empty($rsvpSettings['collect_role']))    $rules['role']    = ['nullable', 'string', 'max:191'];
        $data = $request->validate($rules);

        // Validate per-question constraints (required / select range).
        $questions = (array) ($rsvpSettings['questions'] ?? []);
        $answersIn = (array) ($data['answers'] ?? []);
        $cleanAnswers = [];
        $errs = [];
        foreach ($questions as $q) {
            $label = trim((string) ($q['label'] ?? ''));
            if ($label === '') continue;
            $val = $answersIn[$label] ?? null;
            $required = !empty($q['required']);
            $isEmpty = is_array($val) ? empty($val) : (trim((string) $val) === '');
            if ($required && $isEmpty) {
                $errs["answers.$label"] = "“{$label}” is required.";
                continue;
            }
            if ($isEmpty) continue;
            if (is_array($val)) {
                $val = array_values(array_filter(array_map('strval', $val), fn ($x) => $x !== ''));
                if (!empty($val)) $cleanAnswers[$label] = array_slice($val, 0, 20);
            } else {
                $cleanAnswers[$label] = mb_substr(trim((string) $val), 0, 1000);
            }
        }
        if ($errs) {
            if ($request->wantsJson()) return response()->json(['success' => false, 'errors' => $errs], 422);
            return back()->withErrors($errs)->withInput();
        }

        // Capacity / waitlist enforcement — re-tally seats consumed by
        // confirmed "yes" RSVPs and bump this submission to the waitlist
        // if it would push us past the cap.
        $status = 'confirmed';
        $cap = isset($rsvpSettings['capacity']) ? (int) $rsvpSettings['capacity'] : 0;
        $seatsThisRsvp = $data['response'] === 'yes' ? (1 + ($allowPlusOnes ? (int)($data['plus_ones'] ?? 0) : 0)) : 0;
        if ($cap > 0 && $seatsThisRsvp > 0) {
            $usedSeats = (int) \App\Modules\User\Models\Rsvp::query()
                ->where('link_id', $link->id)
                ->where('response', 'yes')
                ->where('status', 'confirmed')
                ->sum(\DB::raw('plus_ones + 1'));
            if (($usedSeats + $seatsThisRsvp) > $cap) {
                if (empty($rsvpSettings['waitlist_enabled'])) {
                    if ($request->wantsJson()) {
                        return response()->json(['success' => false, 'message' => 'This event is full.'], 422);
                    }
                    return back()->withErrors(['response' => 'This event is full.']);
                }
                $status = 'waitlist';
            }
        }

        $rsvp = \App\Modules\User\Models\Rsvp::create([
            'link_id'         => $link->id,
            'source_block_id' => null,
            'name'            => $data['name'],
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'company'         => $data['company'] ?? null,
            'role'            => $data['role'] ?? null,
            'response'        => $data['response'],
            'status'          => $status,
            'plus_ones'       => $allowPlusOnes ? (int)($data['plus_ones'] ?? 0) : 0,
            'message'         => $data['message'] ?? null,
            'source'          => $request->input('_source', 'event_page'),
            'occurrences'     => !empty($data['occurrences']) ? array_values($data['occurrences']) : null,
            'answers'         => $cleanAnswers ?: null,
            'ip_address'      => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 250),
        ]);

        // Account-level forwarding rules — fan out to the owner's email/webhook
        // destinations whose source filter matches RSVPs.
        try {
            $rsvp->setRelation('link', $link);
            app(\App\Modules\User\Services\InboxForwarder::class)
                ->dispatchForRsvp($link->user_id, $rsvp);
        } catch (\Throwable $e) {
            logger()->warning('Inbox forwarder (rsvp) failed: ' . $e->getMessage());
        }

        // Silently provision a lightweight free Sayzio account for the
        // attendee, mirroring the paid-ticket guest flow. Only for
        // not-signed-in visitors who provided an email; an existing
        // account with that email is reused untouched. Best-effort —
        // never let account creation fail or block the RSVP itself.
        try {
            if (!empty($rsvp->email) && !$request->user()) {
                $attendee = \App\Modules\User\Models\User::firstOrCreate(
                    ['email' => $rsvp->email],
                    [
                        'name'     => $rsvp->name,
                        'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                        'plan_id'  => \App\Modules\Admin\Models\Plan::defaultPlan()?->id,
                        'status'   => 'active',
                    ],
                );
                if ($attendee->wasRecentlyCreated) {
                    $attendee->ensureDefaultWorkspace();
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('RSVP account provisioning failed: ' . $e->getMessage());
        }

        // Task #3606: confirmed "yes" RSVPs get a tier-less QR check-in
        // ticket, same as paid tier buyers. Waitlisted/maybe/not-going
        // RSVPs get none (see RsvpTicketService::sync()).
        $ticket = \App\Services\Events\RsvpTicketService::sync($rsvp);

        // Confirmation + organizer notify (best-effort, swallow failures).
        try {
            if ($rsvp->email && ($rsvpSettings['send_confirmation'] ?? true)) {
                \App\Modules\Common\Services\Emailer::sendMailable('events.rsvp_confirmation', $rsvp->email, new \App\Mail\EventRsvpConfirmationMail($link, $rsvp, $ticket), ['title' => $link->title], ['related' => $link, 'user' => $link->user_id]);
            }
        } catch (\Throwable $e) {
            logger()->warning('RSVP confirmation email failed: ' . $e->getMessage());
        }
        try {
            if (($rsvpSettings['notify_owner'] ?? true)) {
                $ownerEmail = $link->user?->email;
                if ($ownerEmail) {
                    \App\Modules\Common\Services\Emailer::sendMailable('events.rsvp_notify_owner', $ownerEmail, new \App\Mail\EventRsvpNotifyOwnerMail($link, $rsvp), ['title' => $link->title, 'name' => $rsvp->name], ['related' => $link, 'user' => $link->user_id]);
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('RSVP notify-owner email failed: ' . $e->getMessage());
        }

        $request->session()->put('rsvp_submitted_' . $link->id, true);

        // A/B conversion attribution for RSVP form submissions on biolinks
        // that wrap an event (rsvp_enabled). Only fires when an experiment
        // is running; cheap no-op otherwise.
        [$abExp, $abVariant] = app(BiolinkExperimentService::class)
            ->resolveAssignment($request, $link);
        if ($abExp && $abVariant) {
            app(BiolinkExperimentService::class)->recordConversion($abExp, $abVariant);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'message'    => $status === 'waitlist'
                    ? 'You\'re on the waitlist — we\'ll email you the moment a spot opens.'
                    : 'Thanks for your RSVP!',
                'status'     => $status,
                'manage_url' => $rsvp->manageUrl(),
            ]);
        }
        return redirect()->route('redirect.rsvp.manage', [$alias, $rsvp->manage_token])
            ->with('success', $status === 'waitlist'
                ? 'You\'re on the waitlist — we\'ll email you the moment a spot opens.'
                : 'Thanks for your RSVP!');
    }
}
