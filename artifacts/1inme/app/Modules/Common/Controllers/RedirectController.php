<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\AppLinkResolver;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\Common\Services\SmartRedirectResolver;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\AbVariant;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RedirectController extends Controller
{
    public function __construct(
        protected LinkTrackingService $trackingService
    ) {}

    public function handle(Request $request, string $alias)
    {
        // Resolve to the link via primary alias OR any of its additional aliases.
        // Host-aware: requests on a known custom domain only match links bound to
        // that domain; an unknown/disabled host gets a "domain not connected" notice.
        $host = $request->getHost();
        $link = Link::resolveByAlias($alias, $host);
        if (!$link) {
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

        // Stamp the matched smart-link rule id onto the click row so the
        // per-rule analytics breakdown can attribute hits. Best-effort —
        // schema may pre-date the matched_rule_id column on older installs.
        if ($trackedClick && !empty($smart['rule']['id'])
            && \Schema::hasColumn('link_clicks', 'matched_rule_id')) {
            try {
                \DB::table('link_clicks')
                    ->where('id', $trackedClick->id)
                    ->update(['matched_rule_id' => (string) $smart['rule']['id']]);
            } catch (\Throwable $e) { /* swallow — analytics row, not the redirect */ }
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
        if ($link->type === 'url'
            && ($settings['open_in_app'] ?? true) !== false
            && !$request->boolean('_web')) {
            $ua = $request->userAgent() ?? '';
            $isIos     = (bool) preg_match('/iPhone|iPad|iPod/i', $ua);
            $isAndroid = (bool) preg_match('/Android/i', $ua);
            if ($isIos || $isAndroid) {
                $matched = AppLinkResolver::resolve($finalUrl);
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

        // Visibility gating for biolinks (see enforceBiolinkVisibility()).
        if ($gated = $this->enforceBiolinkVisibility($request, $link)) {
            return $gated;
        }

        // Owner-scoped "draft preview" — when the editor iframe loads with
        // ?_preview=1&_draft=1 (signature must still be valid, ignoring the
        // draft + cache-buster params), merge the cached unsaved form state
        // into the link's settings BEFORE rendering. This is what powers the
        // live device preview so creators can see colour/font/theme/layout
        // tweaks without hitting Save first.
        if ($link->type === 'biolink') {
            $this->applyDraftOverrides($request, $link);
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

        return match ($link->type) {
            'url' => tap(
                redirect()->away($finalUrl, $link->redirect_type ?: 301),
                fn ($r) => $smartCookie && $r->withCookie($smartCookie)
            ),
            'biolink' => tap(
                $this->applyBiolinkFramingHeaders(
                    response()->view($this->biolinkViewFor($link), compact('link')),
                    $request
                ),
                fn () => $this->scheduleLazySocialRefresh()
            ),
            'file' => $this->handleFileDownload($link),
            'ics' => $this->handleIcsDownload($link),
            'vcf' => $this->handleVcfDownload($link),
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
        $mode = data_get($link->settings, 'biolink.mode', 'list');
        if ($mode !== 'conversational' && $mode !== 'slides') return 'common.biolink';

        $req = request();
        $isOwnerPreview = $req && $req->boolean('_preview')
            && $req->hasValidSignatureWhileIgnoring(['_draft', '_t'], false);

        if ($mode === 'slides') {
            $q = \App\Modules\User\Models\LinkSlideDeck::withoutGlobalScope('workspace')
                ->where('link_id', $link->id);
            if (!$isOwnerPreview) $q->where('is_published', true);
            return $q->exists() ? 'common.biolink-slides' : 'common.biolink';
        }

        // Conversational mode — same draft preview unlock as before.
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
        if ($request->boolean('_preview') && $request->hasValidSignatureWhileIgnoring(['_draft', '_t'], false)) {
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
        if (!$request->hasValidSignatureWhileIgnoring(['_draft', '_t'], false)) {
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
     * Enforce a biolink's visibility tier (public/registered/followers/
     * subscribers). Returns a 401 gated response when the viewer doesn't
     * meet the tier, or null to allow the request to proceed.
     *
     * Owners (the link's creator) always bypass the gate. Public visibility
     * is a no-op so this is cheap to call on every biolink request.
     */
    protected function enforceBiolinkVisibility(Request $request, Link $link)
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return null;
        if ($link->type !== 'biolink') return null;

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
        if ($request->boolean('_preview') && $request->hasValidSignatureWhileIgnoring(['_draft', '_t'], false)) {
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
        if (!$link || $link->type !== 'biolink') abort(404);

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
            $icons[] = ['src' => $link->favicon, 'sizes' => '64x64', 'type' => 'image/png'];
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
            'name' => $m['name'] ?? $link->title ?? '1INME Bio',
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
        if (!$link || $link->type !== 'biolink') abort(404);

        if (!$link->isAccessible()) {
            if ($redirect = $link->getExpiryRedirectUrl()) {
                return redirect()->away($redirect, 302);
            }
            return response()->view('common.link-expired', ['link' => $link], 410);
        }

        // Same visibility enforcement as the biolink page itself, so private
        // tiers cannot be bypassed by deep-linking directly to a block click URL.
        if ($gated = $this->enforceBiolinkVisibility($request, $link)) {
            return $gated;
        }

        $block = BiolinkBlock::where('id', $blockId)->where('link_id', $link->id)->firstOrFail();
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

        $utmParams = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $param) {
            if (!empty($linkData[$param])) {
                $utmParams[$param] = $linkData[$param];
            }
        }
        if (!empty($utmParams)) {
            $separator = str_contains($destinationUrl, '?') ? '&' : '?';
            $destinationUrl .= $separator . http_build_query($utmParams);
        }

        // Honor an explicit ?source=… tag (e.g. "ar" from the AR Business Card
        // renderer) so block-click rows are attributed to the surface that
        // sent the visitor, falling back to "web" for normal biolink taps.
        $rawSource = (string) $request->query('source', '');
        $sourceTag = preg_match('/^[a-z0-9_-]{1,32}$/', $rawSource) ? $rawSource : 'web';

        $this->trackingService->trackBlockClick($link, $block, $destinationUrl, $request, $alias, $sourceTag);

        return redirect()->away($destinationUrl, 302);
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

    public function subscribe(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link) abort(404);

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

        $subscriber = Subscriber::create([
            'user_id' => $link->user_id,
            'link_id' => $link->id,
            'block_id' => $block->id,
            'type' => $data['type'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'name' => $data['name'] ?? null,
            'channel_url' => $data['channel_url'] ?? null,
            'status' => 'active',
            'source' => $data['type'] === 'whatsapp_channel' ? ($data['_fingerprint'] ?? $alias) : $alias,
            'subscribed_at' => now(),
            'is_spam' => $spamCheck['is_spam'],
            'spam_reason' => $spamCheck['is_spam'] ? $spamCheck['reason'] : null,
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

        return response()->json(['success' => true, 'message' => 'Subscribed successfully!']);
    }

    public function rsvpForm(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);
        if (empty(($link->settings ?? [])['rsvp_enabled'])) abort(404);
        $link->load('icsData');
        $submitted = (bool) $request->session()->get('rsvp_submitted_' . $link->id);
        return view('common.rsvp-form', compact('link', 'submitted'));
    }

    public function rsvpSubmit(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics') abort(404);
        if (empty(($link->settings ?? [])['rsvp_enabled'])) abort(404);

        $allowPlusOnes = !empty(($link->settings ?? [])['rsvp_allow_plus_ones']);
        $collectPhone  = !empty(($link->settings ?? [])['rsvp_collect_phone']);

        $rules = [
            'name'      => ['required', 'string', 'max:120'],
            'email'     => ['nullable', 'email', 'max:160'],
            'response'  => ['required', 'in:yes,no,maybe'],
            'plus_ones' => ['nullable', 'integer', 'min:0', 'max:20'],
            'message'   => ['nullable', 'string', 'max:1000'],
        ];
        if ($collectPhone) $rules['phone'] = ['nullable', 'string', 'max:40'];
        $data = $request->validate($rules);

        $rsvp = \App\Modules\User\Models\Rsvp::create([
            'link_id'         => $link->id,
            'source_block_id' => null,
            'name'            => $data['name'],
            'email'           => $data['email'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'response'        => $data['response'],
            'plus_ones'       => $allowPlusOnes ? (int)($data['plus_ones'] ?? 0) : 0,
            'message'         => $data['message'] ?? null,
            'source'          => $request->input('_source', 'event_page'),
            'meta'            => ['ip' => $request->ip(), 'ua' => substr((string)$request->userAgent(), 0, 250)],
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

        $request->session()->put('rsvp_submitted_' . $link->id, true);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Thanks for your RSVP!']);
        }
        return redirect()->route('redirect.rsvp.form', $alias)->with('success', 'Thanks for your RSVP!');
    }
}
