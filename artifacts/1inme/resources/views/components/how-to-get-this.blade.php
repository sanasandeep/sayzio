@props([
    'guideKey' => null,
    'title'    => null,
    'steps'    => null,
    'docsUrl'  => null,
    'docsLabel' => 'View official docs',
])

@php
    $guide = $guideKey ? \App\Modules\User\Support\ExternalValueGuides::get($guideKey) : null;

    $title     = $guide['title'] ?? $title ?? 'How to get this';
    $steps     = $guide['steps'] ?? $steps ?? [];
    $docsUrl   = $guide['docs_url'] ?? $docsUrl;
    $docsLabel = $guide['docs_label'] ?? $docsLabel;

    if (empty($steps)) {
        // Nothing to show — fail silently rather than render an empty shell.
        return;
    }

    $panelId = 'htg-' . uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'mt-1.5']) }} x-data="{ open: false }">
    <button type="button"
            @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $panelId }}"
            class="inline-flex items-center gap-1.5 text-[11px] font-semibold rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1"
            style="color: var(--accent, #7c5cff);">
        <i class="fas fa-circle-question text-[10px]" aria-hidden="true"></i>
        <span>{{ $title }}</span>
        <i class="fas fa-chevron-down text-[9px] transition-transform duration-150" aria-hidden="true"
           :class="{ 'rotate-180': open }"></i>
    </button>

    <div id="{{ $panelId }}"
         role="region"
         aria-label="{{ $title }}"
         x-show="open"
         x-collapse
         x-cloak
         class="mt-2 rounded-xl p-3.5 text-[11px] leading-relaxed"
         style="background: var(--bg-glass-input, rgba(255,255,255,0.04)); border: 1px solid var(--border-glass, rgba(255,255,255,0.1)); color: var(--text-secondary, rgba(226,232,240,0.75)); backdrop-filter: blur(6px);">
        <ol class="list-decimal list-outside ml-4 space-y-1.5">
            @foreach($steps as $step)
                <li>{!! $step !!}</li>
            @endforeach
        </ol>

        @if($docsUrl)
            <a href="{{ $docsUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 mt-3 font-semibold hover:underline"
               style="color: var(--accent, #7c5cff);">
                {{ $docsLabel }} <i class="fas fa-arrow-up-right-from-square text-[9px]" aria-hidden="true"></i>
            </a>
        @endif
    </div>
</div>
