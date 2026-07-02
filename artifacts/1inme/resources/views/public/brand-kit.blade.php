@php
    $p = $config['palette'] ?? [];
    $f = $config['fonts'] ?? [];
    $v = $config['voice'] ?? [];
    $sec = $config['sections'] ?? [];
    $primary = $p['primary'] ?: '#3d6bff';
    $secondary = $p['secondary'] ?: '';
    $accent = $p['accent'] ?: $primary;
    $neutrals = array_values($p['neutrals'] ?? []);
    $headingFont = $f['heading'] ?: 'Inter';
    $bodyFont = $f['body'] ?: 'Inter';
    $brandName = $config['brand_name'] ?: $creator->name;
    $logos = array_values($config['logos'] ?? []);
    $socials = array_values($config['socials'] ?? []);
    $taglines = array_values($config['taglines'] ?? []);
    $descriptors = array_values($v['descriptors'] ?? []);
    // Swatches we render in the colour grid (named + neutrals), de-duped on hex.
    $swatches = [];
    foreach ([['Primary', $primary], ['Secondary', $secondary], ['Accent', $accent]] as $row) {
        if ($row[1]) { $swatches[] = ['label' => $row[0], 'hex' => $row[1]]; }
    }
    foreach ($neutrals as $i => $n) {
        if ($n) { $swatches[] = ['label' => 'Neutral ' . ($i + 1), 'hex' => $n]; }
    }
    $fontParam = urlencode($headingFont) . ':wght@400;600;700;800';
    if (strcasecmp($headingFont, $bodyFont) !== 0) {
        $fontParam .= '&family=' . urlencode($bodyFont) . ':wght@400;500;600';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $link->title ?: ($brandName . ' — Brand & Press Kit') }} - {{ config('app.name') }}</title>
