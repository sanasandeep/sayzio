@php
    // One Zio Digest content block on the public page. All user-supplied
    // values are escaped; embed/video URLs are only ever iframed when they
    // match a known provider pattern, otherwise we fall back to a plain link.
    $type = (string) ($block['type'] ?? '');
    $text = (string) ($block['text'] ?? '');
    $url  = trim((string) ($block['url'] ?? ''));
    $safeUrl = preg_match('#^https?://#i', $url) ? $url : null;

    $embedSrc = null;
    if ($safeUrl && in_array($type, ['video', 'embed'], true)) {
        if (preg_match('#^https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,20})#i', $safeUrl, $m)) {
            $embedSrc = 'https://www.youtube.com/embed/' . $m[1];
        } elseif (preg_match('#^https?://(?:www\.)?vimeo\.com/(\d+)#i', $safeUrl, $m)) {
            $embedSrc = 'https://player.vimeo.com/video/' . $m[1];
        }
    }
    $isMp4 = $safeUrl && preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $safeUrl);
@endphp

@switch($type)
    @case('heading')
        <h2 class="zd-block-heading">{{ $text }}</h2>
        @break

    @case('text')
        <p class="zd-block-text">{!! nl2br(e($text)) !!}</p>
        @break

    @case('image')
        @if($safeUrl)
            <div class="zd-block-image">
                <img src="{{ $safeUrl }}" alt="{{ $block['alt'] ?? '' }}" loading="lazy">
                @if(!empty($block['caption']))
                    <div class="zd-caption">{{ $block['caption'] }}</div>
                @endif
            </div>
        @endif
        @break

    @case('video')
        @if($embedSrc)
            <div class="zd-video"><iframe src="{{ $embedSrc }}" title="{{ $block['title'] ?? 'Video' }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
        @elseif($isMp4)
            <div class="zd-video"><video src="{{ $safeUrl }}" controls preload="metadata"></video></div>
        @elseif($safeUrl)
            <a class="zd-embed-fallback" href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-circle-play"></i> {{ ($block['title'] ?? '') !== '' ? $block['title'] : 'Watch the video' }}
            </a>
        @endif
        @break

    @case('link')
        @if($safeUrl)
            <a class="zd-link-card" href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer">
                <div class="t">{{ ($block['title'] ?? '') !== '' ? $block['title'] : $safeUrl }}</div>
                @if(!empty($block['description']))
                    <div class="d">{{ $block['description'] }}</div>
                @endif
            </a>
        @endif
        @break

    @case('embed')
        @if($embedSrc)
            <div class="zd-embed"><iframe src="{{ $embedSrc }}" title="{{ $block['title'] ?? 'Embedded content' }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
        @elseif($safeUrl)
            <a class="zd-embed-fallback" href="{{ $safeUrl }}" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-up-right-from-square"></i> {{ ($block['title'] ?? '') !== '' ? $block['title'] : 'View the post' }}
            </a>
        @endif
        @break
@endswitch
