@php
    /**
     * Self-contained embed card (task #2617). Rendered into a cross-origin
     * iframe on a third-party site, so it carries ALL of its own styling
     * inline and assumes no app chrome. Auto light/dark via prefers-color-scheme.
     *
     * @var string      $state     ok|gated|unavailable|missing
     * @var string      $alias
     * @var string      $title
     * @var string|null $subtitle
     * @var string|null $favicon
     * @var array|null  $action    ['label'=>..,'icon'=>..]
     * @var string      $url       canonical short URL (tracked)
     * @var string|null $badge
     */
    $icons = [
        'open'     => '<path d="M14 3h7v7"/><path d="M21 3l-9 9"/><path d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
        'download' => '<path d="M12 3v12"/><path d="M7 10l5 5 5-5"/><path d="M5 21h14"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v4M10 16h4"/>',
        'contact'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M5 17c0-2 2-3 4-3s4 1 4 3"/><path d="M15 9h4M15 13h4"/>',
    ];
    $iconPath = $action['icon'] ?? 'open';
    $iconSvg = $icons[$iconPath] ?? $icons['open'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <style>
        :root {
            --bg: #ffffff;
            --fg: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --accent: #2563eb;
            --accent-fg: #ffffff;
            --badge-bg: #f1f5f9;
            --badge-fg: #475569;
            --shadow: 0 1px 3px rgba(15, 23, 42, .08), 0 8px 24px rgba(15, 23, 42, .06);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0b1220;
                --fg: #e2e8f0;
                --muted: #94a3b8;
                --border: rgba(148, 163, 184, .18);
                --accent: #3b82f6;
                --accent-fg: #ffffff;
                --badge-bg: rgba(148, 163, 184, .12);
                --badge-fg: #cbd5e1;
                --shadow: 0 1px 3px rgba(0, 0, 0, .4), 0 8px 24px rgba(0, 0, 0, .35);
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: transparent; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--fg);
            -webkit-font-smoothing: antialiased;
        }
        .card {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 420px;
            margin: 4px auto;
        }
        .head { display: flex; align-items: center; gap: 12px; }
        .fav {
            width: 40px; height: 40px; border-radius: 10px; flex: 0 0 auto;
            object-fit: cover; background: var(--badge-bg);
            display: flex; align-items: center; justify-content: center;
            color: var(--muted); border: 1px solid var(--border);
        }
        .fav svg { width: 20px; height: 20px; }
        .meta { min-width: 0; flex: 1 1 auto; }
        .title {
            font-size: 15px; font-weight: 650; line-height: 1.3; margin: 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .subtitle {
            font-size: 12.5px; color: var(--muted); margin: 2px 0 0;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .badge {
            display: inline-block; font-size: 10.5px; font-weight: 600;
            letter-spacing: .02em; text-transform: uppercase;
            color: var(--badge-fg); background: var(--badge-bg);
            border-radius: 999px; padding: 3px 9px; margin-top: 4px;
        }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 11px 16px; border-radius: 11px;
            background: var(--accent); color: var(--accent-fg) !important;
            font-size: 14px; font-weight: 600; text-decoration: none;
            border: 0; cursor: pointer; transition: filter .15s ease;
        }
        .btn:hover { filter: brightness(1.06); }
        .btn svg { width: 17px; height: 17px; }
        .footnote { font-size: 10.5px; color: var(--muted); text-align: center; margin: 0; }
        .footnote a { color: var(--muted); text-decoration: none; }
        .footnote a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card" id="embed-card">
        <div class="head">
            <div class="fav">
                @if ($favicon)
                    <img src="{{ $favicon }}" alt="" style="width:40px;height:40px;border-radius:10px;object-fit:cover" onerror="this.style.display='none'">
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconSvg !!}</svg>
                @endif
            </div>
            <div class="meta">
                <p class="title">{{ $title }}</p>
                @if ($subtitle)
                    <p class="subtitle">{{ $subtitle }}</p>
                @endif
                @if ($badge)
                    <span class="badge">{{ $badge }}</span>
                @endif
            </div>
        </div>

        @if ($action)
            <a class="btn" href="{{ $url }}" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $iconSvg !!}</svg>
                <span>{{ $action['label'] }}</span>
            </a>
        @endif

        @if ($state === 'gated')
            <p class="footnote">Private link — open to view if you have access.</p>
        @elseif ($state === 'unavailable')
            <p class="footnote">This link is currently unavailable.</p>
        @endif
    </div>

    <script>
        (function () {
            var ALIAS = @json($alias);
            function postHeight() {
                var el = document.getElementById('embed-card');
                var h = el ? el.getBoundingClientRect().height + 8 : document.documentElement.scrollHeight;
                try {
                    parent.postMessage({ type: '1inme-embed-resize', alias: ALIAS, height: Math.ceil(h) }, '*');
                } catch (e) {}
            }
            window.addEventListener('load', postHeight);
            window.addEventListener('resize', postHeight);
            setTimeout(postHeight, 50);
            setTimeout(postHeight, 300);
        })();
    </script>
</body>
</html>
