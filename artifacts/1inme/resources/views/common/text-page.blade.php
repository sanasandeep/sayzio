{{--
  Public "Shared text" page for `text`-type links (created via Quick Shorten
  when the pasted content isn't a URL / email / phone).
  Rendered by RedirectController::handleTextPage().
  Theme-aware (dark default; html.light-mode flips colours).
--}}
<!DOCTYPE html>
<html lang="en"
      class="{{ $themeClass ?? '' }}"
      data-link-id="{{ $link->id }}">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($content, 150) }}">
    <meta name="robots" content="noindex">

    {{-- OG / social sharing --}}
    <meta property="og:title"       content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($content, 200) }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ $link->getShortUrl() }}">

    @if($link->seo_image)
    <meta property="og:image" content="{{ \App\Support\PublicStorageUrl::resolve($link->seo_image) }}">
    @endif

    @include('common.partials.theme-bootstrap')
    @include('common.partials.fontawesome')

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', system-ui, sans-serif;
            background: #0e0c1a;
            color: #e8e6f0;
        }
        html.light-mode body {
            background: #f7f7fb;
            color: #18172b;
        }
        .text-container { max-width: 720px; margin: 0 auto; padding: 48px 20px 80px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .page-heading { font-size: 1.15rem; font-weight: 700; margin: 0; line-height: 1.3; word-break: break-word; }
        html.light-mode .page-heading { color: #18172b; }

        .copy-btn {
            display: inline-flex; align-items: center; gap: 8px;
            font: inherit; font-size: .85rem; font-weight: 600;
            padding: 9px 16px; border-radius: 10px; cursor: pointer;
            color: #fff; background: #2563eb; border: 1px solid #2563eb;
            transition: background .15s ease;
        }
        .copy-btn:hover { background: #1d4ed8; }
        .copy-btn.copied { background: #059669; border-color: #059669; }

        .header-actions { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        a.download-btn {
            text-decoration: none;
            color: #e8e6f0; background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.16);
        }
        a.download-btn:hover { background: rgba(255,255,255,.13); }
        html.light-mode a.download-btn {
            color: #18172b; background: #fff;
            border-color: rgba(24,23,43,.16);
        }
        html.light-mode a.download-btn:hover { background: #f0eff6; }

        .text-card {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            padding: 24px;
        }
        html.light-mode .text-card {
            background: #fff;
            border-color: rgba(24,23,43,.09);
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }
        .text-content {
            margin: 0;
            font: inherit;
            font-size: .95rem;
            line-height: 1.7;
            white-space: pre-wrap;
            word-break: break-word;
            overflow-wrap: anywhere;
            color: rgba(255,255,255,.85);
            -webkit-user-select: text; user-select: text;
        }
        html.light-mode .text-content { color: rgba(24,23,43,.85); }

        .meta-row {
            display: flex; align-items: center; gap: 10px;
            margin-top: 20px; font-size: .78rem;
            color: rgba(255,255,255,.4);
        }
        html.light-mode .meta-row { color: rgba(24,23,43,.4); }
        .creator-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }

        .empty-state { text-align: center; padding: 60px 20px; opacity: .6; }
        .empty-state i { font-size: 2rem; margin-bottom: 12px; display: block; }
    </style>
</head>
<body>
<div class="text-container">
    <div class="page-header">
        <h1 class="page-heading">{{ $pageTitle }}</h1>
        @if($content !== '')
        <div class="header-actions">
            <a class="copy-btn download-btn"
               href="{{ url('/' . (request()->route('alias') ?? $link->alias) . '/download.txt') }}"
               download
               aria-label="Download as .txt">
                <i class="fa-solid fa-download" aria-hidden="true"></i>
                <span>Download .txt</span>
            </a>
            <button type="button" class="copy-btn" id="copy-text-btn" aria-label="Copy text">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                <span id="copy-text-label">Copy text</span>
            </button>
        </div>
        @endif
    </div>

    @if($content === '')
        <div class="empty-state">
            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
            Nothing to show here yet.
        </div>
    @else
        <div class="text-card">
            <pre class="text-content" id="shared-text">{{ $content }}</pre>
        </div>
    @endif

    <div class="meta-row">
        @if($creator)
            @if($creator->avatar)
                <img class="creator-avatar" src="{{ \App\Support\PublicStorageUrl::resolve($creator->avatar) }}" alt="">
            @endif
            <span>Shared by {{ $creator->name }}</span>
            <span aria-hidden="true">·</span>
        @endif
        <span>{{ $link->created_at?->format('M j, Y') }}</span>
    </div>
</div>

@if($content !== '')
<script>
(function () {
    var btn = document.getElementById('copy-text-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var text = document.getElementById('shared-text').textContent;
        var done = function () {
            btn.classList.add('copied');
            document.getElementById('copy-text-label').textContent = 'Copied!';
            setTimeout(function () {
                btn.classList.remove('copied');
                document.getElementById('copy-text-label').textContent = 'Copy text';
            }, 1800);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(); });
        } else {
            fallback();
        }
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            document.body.removeChild(ta);
        }
    });
})();
</script>
@endif
</body>
</html>
