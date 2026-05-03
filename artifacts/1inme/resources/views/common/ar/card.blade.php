<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>{{ $cfg['display_name'] }} · AR Card · 1INME</title>
<meta name="theme-color" content="{{ $cfg['accent_color'] }}">
<script type="module" src="https://unpkg.com/@google/model-viewer@3.5.0/dist/model-viewer.min.js"></script>
<style>
    :root {
        --accent: {{ $cfg['accent_color'] }};
        --bg: #050816;
    }
    * { box-sizing: border-box; }
    html, body {
        margin: 0; padding: 0; min-height: 100vh; min-height: 100dvh;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #f8fafc;
        background: radial-gradient(circle at 30% 0%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 60%), var(--bg);
        overflow-x: hidden;
    }
    .wrap { max-width: 460px; margin: 0 auto; padding: 16px 16px 96px; }
    .topbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0 18px; }
    .brand { font-size: 11px; letter-spacing: .14em; opacity: .7; text-transform: uppercase; }
    .badge {
        font-size: 10px; padding: 4px 10px; border-radius: 999px;
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
    }
    model-viewer {
        width: 100%; height: 60vh; min-height: 320px;
        background: transparent;
        --poster-color: transparent;
    }
    .name { font-size: 22px; font-weight: 700; margin: 14px 4px 2px; }
    .headline { font-size: 13px; opacity: .75; margin: 0 4px 18px; }
    .blocks { display: grid; gap: 10px; margin-top: 14px; }
    .blk {
        display: flex; align-items: center; gap: 12px;
        padding: 14px 16px; border-radius: 14px; text-decoration: none;
        color: #fff; background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.12);
        transition: transform .12s ease, background .2s ease;
    }
    .blk:hover { background: rgba(255,255,255,.10); }
    .blk:active { transform: scale(.98); }
    .blk .ico {
        width: 32px; height: 32px; border-radius: 10px;
        background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 50%, #fff));
        display: grid; place-items: center; font-weight: 700; font-size: 13px;
    }
    .blk .lbl { font-size: 14px; font-weight: 600; }
    .blk .sub { font-size: 11px; opacity: .55; }
    .toast {
        position: fixed; left: 50%; bottom: 22px; transform: translateX(-50%);
        background: rgba(15,23,42,.92); color: #f8fafc;
        padding: 10px 14px; border-radius: 12px; font-size: 12px;
        border: 1px solid rgba(255,255,255,.1);
        max-width: 90vw; text-align: center; line-height: 1.4;
    }
    .fallback-link {
        display: inline-block; margin-top: 18px; font-size: 12px;
        color: rgba(255,255,255,.7); text-decoration: underline;
    }
    .ar-cta {
        display: block; width: 100%; padding: 14px 18px;
        margin-top: 18px; border-radius: 14px;
        background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 55%, #fff));
        color: #fff; font-weight: 700; font-size: 14px;
        text-align: center; border: none; cursor: pointer; text-decoration: none;
    }
    .footer { margin-top: 32px; text-align: center; font-size: 10px; opacity: .55; }
    .footer a { color: inherit; }

    /* In-AR floating hotspots. model-viewer renders these as HTML pinned to
       3D positions on/around the card during WebXR + the inline 3D preview
       (Scene Viewer / Quick Look render the GLB/USDZ natively and can't
       host arbitrary HTML — those modes use the in-page block list). */
    .hotspot {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; border-radius: 999px;
        background: rgba(15,23,42,.92); color: #fff;
        font-size: 11px; font-weight: 600; text-decoration: none;
        border: 1px solid color-mix(in srgb, var(--accent) 60%, #fff);
        box-shadow: 0 6px 18px rgba(0,0,0,.35);
        white-space: nowrap; max-width: 180px;
        overflow: hidden; text-overflow: ellipsis;
        --min-hotspot-opacity: 0.65;
    }
    .hotspot::before {
        content: ''; width: 8px; height: 8px; border-radius: 50%;
        background: var(--accent); flex: none;
    }
    .hotspot[slot^="hotspot"]:not([data-visible]) { opacity: .35; }
</style>
</head>
<body>
<noscript>
    {{-- JS-disabled visitors can't run model-viewer at all; send them straight
         to the standard biolink so the QR/NFC scan is never a dead end. --}}
    <meta http-equiv="refresh" content="0;url={{ $biolinkUrl }}?ar=unsupported&amp;reason=no_js">
</noscript>
<div class="wrap">
    <div class="topbar">
        <span class="brand">1INME · AR Card</span>
        <span class="badge" id="capBadge">Detecting…</span>
    </div>

    {{-- Why ar-modes is "webxr quick-look" (and NOT scene-viewer):
         - WebXR (Android Chrome / desktop XR browsers) supports model-viewer's
           HTML hotspots, so each <a slot="hotspot-N"> below is a real tappable
           link to /{alias}/b/{blockId}?source=ar in AR.
         - Scene Viewer renders the GLB natively and CAN'T host HTML hotspots,
           so a Scene Viewer launch would be visually rich but not tappable.
           Dropping it forces Android Chrome into WebXR (with hotspots).
         - Quick Look on iOS likewise renders USDZ natively without hotspots,
           but iOS doesn't expose WebXR — so Quick Look stays as the only
           AR path for iOS, with the in-page block list immediately under
           the viewer + a USDZ #callToAction that links the visitor back
           to the standard biolink as a single in-AR CTA. --}}
    <model-viewer id="card"
        src="{{ $glbUrl }}"
        ios-src="{{ $usdzUrl }}#callToAction=Open%20biolink&checkoutTitle=1INME&link={{ urlencode($biolinkUrl . '?source=ar') }}"
        alt="AR business card for {{ $cfg['display_name'] }}"
        ar
        ar-modes="webxr quick-look"
        ar-scale="auto"
        camera-controls
        touch-action="pan-y"
        auto-rotate
        auto-rotate-delay="800"
        rotation-per-second="20deg"
        interaction-prompt="auto"
        shadow-intensity="0.6"
        exposure="1.05"
        environment-image="neutral"
        poster="{{ route('ar.card.texture', $link->alias) }}">
        <button slot="ar-button" class="ar-cta" id="arBtn">View in your space</button>

        @php
            // Distribute up to 6 hotspots across the 1.6 (x) × 1.0 (y) card
            // face at z=0.02 (just floating in front), in a 2-col grid that
            // fills top-down, left-then-right. The card faces +Z so the
            // surface normal is (0, 0, 1) for every hotspot.
            $cols = [-0.42, 0.42];
            $rows = [0.32, 0.0, -0.32];
            $slots = [];
            foreach ($rows as $y) {
                foreach ($cols as $x) {
                    $slots[] = [$x, $y, 0.02];
                }
            }
        @endphp
        @foreach($blocks as $i => $b)
            @php
                $bs = $b->settings ?? [];
                $hLabel = $bs['title'] ?? $bs['text'] ?? $bs['label']
                    ?? ucfirst(str_replace('_', ' ', $b->type));
                [$hx, $hy, $hz] = $slots[$i] ?? [0, 0, 0.05];
                $hHref = url('/' . $link->alias . '/b/' . $b->id) . '?source=ar';
            @endphp
            <a class="hotspot"
               slot="hotspot-{{ $i }}"
               data-position="{{ $hx }}m {{ $hy }}m {{ $hz }}m"
               data-normal="0 0 1"
               data-visibility-attribute="visible"
               href="{{ $hHref }}"
               rel="nofollow noopener"
               data-block-id="{{ $b->id }}">{{ \Illuminate\Support\Str::limit($hLabel, 22) }}</a>
        @endforeach
    </model-viewer>

    <div class="name">{{ $cfg['display_name'] }}</div>
    @if($cfg['headline'])
        <div class="headline">{{ $cfg['headline'] }}</div>
    @endif

    <a href="{{ $biolinkUrl }}?source=ar" class="ar-cta" style="background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);">
        Open full biolink
    </a>

    @if($blocks->isNotEmpty())
    <div class="blocks">
        @foreach($blocks as $b)
            @php
                $bs = $b->settings ?? [];
                $label = $bs['title'] ?? $bs['text'] ?? $bs['label'] ?? ucfirst(str_replace('_',' ', $b->type));
                $sub = $bs['subtitle'] ?? $bs['url'] ?? $bs['link'] ?? '';
                $initial = strtoupper(mb_substr(trim($label), 0, 1));
                $href = url('/' . $link->alias . '/b/' . $b->id) . '?source=ar';
            @endphp
            <a class="blk" href="{{ $href }}" rel="nofollow noopener">
                <span class="ico">{{ $initial ?: '·' }}</span>
                <span style="flex:1; min-width:0;">
                    <span class="lbl" style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $label }}</span>
                    @if($sub)
                        <span class="sub" style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $sub }}</span>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
    @endif

    <div id="toast" class="toast" hidden></div>

    <div class="footer">
        Powered by <a href="{{ url('/') }}">1INME</a> · AR experiences for biolinks
    </div>
