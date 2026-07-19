@extends('public.layouts.site')
@section('title', 'Download SayZio Browser')

@php
    $accent = '#3d6bff';
    /** @var array $release — resolved by ZioBrowserDownloadController (live GitHub release or pinned fallback). */
    $version = $release['version'] ?? '';

    // The three headline installers. `id` doubles as the JS platform key.
    $downloads = [
        [
            'id'    => 'mac-arm64',
            'icon'  => 'fa-brands fa-apple',
            'title' => 'Mac: Apple Silicon',
            'desc'  => 'For M1, M2, M3 and newer Macs.',
            'file'  => 'Download .dmg',
            'url'   => $release['mac_arm64_dmg'] ?? null,
            'alt'   => $release['mac_arm64_zip'] ?? null,
        ],
        [
            'id'    => 'mac-x64',
            'icon'  => 'fa-brands fa-apple',
            'title' => 'Mac: Intel',
            'desc'  => 'For Intel-based Macs.',
            'file'  => 'Download .dmg',
            'url'   => $release['mac_x64_dmg'] ?? null,
            'alt'   => $release['mac_x64_zip'] ?? null,
        ],
        [
            'id'    => 'windows',
            'icon'  => 'fa-brands fa-windows',
            'title' => 'Windows',
            'desc'  => 'For Windows 10 and 11 (64-bit).',
            'file'  => 'Download installer (.exe)',
            'url'   => $release['windows_exe'] ?? null,
            'alt'   => null,
        ],
    ];
    $downloads = array_values(array_filter($downloads, fn ($d) => !empty($d['url'])));

    $features = [
        ['icon' => 'fa-bolt',        'title' => 'Built for creators', 'desc' => 'Your Sayzio dashboard, links, analytics and inbox one click away, in a fast, distraction-free desktop browser.'],
        ['icon' => 'fa-rotate',      'title' => 'Always up to date',  'desc' => 'On Windows, the app updates itself automatically. On Mac, grab new versions from this page for now; auto-updates arrive once builds are signed.'],
        ['icon' => 'fa-shield-halved','title' => 'Private by design', 'desc' => 'No trackers, no bloat. Just a clean browsing experience with Sayzio built in.'],
    ];
@endphp

@section('content')
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
              style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: #bccfff;">
            <i class="fas fa-download text-[10px]"></i> SayZio Browser
        </span>
        <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
            Get SayZio on your desktop.
            <span class="block grad-text">Download the browser.</span>
        </h1>
        <p class="mt-5 text-lg text-gray-400 max-w-2xl mx-auto leading-relaxed">
            The SayZio Browser puts your links, Link in Bio pages and analytics front and centre, available for Mac and Windows.
            @if($version)
                <span class="block mt-2 text-sm text-gray-500">Latest version: v{{ $version }}</span>
            @endif
        </p>

        {{-- Primary, platform-detected button (JS swaps the target; no-JS falls back to the grid below). --}}
        @if(count($downloads))
            <div class="mt-8 flex flex-col items-center gap-3" data-anim="fade-up">
                <a id="zio-primary-download" href="{{ $downloads[0]['url'] }}" data-platform="{{ $downloads[0]['id'] }}"
                   class="inline-flex items-center gap-3 px-8 py-4 rounded-2xl text-white font-semibold text-lg shadow-lg transition-transform hover:scale-[1.02]"
                   style="background: linear-gradient(135deg, {{ $accent }}, #274ecf);">
                    <i id="zio-primary-icon" class="{{ $downloads[0]['icon'] }} text-xl"></i>
                    <span id="zio-primary-label">Download for {{ $downloads[0]['title'] }}</span>
                </a>
                <p class="text-sm text-gray-500">Free download · Not your device? Pick another installer below.</p>
            </div>
        @endif
    </div>
</section>

<section class="relative pb-16 lg:pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($downloads as $d)
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 flex flex-col" data-zio-card="{{ $d['id'] }}">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl flex items-center justify-center text-xl"
                              style="background: {{ $accent }}1a; color: #9db8ff;">
                            <i class="{{ $d['icon'] }}"></i>
                        </span>
                        <div>
                            <h3 class="font-semibold">{{ $d['title'] }}</h3>
                            <p class="text-xs text-gray-500">{{ $d['desc'] }}</p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col gap-2">
                        <a href="{{ $d['url'] }}"
                           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-medium text-sm text-white transition-colors"
                           style="background: {{ $accent }};">
                            <i class="fas fa-download text-xs"></i> {{ $d['file'] }}
                        </a>
                        @if(!empty($d['alt']))
                            <a href="{{ $d['alt'] }}" class="text-xs text-gray-500 hover:text-gray-300 text-center transition-colors">
                                Or download as .zip
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if(!count($downloads))
            <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center text-gray-400">
                Downloads are temporarily unavailable. Please check back shortly or grab the installers from
                <a href="https://github.com/sanasandeep/sayzio/releases" class="underline hover:text-gray-200">our releases page</a>.
            </div>
        @endif

        <div class="mt-14 grid sm:grid-cols-3 gap-5">
            @foreach($features as $f)
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center"
                          style="background: {{ $accent }}1a; color: #9db8ff;">
                        <i class="fas {{ $f['icon'] }}"></i>
                    </span>
                    <h3 class="mt-4 font-semibold">{{ $f['title'] }}</h3>
                    <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $f['desc'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-10 text-center text-xs text-gray-600">
            Windows keeps itself up to date automatically. macOS installers are unsigned for now, so Mac updates are manual
            (re-download from this page) until signing lands; if macOS warns on first open, right-click the app and choose “Open”.
            Windows SmartScreen may ask you to confirm with “More info → Run anyway”.
        </p>
    </div>
</section>

<script>
    // Platform detection for the hero button: swap the primary download to
    // the visitor's OS/architecture. Falls back to the server-rendered
    // default when detection is inconclusive.
    (function () {
        var btn = document.getElementById('zio-primary-download');
        if (!btn) return;
        var targets = {};
        document.querySelectorAll('[data-zio-card]').forEach(function (card) {
            var link = card.querySelector('a[href]');
            var title = card.querySelector('h3');
            if (link) targets[card.getAttribute('data-zio-card')] = { url: link.getAttribute('href'), title: title ? title.textContent : '' };
        });

        function apply(key, isWin) {
            var t = targets[key];
            if (!t) return;
            btn.setAttribute('href', t.url);
            btn.setAttribute('data-platform', key);
            var label = document.getElementById('zio-primary-label');
            if (label) label.textContent = 'Download for ' + t.title;
            var icon = document.getElementById('zio-primary-icon');
            if (icon) icon.className = (isWin ? 'fa-brands fa-windows' : 'fa-brands fa-apple') + ' text-xl';
        }

        var ua = navigator.userAgent || '';
        if (/Windows/i.test(ua)) { apply('windows', true); return; }
        if (!/Macintosh|Mac OS X/i.test(ua)) return; // Linux/mobile: keep default

        // Apple Silicon vs Intel: the UA lies (always says Intel), so probe
        // WebGL renderer strings; default to Apple Silicon (the common case
        // on current hardware) when the probe is inconclusive.
        var isArm = true;
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                var ext = gl.getExtension('WEBGL_debug_renderer_info');
                var renderer = ext ? (gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '') : '';
                if (/\b(Intel|AMD|Radeon|Iris|NVIDIA)\b/i.test(renderer) && !/Apple/i.test(renderer)) isArm = false;
            }
        } catch (e) { /* keep default */ }
        apply(isArm ? 'mac-arm64' : 'mac-x64', false);
    })();
</script>
@endsection
