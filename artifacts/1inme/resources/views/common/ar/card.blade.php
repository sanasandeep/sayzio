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
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <span class="brand">1INME · AR Card</span>
        <span class="badge" id="capBadge">Detecting…</span>
    </div>

    <model-viewer id="card"
        src="{{ $glbUrl }}"
        ios-src="{{ $usdzUrl }}"
        alt="AR business card for {{ $cfg['display_name'] }}"
        ar
        ar-modes="webxr scene-viewer quick-look"
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

    // Capability sniff for the badge
    var ua = navigator.userAgent || '';
    var isIOS = /iPhone|iPad|iPod/i.test(ua);
    var isAndroid = /Android/i.test(ua);
    var supports = false;
    try { supports = !!(mv && mv.canActivateAR); } catch (e) {}
    setTimeout(function () {
        if (mv.canActivateAR) {
            badge.textContent = isIOS ? 'iOS Quick Look' : (isAndroid ? 'Scene Viewer' : 'WebXR');
        } else if (isIOS) {
            badge.textContent = 'Tap viewer';
        } else {
            badge.textContent = '3D preview';
            showToast("This browser can't open the AR overlay — you can still rotate the card and tap any link below.");
        }
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
            showToast("Couldn't open AR on this device. Tap any link below to continue.");
        }
    });
})();
</script>
</body>
</html>