</div>

<script>
(function () {
    var mv = document.getElementById('card');
    var badge = document.getElementById('capBadge');
    var toast = document.getElementById('toast');

    function showToast(msg) {
        toast.textContent = msg;
        toast.hidden = false;
        setTimeout(function () { toast.hidden = true; }, 5500);
    }

    // Capability sniff. The page is an AR experience; if the device can't
    // activate AR we send the visitor straight to the standard biolink so
    // the QR/NFC scan still leads somewhere useful. Creators previewing
    // from the dashboard can pass ?preview=1 to stay on the 3D page.
    var ua = navigator.userAgent || '';
    var isIOS = /iPhone|iPad|iPod/i.test(ua);
    var isAndroid = /Android/i.test(ua);
    var qs = new URLSearchParams(location.search);
    var previewMode = qs.get('preview') === '1';
    var biolinkUrl = {!! json_encode($biolinkUrl) !!};

    function looksArCapable() {
        // iOS Safari supports Quick Look natively even before model-viewer
        // reports canActivateAR, so treat it as capable. Android needs an
        // ARCore-aware Chrome; model-viewer's canActivateAR is the source
        // of truth there. Desktop browsers fall back to WebXR detection.
        if (isIOS) return true;
        try { if (mv && mv.canActivateAR) return true; } catch (e) {}
        if (navigator.xr && typeof navigator.xr.isSessionSupported === 'function') {
            return navigator.xr.isSessionSupported('immersive-ar').catch(function () { return false; });
        }
        return false;
    }

    function fallbackToBiolink(reason) {
        if (previewMode) {
            badge.textContent = '3D preview';
            showToast("Preview mode — this browser can't open AR. Visitors would be sent to the biolink.");
            return;
        }
        // Server-rendered <noscript> link is a parallel safety net; this is
        // the JS path for capable browsers that just lack AR (e.g. desktop).
        var sep = biolinkUrl.indexOf('?') === -1 ? '?' : '&';
        location.replace(biolinkUrl + sep + 'ar=unsupported&reason=' + encodeURIComponent(reason || 'no_ar'));
    }

    setTimeout(function () {
        Promise.resolve(looksArCapable()).then(function (capable) {
            if (capable) {
                badge.textContent = isIOS ? 'iOS Quick Look' : (isAndroid ? 'Scene Viewer' : 'WebXR');
                return;
            }
            fallbackToBiolink('not_supported');
        });
    }, 600);

    // Fire a "source=ar" page session as soon as the viewer loads so analytics
    // see this hit as a distinct AR session, not a phantom direct visit.
    try {
        var fd = new FormData();
        fd.append('source', 'ar');
        fetch({!! json_encode(url('/' . $link->alias . '/track/session')) !!}, {
            method: 'POST', body: fd, credentials: 'same-origin', keepalive: true
        }).catch(function () {});
    } catch (e) {}

    mv.addEventListener('ar-status', function (e) {
        if (e.detail.status === 'failed') {
            // Capable on paper, refused at runtime — still send the user to
            // the standard biolink instead of a stranded 3D preview.
            fallbackToBiolink('ar_failed');
        }
    });
})();
</script>
</body>
</html>
