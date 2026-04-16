{{--
    Reusable page hero header.

    @include('user.partials.page-hero', [
        'title'    => 'Page Title',
        'subtitle' => 'Optional one-liner under the title',
        'icon'     => 'fa-link',                          // FA icon class (default fa-rocket)
        'favicon'  => null,                               // image URL — replaces icon when set
        'chips'    => [                                   // small status pills, optional
            ['icon' => 'fa-circle text-emerald-400', 'text' => 'Active'],
            ['icon' => 'fa-calendar', 'text' => 'Today'],
        ],
        'back'     => route('user.links.index'),          // back-button URL, optional
        'actions'  => [                                   // structured action buttons, optional
            ['label' => 'Create', 'url' => '/x', 'icon' => 'fa-plus', 'class' => 'btn-primary', 'target' => null],
        ],
    ])
--}}
@php
    $title    = $title    ?? 'Untitled';
    $subtitle = $subtitle ?? null;
    $icon     = $icon     ?? 'fa-rocket';
    $favicon  = $favicon  ?? null;
    $chips    = $chips    ?? [];
    $back     = $back     ?? null;
    $actions  = $actions  ?? [];
    if (!is_array($actions)) { $actions = []; }
@endphp
<div class="page-hero mb-6">
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div class="flex items-start gap-4 min-w-0 flex-1">
            @if($back)
                <a href="{{ $back }}" class="hero-chip" title="Back" aria-label="Back">
                    <i class="fas fa-arrow-left"></i>
                </a>
            @endif
            <div class="hero-emblem {{ $favicon ? 'has-favicon' : '' }}" x-data="{ failed: false }">
                @if($favicon)
                    <img src="{{ $favicon }}" alt="favicon" class="favicon-img"
                         x-show="!failed" @@error="failed = true">
                    <i x-show="failed" x-cloak class="fas {{ $icon }}" style="color:#7c3aed;"></i>
                @else
                    <i class="fas {{ $icon }}"></i>
                @endif
            </div>
            <div class="min-w-0">
                @if(!empty($chips))
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        @foreach($chips as $c)
                            <span class="hero-chip">
                                @if(!empty($c['icon']))<i class="fas {{ $c['icon'] }}"></i>@endif
                                {{ $c['text'] ?? '' }}
                            </span>
                        @endforeach
                    </div>
                @endif
                <h1 class="hero-title gradient-text truncate">{{ $title }}</h1>
                @if($subtitle)
                    <p class="text-sm mt-1.5" style="color: var(--text-muted);">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if(!empty($actions))
            <div class="flex items-center gap-2 flex-wrap">
                @foreach($actions as $a)
                    <a href="{{ $a['url'] ?? '#' }}"
                       @if(!empty($a['target']))target="{{ $a['target'] }}" rel="noopener noreferrer"@endif
                       class="{{ $a['class'] ?? 'btn-primary' }} text-xs py-2">
                        @if(!empty($a['icon']))<i class="fas {{ $a['icon'] }} text-[10px]"></i>@endif
                        {{ $a['label'] ?? '' }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
