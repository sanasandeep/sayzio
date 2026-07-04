{{--
    Reusable page hero header — used across Dashboard, Links list, Link analytics,
    Link edit, Blocks editor and Settings sub-pages.

    @include('user.partials.page-hero', [
        'title'    => 'Page Title',
        'subtitle' => 'Optional one-liner under the title',
        'icon'     => 'fa-link',                          // FA icon class (default fa-rocket)
        'favicon'  => null,                               // image URL — preferred avatar
        'url'      => null,                               // optional short URL row with copy + open
        'chips'    => [                                   // small status pills
            ['icon' => 'fa-circle text-emerald-400', 'text' => 'Active'],
        ],
        'back'     => route('user.links.index'),
        'actions'  => [
            ['label' => 'Create', 'url' => '/x', 'icon' => 'fa-plus', 'class' => 'btn-primary', 'target' => null],
        ],
    ])
--}}
@php
    $title    = $title    ?? 'Untitled';
    $subtitle = $subtitle ?? null;
    $icon     = $icon     ?? 'fa-rocket';
    $favicon  = $favicon  ?? null;
    $url      = $url      ?? null;
    $chips    = $chips    ?? [];
    $back     = $back     ?? null;
    $actions  = $actions  ?? [];
    if (!is_array($actions)) { $actions = []; }
    // Letter-avatar fallback: first alphanumeric char of the title, uppercased.
    $letter = strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]+/u', '', $title) ?: $title, 0, 1));
@endphp
<div class="page-hero mb-6"@if($url) x-data="{ copied: false }"@endif>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div class="flex items-start gap-4 min-w-0 flex-1">
            @if($back)
                <a href="{{ $back }}" class="hero-back" title="Back" aria-label="Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
            @endif
            <div class="hero-emblem {{ $favicon ? 'has-favicon' : 'has-letter' }}" x-data="{ failed: false }">
                @if($favicon)
                    <img src="{{ $favicon }}" alt="favicon" class="favicon-img"
                         x-show="!failed" @@error="failed = true">
                    <span x-show="failed" x-cloak class="hero-emblem-letter">{{ $letter }}</span>
                @else
                    <span class="hero-emblem-letter">{{ $letter }}</span>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                @if(!empty($chips))
                    <div class="flex items-center gap-2 flex-wrap mb-1.5">
                        @foreach($chips as $c)
                            <span class="hero-chip">
                                @if(!empty($c['icon']))<i class="fas {{ $c['icon'] }}"></i>@endif
                                <span @if(!empty($c['textId'])) id="{{ $c['textId'] }}"@endif>{{ $c['text'] ?? '' }}</span>
                            </span>
                        @endforeach
                    </div>
                @endif
                <h1 class="hero-title gradient-text truncate">{{ $title }}</h1>
                @if($subtitle)
                    <p class="hero-subtitle">{{ $subtitle }}</p>
                @endif
                @if($url)
                    <div class="hero-url">
                        <i class="fas fa-link hero-url-icon"></i>
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="hero-url-text truncate" title="{{ $url }}">{{ $url }}</a>
                        <button type="button"
                                @click="navigator.clipboard.writeText('{{ $url }}'); copied = true; setTimeout(() => copied = false, 1800)"
                                class="hero-url-btn" title="Copy link">
                            <i x-show="!copied" class="fas fa-copy"></i>
                            <i x-show="copied" x-cloak class="fas fa-check" style="color:#10b981;"></i>
                        </button>
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="hero-url-btn" title="Open in new tab">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
        @if(!empty($actions))
            <div class="flex items-center gap-2 flex-wrap">
                @foreach($actions as $a)
                    <a href="{{ $a['url'] ?? '#' }}"
                       @if(!empty($a['target']))target="{{ $a['target'] }}" rel="noopener noreferrer"@endif
                       class="{{ $a['class'] ?? 'btn-primary' }} text-xs py-2"
                       @if(!empty($a['title']))title="{{ $a['title'] }}"@endif>
                        @if(!empty($a['icon']))<i class="fas {{ $a['icon'] }} text-[10px]"></i>@endif
                        @if(!empty($a['label'])){{ $a['label'] }}@endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