<meta name="description" content="{{ Str::limit($config['tagline'] ?: $config['about'] ?: ($brandName . ' brand & press kit'), 180) }}">
<meta property="og:title" content="{{ $brandName }} — Brand & Press Kit">
<meta property="og:description" content="{{ Str::limit($config['tagline'] ?: $config['about'] ?: ('Brand assets for ' . $brandName), 180) }}">
<meta property="og:type" content="website">
@if(!empty($logos[0]['url']))<meta property="og:image" content="{{ $logos[0]['url'] }}">@endif
<link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family={{ $fontParam }}&display=swap" rel="stylesheet">
<style>
    :root{
        --bk-page-bg: {{ $template['page_bg'] }};
        --bk-text: {{ $template['text'] }};
        --bk-muted: {{ $template['text_muted'] }};
        --bk-card-bg: {{ $template['card_bg'] }};
        --bk-card-border: {{ $template['card_border'] }};
        --bk-radius: {{ $template['radius'] }};
        --bk-primary: {{ $primary }};
        --bk-accent: {{ $accent }};
        --bk-heading: '{{ $headingFont }}', system-ui, sans-serif;
        --bk-body: '{{ $bodyFont }}', system-ui, sans-serif;
    }
    *{box-sizing:border-box;}
    body{margin:0; background:var(--bk-page-bg); color:var(--bk-text); font-family:var(--bk-body); -webkit-font-smoothing:antialiased; line-height:1.55;}
    h1,h2,h3{font-family:var(--bk-heading); margin:0; line-height:1.15;}
    a{color:inherit;}
    .bk-wrap{max-width:920px; margin:0 auto; padding:48px 20px 80px;}
    .bk-muted{color:var(--bk-muted);}
    .bk-card{background:var(--bk-card-bg); border:1px solid var(--bk-card-border); border-radius:var(--bk-radius); padding:24px;}
    .bk-section{margin-top:28px;}
    .bk-section-title{font-size:13px; text-transform:uppercase; letter-spacing:.12em; color:var(--bk-muted); margin-bottom:14px; font-family:var(--bk-body); font-weight:600;}

    .bk-hero h1{font-size:clamp(32px,6vw,52px); font-weight:800;}
    .bk-hero .bk-tag{font-size:clamp(15px,2.4vw,20px); color:var(--bk-muted); margin-top:10px;}
    .bk-chip-row{display:flex; flex-wrap:wrap; gap:8px; margin-top:18px;}
    .bk-chip{font-size:12px; padding:5px 12px; border-radius:999px; background:color-mix(in srgb, var(--bk-accent) 18%, transparent); color:var(--bk-text); border:1px solid color-mix(in srgb, var(--bk-accent) 35%, transparent);}

    .bk-grid{display:grid; gap:14px;}
    .bk-grid.cols-logos{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));}
    .bk-grid.cols-swatch{grid-template-columns:repeat(auto-fill,minmax(140px,1fr));}
    .bk-grid.cols-social{grid-template-columns:repeat(auto-fill,minmax(180px,1fr));}

    .bk-logo{display:flex; flex-direction:column; gap:12px; align-items:center; text-align:center;}
    .bk-logo-prev{width:100%; height:120px; border-radius:calc(var(--bk-radius) - 6px); background:#ffffff; display:flex; align-items:center; justify-content:center; overflow:hidden; border:1px solid var(--bk-card-border);}
    .bk-logo-prev img{max-width:80%; max-height:80%; object-fit:contain;}
    .bk-dl{display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:600; padding:8px 14px; border-radius:999px; background:var(--bk-accent); color:#fff; text-decoration:none;}

    .bk-swatch{cursor:pointer; border:1px solid var(--bk-card-border); border-radius:calc(var(--bk-radius) - 6px); overflow:hidden; background:var(--bk-card-bg); transition:transform .12s;}
    .bk-swatch:hover{transform:translateY(-2px);}
    .bk-swatch-color{height:78px;}
    .bk-swatch-meta{padding:9px 11px;}
    .bk-swatch-label{font-size:12px; font-weight:600;}
    .bk-swatch-hex{font-size:12px; color:var(--bk-muted); font-family:ui-monospace,SFMono-Regular,Menlo,monospace; display:flex; align-items:center; gap:6px;}

    .bk-font{display:flex; flex-direction:column; gap:6px;}
    .bk-font + .bk-font{margin-top:18px; padding-top:18px; border-top:1px solid var(--bk-card-border);}
    .bk-font-role{font-size:11px; text-transform:uppercase; letter-spacing:.1em; color:var(--bk-muted);}
    .bk-font-name{font-size:14px; color:var(--bk-muted);}

    .bk-social{display:flex; align-items:center; gap:10px; padding:12px 14px; text-decoration:none; background:var(--bk-card-bg); border:1px solid var(--bk-card-border); border-radius:calc(var(--bk-radius) - 6px); font-size:14px; font-weight:600;}
    .bk-social i{color:var(--bk-accent);}

    .bk-copybtn{cursor:pointer; background:none; border:none; color:inherit; font:inherit; padding:0; display:inline-flex; align-items:center; gap:6px;}
    .bk-boiler{white-space:pre-wrap;}
    .bk-contact a{color:var(--bk-accent); text-decoration:none; font-weight:600;}
    .bk-foot{margin-top:48px; text-align:center; font-size:12px; color:var(--bk-muted);}
    .bk-toast{position:fixed; left:50%; bottom:28px; transform:translateX(-50%) translateY(20px); background:var(--bk-accent); color:#fff; padding:10px 18px; border-radius:999px; font-size:13px; font-weight:600; opacity:0; pointer-events:none; transition:all .25s;}
    .bk-toast.show{opacity:1; transform:translateX(-50%) translateY(0);}
</style>
</head>
<body>
<div class="bk-wrap">

    {{-- Hero --}}
    <header class="bk-hero">
        <h1>{{ $brandName }}</h1>
        @if($config['tagline'])<p class="bk-tag">{{ $config['tagline'] }}</p>@endif
        @if(($sec['voice'] ?? true) && count($descriptors))
        <div class="bk-chip-row">
            @foreach($descriptors as $d)<span class="bk-chip">{{ $d }}</span>@endforeach
        </div>
        @endif
    </header>

    {{-- About / boilerplate --}}
    @if(($sec['about'] ?? true) && ($config['about'] || $config['boilerplate']))
    <section class="bk-section">
        <div class="bk-section-title">About</div>
        <div class="bk-card">
            @if($config['about'])<p style="margin:0 0 {{ $config['boilerplate'] ? '16px' : '0' }};">{{ $config['about'] }}</p>@endif
            @if($config['boilerplate'])
                <div style="border-top:1px solid var(--bk-card-border); padding-top:16px; margin-top:4px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <span class="bk-section-title" style="margin:0;">Press boilerplate</span>
                        <button class="bk-copybtn bk-muted" style="font-size:12px;" data-copy="{{ e($config['boilerplate']) }}"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <p class="bk-boiler" style="margin:0;">{{ $config['boilerplate'] }}</p>
                </div>
            @endif
        </div>
    </section>
    @endif

    {{-- Logos --}}
    @if(($sec['logos'] ?? true) && count($logos))
    <section class="bk-section">
        <div class="bk-section-title">Logos</div>
        <div class="bk-grid cols-logos">
            @foreach($logos as $logo)
            <div class="bk-card bk-logo">
                <div class="bk-logo-prev"><img src="{{ $logo['url'] }}" alt="{{ $logo['label'] ?: 'Logo' }}" loading="lazy"></div>
                <a class="bk-dl" href="{{ $logo['url'] }}" download target="_blank" rel="noopener"><i class="fas fa-download"></i> {{ $logo['label'] ?: 'Download' }}</a>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Colours --}}
    @if(($sec['colors'] ?? true) && count($swatches))
    <section class="bk-section">
        <div class="bk-section-title">Colour palette</div>
        <div class="bk-grid cols-swatch">
            @foreach($swatches as $sw)
            <div class="bk-swatch" data-copy="{{ $sw['hex'] }}" title="Click to copy {{ $sw['hex'] }}">
                <div class="bk-swatch-color" style="background:{{ $sw['hex'] }};"></div>
                <div class="bk-swatch-meta">
                    <div class="bk-swatch-label">{{ $sw['label'] }}</div>
                    <div class="bk-swatch-hex"><i class="fas fa-copy"></i> {{ strtoupper($sw['hex']) }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Fonts --}}
    @if(($sec['fonts'] ?? true))
    <section class="bk-section">
        <div class="bk-section-title">Typography</div>
        <div class="bk-card">
            <div class="bk-font">
                <span class="bk-font-role">Heading</span>
                <span style="font-family:var(--bk-heading); font-size:30px; font-weight:700;">{{ $headingFont }}</span>
                <span class="bk-font-name">Aa Bb Cc — The quick brown fox</span>
            </div>
            <div class="bk-font">
                <span class="bk-font-role">Body</span>
                <span style="font-family:var(--bk-body); font-size:20px;">{{ $bodyFont }}</span>
                <span class="bk-font-name">The quick brown fox jumps over the lazy dog.</span>
            </div>
        </div>
    </section>
    @endif

    {{-- Voice + taglines --}}
    @if(($sec['voice'] ?? true) && ($v['tone'] || count($taglines)))
    <section class="bk-section">
        <div class="bk-section-title">Brand voice</div>
        <div class="bk-card">
            @if($v['tone'])<p style="margin:0 0 {{ count($taglines) ? '16px' : '0' }};"><strong>Tone:</strong> {{ $v['tone'] }}</p>@endif
            @if(count($taglines))
            <div style="@if($v['tone'])border-top:1px solid var(--bk-card-border); padding-top:16px;@endif">
                <span class="bk-section-title" style="display:block; margin-bottom:10px;">Taglines</span>
                <ul style="margin:0; padding-left:18px;">
                    @foreach($taglines as $t)<li style="margin-bottom:6px;">{{ $t }}</li>@endforeach
                </ul>
            </div>
            @endif
        </div>
    </section>
    @endif

    {{-- Socials --}}
    @if(($sec['socials'] ?? true) && count($socials))
    <section class="bk-section">
        <div class="bk-section-title">Find us</div>
        <div class="bk-grid cols-social">
            @foreach($socials as $s)
            <a class="bk-social" href="{{ $s['url'] }}" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> {{ $s['label'] ?: $s['url'] }}</a>
            @endforeach
        </div>
    </section>
    @endif

    {{-- Contact --}}
    @if(($sec['contact'] ?? true) && ($config['contact_email'] || $config['contact_url']))
    <section class="bk-section">
        <div class="bk-section-title">Press & contact</div>
        <div class="bk-card bk-contact">
            @if($config['contact_email'])<p style="margin:0 0 {{ $config['contact_url'] ? '8px' : '0' }};"><i class="fas fa-envelope bk-muted"></i> <a href="mailto:{{ $config['contact_email'] }}">{{ $config['contact_email'] }}</a></p>@endif
            @if($config['contact_url'])<p style="margin:0;"><i class="fas fa-globe bk-muted"></i> <a href="{{ $config['contact_url'] }}" target="_blank" rel="noopener">{{ $config['contact_url'] }}</a></p>@endif
        </div>
    </section>
    @endif

    <footer class="bk-foot">
        Brand &amp; Press Kit &middot; powered by {{ config('app.name') }}
    </footer>
</div>

<div class="bk-toast" id="bk-toast">Copied!</div>

<script>
(function(){
    var toast = document.getElementById('bk-toast');
    var t;
    function showToast(msg){
        toast.textContent = msg || 'Copied!';
        toast.classList.add('show');
        clearTimeout(t);
        t = setTimeout(function(){ toast.classList.remove('show'); }, 1400);
    }
    function copy(text){
        if (navigator.clipboard && window.isSecureContext){
            navigator.clipboard.writeText(text).then(function(){ showToast('Copied ' + text); }).catch(function(){ fallback(text); });
        } else { fallback(text); }
    }
    function fallback(text){
        var el = document.createElement('textarea');
        el.value = text; el.style.position = 'fixed'; el.style.opacity = '0';
        document.body.appendChild(el); el.select();
        try { document.execCommand('copy'); showToast('Copied ' + text); } catch(e){}
        document.body.removeChild(el);
    }
    document.querySelectorAll('[data-copy]').forEach(function(node){
        node.addEventListener('click', function(){ copy(node.getAttribute('data-copy')); });
    });
})();
</script>
</body>
</html>
