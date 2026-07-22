<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php($pageTitle = $digest->title . ' — ' . config('app.name'))
    @php($shareDescription = trim((string) ($digest->summary ?: ('A digest from ' . config('app.name')))))
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($shareDescription, 160) }}">
    @if(!empty($isPreview) || !$digest->isPublished())
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="canonical" href="{{ $digest->publicUrl() }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $digest->title }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($shareDescription, 200) }}">
    <meta property="og:url" content="{{ $digest->publicUrl() }}">
    @php($zdLogoUrl = \App\Services\ZioDigest\ZioDigestBranding::logoUrl())
    @if($digest->lead_image)
        <meta property="og:image" content="{{ $digest->lead_image }}">
    @else
        <meta property="og:image" content="{{ \App\Services\ZioDigest\ZioDigestBranding::logoAbsoluteUrl() }}">
    @endif
    <meta name="twitter:card" content="{{ $digest->lead_image ? 'summary_large_image' : 'summary' }}">
    @include('common.partials.theme-bootstrap')
    @include('common.partials.fontawesome')
    <style>
        :root { color-scheme: dark light; }
        body.zio-digest-page {
            margin: 0;
            font-family: 'Space Grotesk', Arial, Helvetica, sans-serif;
            background: #0b0b13;
            color: #e5e7eb;
            line-height: 1.65;
        }
        html.light-mode body.zio-digest-page { background: #f4f5f9; color: #1f2937; }
        .zd-wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px 64px; }
        .zd-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 18px;
            overflow: hidden;
        }
        html.light-mode .zd-card { background: #ffffff; border-color: #e5e7eb; }
        .zd-lead { display: block; width: 100%; height: auto; }
        .zd-body { padding: 32px 28px; }
        .zd-title { font-size: 30px; margin: 0 0 10px; color: #ffffff; }
        html.light-mode .zd-title { color: #111827; }
        .zd-summary { color: #9ca3af; margin: 0 0 24px; }
        html.light-mode .zd-summary { color: #4b5563; }
        .zd-block-heading { font-size: 21px; margin: 30px 0 8px; color: #ffffff; }
        html.light-mode .zd-block-heading { color: #111827; }
        .zd-block-text { margin: 0 0 16px; color: #d1d5db; }
        html.light-mode .zd-block-text { color: #374151; }
        .zd-block-image img { max-width: 100%; height: auto; border-radius: 12px; display: block; }
        .zd-caption { font-size: 12px; color: #9ca3af; margin: 6px 0 16px; }
        html.light-mode .zd-caption { color: #6b7280; }
        .zd-video, .zd-embed { position: relative; margin: 0 0 16px; }
        .zd-video iframe, .zd-embed iframe { width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: 12px; }
        .zd-video video { width: 100%; border-radius: 12px; }
        .zd-link-card {
            display: block; padding: 14px 18px; margin: 0 0 16px;
            border: 1px solid rgba(255,255,255,0.12); border-radius: 12px;
            text-decoration: none;
        }
        html.light-mode .zd-link-card { border-color: #e5e7eb; }
        .zd-link-card .t { color: #93c5fd; font-weight: 600; }
        html.light-mode .zd-link-card .t { color: #2563eb; }
        .zd-link-card .d { color: #9ca3af; font-size: 13px; margin-top: 3px; }
        html.light-mode .zd-link-card .d { color: #6b7280; }
        .zd-embed-fallback {
            display: block; padding: 14px 18px; margin: 0 0 16px;
            border: 1px dashed rgba(255,255,255,0.2); border-radius: 12px;
            color: #93c5fd; text-decoration: none;
        }
        html.light-mode .zd-embed-fallback { border-color: #d1d5db; color: #2563eb; }
        .zd-footer { padding: 18px 28px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 12px; color: #9ca3af; text-align: center; }
        html.light-mode .zd-footer { border-top-color: #e5e7eb; color: #6b7280; }
        .zd-preview-banner {
            background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.4);
            color: #fbbf24; padding: 8px 14px; border-radius: 10px;
            font-size: 13px; margin-bottom: 18px; text-align: center;
        }
        html.light-mode .zd-preview-banner { color: #92400e; }
        .zd-date { font-size: 12px; color: #9ca3af; margin-bottom: 18px; }
        html.light-mode .zd-date { color: #6b7280; }
        .zd-brand { display: flex; justify-content: center; margin: 0 0 22px; }
        .zd-brand-chip {
            display: inline-flex; align-items: center;
            background: rgba(255,255,255,0.92);
            border-radius: 14px; padding: 8px 16px;
        }
        html.light-mode .zd-brand-chip { background: transparent; padding: 0; border-radius: 0; }
        .zd-brand img { display: block; height: 44px; width: auto; max-width: 100%; }
        @media (max-width: 480px) {
            .zd-brand img { height: 34px; }
        }
    </style>
</head>
<body class="zio-digest-page">
    <div class="zd-wrap">
        <div class="zd-brand">
            <span class="zd-brand-chip"><img src="{{ $zdLogoUrl }}" alt="Zio Digest — Your Daily Dose of Smart Reads"></span>
        </div>
        @if(!empty($isPreview))
            <div class="zd-preview-banner"><i class="fas fa-eye"></i> Admin preview — {{ $digest->isPublished() ? 'this digest is live.' : 'this digest is a draft and hidden from the public.' }}</div>
        @endif
        <article class="zd-card">
            @if($digest->lead_image)
                <img class="zd-lead" src="{{ $digest->lead_image }}" alt="">
            @endif
            <div class="zd-body">
                <h1 class="zd-title">{{ $digest->title }}</h1>
                @if($digest->published_at)
                    <div class="zd-date">{{ $digest->published_at->format('F j, Y') }}</div>
                @endif
                @if($digest->summary)
                    <p class="zd-summary">{!! nl2br(e($digest->summary)) !!}</p>
                @endif

                @foreach((array) $digest->blocks as $block)
                    @include('common.partials.zio-digest-block', ['block' => (array) $block])
                @endforeach
            </div>
            <div class="zd-footer">
                {{ config('app.name') }} &middot; Zio Digest
            </div>
        </article>
    </div>
</body>
</html>
