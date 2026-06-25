@php
    use App\Modules\User\Support\QrCodeDesignSanitizer;
    /** @var \App\Modules\User\Models\Link $link */
    /** @var \App\Modules\User\Models\RestaurantTable $table */
    $accent = $menu->accent_color ?: '#3d6bff';
    $place = $link->title ?: $link->alias;

    // Reuse the shared QR Studio design vocabulary so table codes are styled
    // consistently with the rest of the platform (tinted with the menu accent).
    $design = QrCodeDesignSanitizer::defaultDesign();
    $design['fg_color'] = $accent;
    $design['corner_square_color'] = $accent;
    $design['corner_dot_color'] = $accent;
    foreach ($design['eye_corners'] as $i => $eye) {
        $design['eye_corners'][$i]['outer_color'] = $accent;
        $design['eye_corners'][$i]['inner_color'] = $accent;
    }

    // Server-side fallback used until the engine renders (and for no-JS / print).
    $svg = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(320)->margin(1)->color(...sscanf(ltrim($accent, '#'), '%02x%02x%02x'))->generate($url));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Table {{ $table->label }} · {{ $place }}</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#f3f4f6; color:#111; padding:28px; }
        .toolbar { max-width:420px; margin:0 auto 18px; display:flex; gap:10px; justify-content:center; }
        .btn { border:0; border-radius:10px; padding:11px 18px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn.primary { background:{{ $accent }}; color:#fff; }
        .btn.ghost { background:#fff; color:#374151; border:1px solid #d1d5db; }
        .card { max-width:420px; margin:0 auto; background:#fff; border:1px solid #e5e7eb; border-radius:20px; padding:34px 28px; text-align:center; box-shadow:0 10px 40px -16px rgba(0,0,0,.25); }
        .place { font-size:15px; font-weight:600; color:#6b7280; letter-spacing:.04em; text-transform:uppercase; }
        .table-label { font-size:40px; font-weight:800; margin:6px 0 4px; color:#111; }
        .scan { font-size:14px; color:#6b7280; margin-bottom:18px; }
        .qr { display:inline-block; padding:14px; border-radius:16px; background:#fff; }
        .qr svg { display:block; width:280px; height:280px; }
        .url { font-size:12px; color:#9ca3af; margin-top:16px; word-break:break-all; }
        .foot { margin-top:18px; font-size:12.5px; color:#6b7280; }
        @media print {
            body { background:#fff; padding:0; }
            .toolbar { display:none; }
            .card { box-shadow:none; border:none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn primary" onclick="window.print()">Print</button>
        <button class="btn ghost" onclick="window.close()">Close</button>
    </div>
    <div class="card">
        <div class="place">{{ $place }}</div>
        <div class="table-label">Table {{ $table->label }}</div>
        <div class="scan">Scan to view the menu &amp; order</div>
        <div class="qr" id="qr" data-url="{{ $url }}">{!! base64_decode($svg) !!}</div>
        <div class="url">{{ $url }}</div>
        <div class="foot">Pay your server directly — no app needed.</div>
    </div>

    {{-- qrcode-generator from CDN; QrStudio engine reads window.qrcode --}}
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script src="{{ asset('js/qr-studio/engine.js') }}?v={{ filemtime(public_path('js/qr-studio/engine.js')) }}"></script>
    <script>
        (function () {
            var design = @json($design);
            function optsFrom(d, data) {
                var f = d.frame || {};
                return {
                    data: data, errorCorrection: d.error_correction || 'M', modulePx: 7, margin: d.margin || 2,
                    dotShape: d.dot_style, outerEyeShape: d.corner_square_style, innerEyeShape: d.corner_dot_style,
                    fgColor: d.fg_color, bgColor: d.bg_color, transparentBg: !!d.transparent_bg,
                    cornerSquareColor: d.corner_square_color, cornerDotColor: d.corner_dot_color,
                    gradient: d.gradient, eyeOuterGradient: d.eye_outer_gradient, eyeInnerGradient: d.eye_inner_gradient,
                    bgGradient: d.bg_gradient, logos: { background:null, center:null, foreground:null },
                    hideDotsBehindLogo: false, qrRotation: 0, dropShadow: false,
                    frame: { template:'none' }, fontFamily: (f.font || 'Inter'),
                };
            }
            function draw() {
                if (!window.QrStudio) { setTimeout(draw, 120); return; }
                var el = document.getElementById('qr');
                try {
                    var result = window.QrStudio.render(optsFrom(design, el.getAttribute('data-url')));
                    if (result && result.svg) el.innerHTML = result.svg;
                } catch (e) { /* keep server-rendered fallback */ }
            }
            draw();
        })();
    </script>
</body>
</html>
