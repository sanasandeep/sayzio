{{--
  Public Creator Updates / Changelog page.
  Rendered by RedirectController::handleUpdatesPage().
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
    <meta name="description" content="{{ $settings['subheading'] ?? '' }}">

    {{-- OG / social sharing --}}
    <meta property="og:title"       content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $settings['subheading'] ?? '' }}">
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
        .updates-container { max-width: 720px; margin: 0 auto; padding: 48px 20px 80px; }
        .page-header { margin-bottom: 48px; }
        .page-heading { font-size: 2rem; font-weight: 700; margin: 0 0 8px; line-height: 1.2; }
        html.light-mode .page-heading { color: #18172b; }
        .page-subheading { font-size: 1rem; color: rgba(255,255,255,.55); margin: 0 0 20px; }
        html.light-mode .page-subheading { color: rgba(24,23,43,.55); }
        .creator-row { display: flex; align-items: center; gap: 10px; }
        .creator-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
        .creator-name { font-size: .875rem; font-weight: 600; }
        html.light-mode .creator-name { color: #18172b; }
        .creator-handle { font-size: .8rem; color: rgba(255,255,255,.4); }
        html.light-mode .creator-handle { color: rgba(24,23,43,.4); }

        .entry-list { display: flex; flex-direction: column; gap: 24px; }
        .entry {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 16px;
            overflow: hidden;
            scroll-margin-top: 80px;
        }
        html.light-mode .entry {
            background: #fff;
            border-color: rgba(24,23,43,.09);
            box-shadow: 0 1px 6px rgba(0,0,0,.06);
        }
        .entry-cover { width: 100%; max-height: 280px; object-fit: cover; display: block; }
        .entry-body { padding: 24px; }
        .entry-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; }
        .tag-badge {
            font-size: .7rem; font-weight: 600; letter-spacing: .04em;
            padding: 2px 9px; border-radius: 999px; border: 1px solid;
        }
        .tag-New          { background: rgba(16,185,129,.15); color: #6ee7b7; border-color: rgba(16,185,129,.3); }
        .tag-Improvement  { background: rgba(59,130,246,.15); color: #93c5fd; border-color: rgba(59,130,246,.3); }
        .tag-Fix          { background: rgba(245,158,11,.15); color: #fcd34d; border-color: rgba(245,158,11,.3); }
        .tag-Announcement { background: rgba(14,165,233,.15); color: #7dd3fc; border-color: rgba(14,165,233,.3); }
        .tag-Breaking     { background: rgba(239,68,68,.15);  color: #fca5a5; border-color: rgba(239,68,68,.3); }
        .tag-Deprecation  { background: rgba(244,63,94,.15);  color: #fda4af; border-color: rgba(244,63,94,.3); }
        .tag-Security     { background: rgba(249,115,22,.15); color: #fdba74; border-color: rgba(249,115,22,.3); }
        html.light-mode .tag-New { background: rgba(16,185,129,.1); color: #059669; border-color: rgba(16,185,129,.35); }
        html.light-mode .tag-Improvement { background: rgba(59,130,246,.1); color: #2563eb; border-color: rgba(59,130,246,.35); }
        html.light-mode .tag-Fix { background: rgba(245,158,11,.1); color: #d97706; border-color: rgba(245,158,11,.35); }
        html.light-mode .tag-Announcement { background: rgba(14,165,233,.1); color: #0284c7; border-color: rgba(14,165,233,.35); }
        html.light-mode .tag-Breaking { background: rgba(239,68,68,.1); color: #dc2626; border-color: rgba(239,68,68,.35); }
        html.light-mode .tag-Deprecation { background: rgba(244,63,94,.1); color: #e11d48; border-color: rgba(244,63,94,.35); }
        html.light-mode .tag-Security { background: rgba(249,115,22,.1); color: #ea580c; border-color: rgba(249,115,22,.35); }

        .entry-date { font-size: .78rem; color: rgba(255,255,255,.4); }
        html.light-mode .entry-date { color: rgba(24,23,43,.4); }
        .entry-anchor { font-size: .72rem; color: rgba(255,255,255,.25); text-decoration: none; }
        html.light-mode .entry-anchor { color: rgba(24,23,43,.25); }
        .entry-anchor:hover { color: rgba(255,255,255,.5); }
        html.light-mode .entry-anchor:hover { color: rgba(24,23,43,.5); }
        .entry-title { font-size: 1.2rem; font-weight: 700; margin: 0 0 10px; line-height: 1.3; }
        html.light-mode .entry-title { color: #18172b; }
        .entry-content { font-size: .92rem; line-height: 1.7; color: rgba(255,255,255,.7); }
        html.light-mode .entry-content { color: rgba(24,23,43,.7); }
        .entry-content p { margin: 0 0 .8em; }
        .entry-content p:last-child { margin-bottom: 0; }
        .entry-content a { color: #818cf8; }
        html.light-mode .entry-content a { color: #4f46e5; }
        .entry-content code { background: rgba(255,255,255,.08); padding: 1px 5px; border-radius: 4px; font-size: .85em; }
        html.light-mode .entry-content code { background: rgba(24,23,43,.07); }
        .entry-content pre { background: rgba(0,0,0,.3); border-radius: 10px; padding: 12px 16px; overflow-x: auto; }
        html.light-mode .entry-content pre { background: rgba(24,23,43,.05); }
        .entry-content blockquote { border-left: 3px solid rgba(255,255,255,.15); padding-left: 12px; margin: 8px 0; color: rgba(255,255,255,.5); }
        html.light-mode .entry-content blockquote { border-color: rgba(24,23,43,.2); color: rgba(24,23,43,.5); }

        .empty-state { text-align: center; padding: 60px 20px; opacity: .6; }
        .empty-state i { font-size: 2rem; margin-bottom: 12px; display: block; }

        /* Pagination */
        .pagination { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 40px; flex-wrap: wrap; }
        .pagination a, .pagination span {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 8px; font-size: .85rem;
            text-decoration: none; border: 1px solid rgba(255,255,255,.12);
        }
        .pagination a { color: rgba(255,255,255,.7); background: rgba(255,255,255,.05); }
        .pagination a:hover { background: rgba(255,255,255,.1); }
        .pagination span.current { background: var(--color-primary-600, #2563eb); border-color: var(--color-primary-600, #2563eb); color: #fff; }
        .pagination span.dots { border: none; background: none; color: rgba(255,255,255,.3); width: auto; }
        html.light-mode .pagination a, html.light-mode .pagination span { border-color: rgba(24,23,43,.12); }
        html.light-mode .pagination a { color: rgba(24,23,43,.7); border-color: rgba(24,23,43,.12); background: #fff; }
        html.light-mode .pagination a:hover { background: rgba(24,23,43,.06); }
        html.light-mode .pagination span.current { background: var(--color-primary-600, #2563eb); border-color: var(--color-primary-600, #2563eb); color: #fff; }
        html.light-mode .pagination span.dots { color: rgba(24,23,43,.3); }

        /* Tracking pixels --*/
        .pixel-container { display: none; }

        @media (max-width: 600px) {
            .updates-container { padding: 28px 14px 60px; }
            .page-heading { font-size: 1.5rem; }
            .entry-body { padding: 16px; }
        }
    </style>

    {{-- Tracking pixels --}}
    @if(!empty($link->pixels) && $link->pixels->isNotEmpty())
    @include('common.partials.tracking-pixels', ['pixels' => $link->pixels, 'event' => 'PageView'])
    @endif

    {{-- Custom head code (plan-gated) --}}
    @if(!empty($link->settings['custom_head']))
    {!! $link->settings['custom_head'] !!}
    @endif
</head>
<body>
<div class="updates-container">

    {{-- Page header --}}
    <header class="page-header">
        @if($creator)
        <div class="creator-row" style="margin-bottom:20px">
            @if($creator->avatar)
            <img src="{{ \App\Support\PublicStorageUrl::resolve($creator->avatar) }}"
                 alt="{{ $creator->name }}"
                 class="creator-avatar">
            @endif
            <div>
                <div class="creator-name">{{ $creator->name }}</div>
                @if($creator->handle)
                <div class="creator-handle">@{{ $creator->handle }}</div>
                @endif
            </div>
        </div>
        @endif

        <h1 class="page-heading">{{ $settings['heading'] }}</h1>
        @if(!empty($settings['subheading']))
        <p class="page-subheading">{{ $settings['subheading'] }}</p>
        @endif
    </header>

    {{-- Entry list --}}
    @if($entries->isEmpty())
    <div class="empty-state">
        <i class="fa fa-bullhorn"></i>
        <p>No updates posted yet.</p>
    </div>
    @else
    <div class="entry-list">
        @foreach($entries as $entry)
        <article class="entry" id="{{ $entry->anchorId() }}">
            @if($entry->image)
            <img src="{{ \App\Support\PublicStorageUrl::resolve($entry->image) }}"
                 alt="{{ $entry->title }}"
                 class="entry-cover"
                 loading="lazy">
            @endif
            <div class="entry-body">
                <div class="entry-meta">
                    @if($entry->tag)
                    <span class="tag-badge tag-{{ str_replace(' ', '-', $entry->tag) }}">{{ $entry->tag }}</span>
                    @endif
                    <span class="entry-date">
                        <i class="fa fa-calendar-alt" style="font-size:.7rem;opacity:.6"></i>
                        {{ $entry->published_date?->format('F j, Y') }}
                    </span>
                    <a href="#{{ $entry->anchorId() }}" class="entry-anchor"
                       title="Permalink to this entry">
                        <i class="fa fa-link" style="font-size:.65rem"></i>
                    </a>
                </div>
                <h2 class="entry-title">{{ $entry->title }}</h2>
                @if($entry->body)
                <div class="entry-content">{!! $entry->body !!}</div>
                @endif
            </div>
        </article>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($entries->hasPages())
    <nav class="pagination" aria-label="Entries pagination">
        @if($entries->onFirstPage())
            <span>‹</span>
        @else
            <a href="{{ $entries->previousPageUrl() }}" aria-label="Previous">‹</a>
        @endif

        @foreach($entries->getUrlRange(1, $entries->lastPage()) as $page => $url)
            @if($page == $entries->currentPage())
                <span class="current">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($entries->hasMorePages())
            <a href="{{ $entries->nextPageUrl() }}" aria-label="Next">›</a>
        @else
            <span>›</span>
        @endif
    </nav>
    @endif
    @endif

</div>
</body>
</html>
