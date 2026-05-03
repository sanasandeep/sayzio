<style>
    .device-switcher-btn {
        width: 36px; height: 36px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px;
        color: var(--text-faint);
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .device-switcher-btn:hover {
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        border-color: rgba(124,58,237,0.15);
    }
    .device-switcher-btn.active {
        background: rgba(124,58,237,0.15);
        color: #a78bfa;
        border-color: rgba(124,58,237,0.3);
        box-shadow: 0 0 12px rgba(124,58,237,0.1);
    }

    .device-frame-phone {
        width: 280px;
        margin: 0 auto;
    }
    @media (min-width: 1024px) {
        .device-preview-root {
            position: sticky;
            top: 12px;
        }
        .device-preview-root .device-frame-phone {
            width: min(280px, max(200px, calc((100vh - 120px) * 375 / 812)));
        }
    }
    /* Activate the side-by-side editor layout (form | device preview) a bit
       earlier than Tailwind's lg breakpoint so the phone stays visible on
       ~960-1023px viewports (common iframe widths). */
    @media (min-width: 900px) and (max-width: 1023.98px) {
        .grid.lg\:grid-cols-12 {
            display: grid !important;
            grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
        }
        .lg\:col-span-7 { grid-column: span 7 / span 7 !important; }
        .lg\:col-span-5 { grid-column: span 5 / span 5 !important; }
        .hidden.lg\:block { display: block !important; }
        .device-preview-root {
            position: sticky;
            top: 12px;
        }
        .device-preview-root .device-frame-phone {
            width: min(260px, max(180px, calc((100vh - 120px) * 375 / 812)));
        }
    }
    .device-frame-phone .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 2rem;
        position: relative;
        aspect-ratio: 375 / 812;
    }
    .device-frame-phone .device-screen iframe {
        width: 375px;
        height: 812px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-tablet {
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
    }
    .device-frame-tablet .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 0.75rem;
        position: relative;
        aspect-ratio: 3 / 4;
    }
    .device-frame-tablet .device-screen iframe {
        width: 768px;
        height: 1024px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-tablet-land {
        width: 100%;
        margin: 0 auto;
    }
    .device-frame-tablet-land .device-screen {
        width: 100%;
        overflow: hidden;
        border-radius: 0.75rem;
        position: relative;
        aspect-ratio: 4 / 3;
    }
    .device-frame-tablet-land .device-screen iframe {
        width: 1024px;
        height: 768px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-frame-desktop {
        width: 100%;
        margin: 0 auto;
    }
    .device-frame-desktop .device-screen {
        width: 100%;
        overflow: hidden;
        position: relative;
        aspect-ratio: 16 / 10;
    }
    .device-frame-desktop .device-screen iframe {
        width: 1440px;
        height: 900px;
        transform-origin: top left;
        border: 0;
        position: absolute;
        top: 0;
        left: 0;
    }

    .device-resolution-label {
        text-align: center;
        font-size: 10px;
        color: var(--text-faint);
        margin-top: 8px;
        font-family: 'SF Mono', 'Fira Code', monospace;
        letter-spacing: 0.5px;
        opacity: 0.6;
    }

    /* Inline "preview session expired" banner overlaid on the device screen
       when our background re-mint of the signed URL fails (e.g. dashboard
       session was logged out in another tab). The user can click to retry. */
    .preview-expired-banner {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: none;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 16px;
        text-align: center;
        background: rgba(10, 6, 18, 0.88);
        backdrop-filter: blur(6px);
        color: rgba(255, 255, 255, 0.92);
        font-size: 12px;
        line-height: 1.4;
    }
    .device-screen.preview-expired .preview-expired-banner { display: flex; }
    .preview-expired-banner .preview-expired-title {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
    }
    .preview-expired-banner .preview-expired-msg {
        color: rgba(255, 255, 255, 0.65);
        max-width: 220px;
    }
    .preview-expired-banner button {
        margin-top: 4px;
        padding: 6px 14px;
        border-radius: 8px;
        background: rgba(124, 58, 237, 0.85);
        color: #fff;
        font-size: 12px;
        font-weight: 500;
        border: 1px solid rgba(167, 139, 250, 0.4);
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .preview-expired-banner button:hover { background: rgba(124, 58, 237, 1); }
</style>

<div class="device-preview-root" x-data="{ previewMode: 'phone' }">
    <div class="flex items-center justify-center gap-1 mb-4">
        <button type="button" @click="previewMode = 'phone'; switchPreviewMode('phone')" class="device-switcher-btn" :class="previewMode === 'phone' ? 'active' : ''" title="Phone">
            <i class="fas fa-mobile-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'tablet'; switchPreviewMode('tablet')" class="device-switcher-btn" :class="previewMode === 'tablet' ? 'active' : ''" title="Tablet Portrait">
            <i class="fas fa-tablet-alt"></i>
        </button>
        <button type="button" @click="previewMode = 'tablet-land'; switchPreviewMode('tablet-land')" class="device-switcher-btn" :class="previewMode === 'tablet-land' ? 'active' : ''" title="Tablet Landscape">
            <i class="fas fa-tablet-alt" style="transform: rotate(-90deg);"></i>
        </button>
        <button type="button" @click="previewMode = 'desktop'; switchPreviewMode('desktop')" class="device-switcher-btn" :class="previewMode === 'desktop' ? 'active' : ''" title="Desktop">
            <i class="fas fa-desktop"></i>
        </button>
    </div>
    <div class="flex justify-center transition-all duration-500 ease-in-out">
        {{-- Phone --}}
        <div x-show="previewMode === 'phone'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="device-frame-phone relative mx-auto">
            <div class="relative bg-black rounded-[2.8rem] p-[10px]" style="border: 2px solid rgba(60,60,70,0.7); box-shadow: 0 10px 28px -10px rgba(0,0,0,0.45), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 z-10 flex items-center justify-center" style="width: 100px; height: 28px; background: #000; border-radius: 0 0 18px 18px;">
                    <div class="rounded-full" style="width: 56px; height: 16px; background: rgba(25,25,30,0.95); border: 1px solid rgba(40,40,50,0.6);"></div>
                </div>
                <div class="absolute top-[6px] right-[22px] z-10 flex items-center gap-1">
                    <div style="width: 14px; height: 8px; border-radius: 2px; border: 1px solid rgba(255,255,255,0.15);"><div style="width: 60%; height: 100%; background: rgba(255,255,255,0.15); border-radius: 1px;"></div></div>
                </div>
                <div class="device-screen relative" style="background: #000;">
                    <iframe class="preview-iframe" data-preview="phone"></iframe>
                    <div class="preview-expired-banner">
                        <div class="preview-expired-title">Preview session expired</div>
                        <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                        <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                    </div>
                </div>
                <div class="absolute bottom-[6px] left-1/2 -translate-x-1/2 rounded-full" style="width: 110px; height: 4px; background: rgba(255,255,255,0.12);"></div>
            </div>
            <div class="device-resolution-label">iPhone 15 &middot; 375 &times; 812</div>
        </div>
        {{-- Tablet Portrait --}}
        <div x-show="previewMode === 'tablet'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-tablet relative mx-auto">
            <div class="relative rounded-[1.8rem] shadow-2xl" style="background: linear-gradient(180deg, #2a2a35, #1a1a25); border: 2.5px solid rgba(60,60,70,0.7); box-shadow: 0 20px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="flex justify-center pt-2 pb-1">
                    <div class="rounded-full" style="width: 8px; height: 8px; background: rgba(30,30,40,0.9); border: 1px solid rgba(60,60,70,0.5); box-shadow: inset 0 1px 2px rgba(0,0,0,0.4);"></div>
                </div>
                <div class="px-3 pb-3">
                    <div class="device-screen rounded-lg" style="background: #000;">
                        <iframe class="preview-iframe" data-preview="tablet"></iframe>
                        <div class="preview-expired-banner">
                            <div class="preview-expired-title">Preview session expired</div>
                            <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                            <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="device-resolution-label">iPad &middot; 768 &times; 1024</div>
        </div>
        {{-- Tablet Landscape --}}
        <div x-show="previewMode === 'tablet-land'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-tablet-land relative mx-auto">
            <div class="relative rounded-[1.8rem] shadow-2xl" style="background: linear-gradient(90deg, #2a2a35, #1a1a25); border: 2.5px solid rgba(60,60,70,0.7); box-shadow: 0 20px 60px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.05);">
                <div class="flex items-center" style="min-height: 100%;">
                    <div class="flex flex-col justify-center items-center px-1.5" style="flex-shrink: 0;">
                        <div class="rounded-full" style="width: 8px; height: 8px; background: rgba(30,30,40,0.9); border: 1px solid rgba(60,60,70,0.5); box-shadow: inset 0 1px 2px rgba(0,0,0,0.4);"></div>
                    </div>
                    <div class="py-3 pr-3 flex-1">
                        <div class="device-screen rounded-lg" style="background: #000;">
                            <iframe class="preview-iframe" data-preview="tablet-land"></iframe>
                            <div class="preview-expired-banner">
                                <div class="preview-expired-title">Preview session expired</div>
                                <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                                <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="device-resolution-label">iPad Landscape &middot; 1024 &times; 768</div>
        </div>
        {{-- Desktop --}}
        <div x-show="previewMode === 'desktop'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-cloak class="device-frame-desktop relative mx-auto">
            <div class="relative rounded-t-xl shadow-2xl" style="background: linear-gradient(180deg, #2c2c38, #1e1e28); border: 2.5px solid rgba(60,60,70,0.7); border-bottom: none; box-shadow: 0 -2px 40px rgba(0,0,0,0.3);">
                <div class="flex items-center gap-1.5 px-4 py-2" style="border-bottom: 1px solid rgba(60,60,70,0.5);">
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #ff5f57; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #febc2e; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="w-[10px] h-[10px] rounded-full" style="background: #28c840; box-shadow: inset 0 -1px 1px rgba(0,0,0,0.2);"></div>
                    <div class="flex-1 ml-4 mr-8 rounded-md py-1 px-3 text-[10px] truncate flex items-center gap-1.5" style="background: rgba(0,0,0,0.25); color: rgba(255,255,255,0.4); border: 1px solid rgba(60,60,70,0.4);">
                        <i class="fas fa-lock text-[7px]" style="color: rgba(255,255,255,0.25);"></i>
                        {{ url('/' . $link->alias) }}
                    </div>
                </div>
                <div class="device-screen" style="background: #000;">
                    <iframe class="preview-iframe" data-preview="desktop"></iframe>
                    <div class="preview-expired-banner">
                        <div class="preview-expired-title">Preview session expired</div>
                        <div class="preview-expired-msg">Your editor has been open for a while. Reload to refresh the preview.</div>
                        <button type="button" onclick="refreshPreviewSignedUrl(true)">Reload preview</button>
                    </div>
                </div>
            </div>
            <div class="mx-auto relative" style="width: 50%; height: 18px; background: linear-gradient(180deg, #2a2a35, #1e1e28); border-radius: 0 0 2px 2px; border: 2px solid rgba(60,60,70,0.5); border-top: 1px solid rgba(60,60,70,0.3);">
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-12 h-[3px] rounded-b" style="background: rgba(255,255,255,0.04);"></div>
            </div>
            <div class="mx-auto" style="width: 70%; height: 4px; background: linear-gradient(180deg, #25252f, #1a1a22); border-radius: 0 0 8px 8px; border: 1.5px solid rgba(60,60,70,0.4); border-top: none;"></div>
            <div class="device-resolution-label">MacBook &middot; 1440 &times; 900</div>
        </div>
    </div>
</div>

@php
    // Owner-scoped, signed preview URL. The RedirectController honours
    // `?_preview=1` + a valid Laravel signature as proof of ownership so the
    // iframe is never gated and never blocked by SameSite/3rd-party-cookie
    // behaviour on a custom domain. 24h expiry is plenty for an editing session.
    $__previewExpiresAt = now()->addHours(24);
    $__previewUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'redirect.handle',
        $__previewExpiresAt,
        ['alias' => $link->alias, '_preview' => 1]
    );
    $__previewRefreshUrl = route('user.links.preview-url', ['link' => $link->id]);
@endphp
<script>
var _previewUrl = @json($__previewUrl);
var _previewExpiresAt = @json($__previewExpiresAt->getTimestamp()); // epoch seconds
var _previewRefreshEndpoint = @json($__previewRefreshUrl);
var _previewRefreshInFlight = null;
var _previewRefreshTimer = null;
var _activePreviewMode = 'phone';
var _deviceViewports = {
    phone:        { w: 375, h: 812 },
    tablet:       { w: 768, h: 1024 },
    'tablet-land': { w: 1024, h: 768 },
    desktop:      { w: 1440, h: 900 }
};

function _scaleSingleIframe(iframe) {
    var mode = iframe.dataset.preview;
    var vp = _deviceViewports[mode];
    if (!vp) return;
    var screen = iframe.closest('.device-screen');
    if (!screen) return;
    var containerW = screen.offsetWidth;
    if (containerW <= 0) return;
    var scale = containerW / vp.w;
    iframe.style.transform = 'scale(' + scale + ')';
}

function _scaleDeviceIframes() {
    document.querySelectorAll('.preview-iframe').forEach(_scaleSingleIframe);
}

// Per-screen ResizeObserver: re-scale the moment the device-screen actually
// has a non-zero width (after the x-show / x-transition animation finishes).
// Avoids the previous brittle "setTimeout(..., 100)" race where the iframe
// stayed permanently blank because it was measured at width 0.
function _ensureIframeObserved(iframe) {
    var screen = iframe.closest('.device-screen');
    if (!screen || screen.__previewObserved) return;
    screen.__previewObserved = true;
    if (typeof ResizeObserver === 'function') {
        var ro = new ResizeObserver(function() { _scaleSingleIframe(iframe); });
        ro.observe(screen);
    }
}

function _setExpiredBanner(show) {
    document.querySelectorAll('.preview-iframe').forEach(function(f) {
        var screen = f.closest('.device-screen');
        if (!screen) return;
        screen.classList.toggle('preview-expired', !!show);
    });
}

// Mint a fresh signed URL via the editor endpoint. When `forceReloadAll` is
// true we also reload every already-loaded iframe so the banner disappears
// the moment the new URL is in hand. Returns a Promise<boolean>.
function refreshPreviewSignedUrl(forceReloadAll) {
    if (_previewRefreshInFlight) return _previewRefreshInFlight;
    _previewRefreshInFlight = fetch(_previewRefreshEndpoint, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(res) {
        if (!res.ok) throw new Error('preview-url HTTP ' + res.status);
        return res.json();
    }).then(function(json) {
        if (!json || !json.url) throw new Error('preview-url malformed response');
        _previewUrl = json.url;
        if (json.expires_at) _previewExpiresAt = json.expires_at;
        _setExpiredBanner(false);
        _schedulePreviewRefresh();
        if (forceReloadAll) {
            // Reload every iframe that already had a src so the new signed
            // URL takes effect immediately on every device mode.
            document.querySelectorAll('.preview-iframe').forEach(function(f) {
                if (f.src && f.src !== 'about:blank' && f.src !== window.location.href) {
                    _reloadIframe(f);
                }
            });
        }
        return true;
    }).catch(function(err) {
        // Most common failure: dashboard session was lost (auth redirect to
        // login -> JSON parse fail) or network is offline. Show the inline
        // banner so the user knows to reload, instead of letting the iframe
        // silently drift into Laravel's "Invalid signature" page.
        _setExpiredBanner(true);
        return false;
    }).finally(function() {
        _previewRefreshInFlight = null;
    });
    return _previewRefreshInFlight;
}

// Schedule a proactive refresh ~5 minutes before the current signed URL
// expires. Falls back to "right now" if the URL has already expired (e.g.
// the laptop was asleep past the 24h window).
function _schedulePreviewRefresh() {
    if (_previewRefreshTimer) {
        clearTimeout(_previewRefreshTimer);
        _previewRefreshTimer = null;
    }
    if (!_previewExpiresAt) return;
    var nowSec = Math.floor(Date.now() / 1000);
    var leadSec = 300; // refresh 5 min before expiry
    var delayMs = Math.max(0, (_previewExpiresAt - leadSec - nowSec) * 1000);
    // Cap at ~24 days — setTimeout silently fires immediately past INT32_MAX.
    if (delayMs > 2147483000) delayMs = 2147483000;
    _previewRefreshTimer = setTimeout(function() { refreshPreviewSignedUrl(true); }, delayMs);
}

function _reloadIframe(f) {
    // If the URL has already expired by the time we try to reload (long sleep
    // / browser was backgrounded past the 24h window), block this reload and
    // kick off a fresh-URL fetch instead — otherwise we'd just paint Laravel's
    // "Invalid signature" page into the iframe.
    var nowSec = Math.floor(Date.now() / 1000);
    if (_previewExpiresAt && nowSec >= _previewExpiresAt) {
        refreshPreviewSignedUrl(true);
        return;
    }
    try {
        if (f.contentWindow && f.contentWindow.location) {
            f.contentWindow.location.replace(_previewUrl);
            f.dataset.previewStale = '';
            return;
        }
    } catch (e) { /* cross-origin: fall through */ }
    f.src = _previewUrl;
    f.dataset.previewStale = '';
}

function _ensureIframeLoaded(mode) {
    var f = document.querySelector('.preview-iframe[data-preview="' + mode + '"]');
    if (!f) return null;
    var nowSec = Math.floor(Date.now() / 1000);
    var expired = _previewExpiresAt && nowSec >= _previewExpiresAt;
    if (expired) {
        // URL has already expired (long-asleep tab) — fetch a fresh one before
        // the iframe ever sees the bad signature.
        refreshPreviewSignedUrl(true);
        _ensureIframeObserved(f);
        _scaleSingleIframe(f);
        return f;
    }
    if (!f.src || f.src === 'about:blank' || f.src === window.location.href) {
        f.src = _previewUrl;
        f.dataset.previewStale = '';
    } else if (f.dataset.previewStale === '1') {
        // The page was edited while this device mode was hidden — reload
        // now so the user doesn't see stale content on mode switch.
        _reloadIframe(f);
    }
    _ensureIframeObserved(f);
    _scaleSingleIframe(f);
    return f;
}

function switchPreviewMode(mode) {
    _activePreviewMode = mode;
    // Ensure the requested mode has a src; leave OTHER iframes alone instead
    // of resetting them to about:blank — switching back and forth was causing
    // a permanently-blank frame because the just-shown iframe was measured at
    // width 0 before its container animated in. Stale iframes are reloaded
    // on activation rather than on every edit (cheaper, no flash).
    _ensureIframeLoaded(mode);
}

function refreshPreview() {
    // Reload the currently visible iframe immediately. Mark all other
    // already-loaded iframes as stale so switchPreviewMode() reloads them
    // when they next become active — keeps every device mode in sync with
    // the latest edit without re-fetching backgrounds the user can't see.
    document.querySelectorAll('.preview-iframe').forEach(function(f) {
        if (!f.src || f.src === 'about:blank' || f.src === window.location.href) return;
        if (f.dataset.preview === _activePreviewMode) {
            _reloadIframe(f);
        } else {
            f.dataset.previewStale = '1';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    _schedulePreviewRefresh();
    _ensureIframeLoaded('phone');
});

window.addEventListener('resize', function() {
    clearTimeout(window._resizeScaleTimer);
    window._resizeScaleTimer = setTimeout(_scaleDeviceIframes, 150);
});

// Tab-return safety net: if the laptop was asleep / tab was hidden long
// enough for the URL to expire, mint a new one the moment the user comes
// back so they don't first see a broken preview frame.
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState !== 'visible') return;
    var nowSec = Math.floor(Date.now() / 1000);
    if (_previewExpiresAt && nowSec >= (_previewExpiresAt - 60)) {
        refreshPreviewSignedUrl(true);
    }
});
</script>
