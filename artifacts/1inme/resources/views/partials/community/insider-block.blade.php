{{-- Public-facing Insider feed block rendered inline on a biolink page.
     Themed from the biolink's own theme tokens ($fontColor / $btnColor) so
     text and surfaces stay legible on both light- and dark-themed pages. --}}
@php
    $__cFg = $fontColor ?? '#ffffff';
    $__cAccent = $btnColor ?? '#3d6bff';
    $__cBg = $__cFg . '0d';      // ~5% tint of the theme text color
    $__cBorder = $__cFg . '1a';  // ~10% tint of the theme text color
@endphp
<div class="community-insider-block rounded-2xl p-5 my-4" data-link-id="{{ $link->id }}" data-block-id="{{ $block->id }}" style="background: {{ $__cBg }}; border: 1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold" style="color: {{ $__cFg }};">
            <i class="fas fa-lock mr-1.5"></i>
            {{ $block->settings['title'] ?? 'Insider Feed' }}
        </h3>
        <button type="button" class="insider-join-btn text-xs px-3 py-1 rounded-full" style="background: {{ $__cAccent }}; color:#fff;">
            Join
        </button>
    </div>
    <p class="text-sm opacity-70 mb-3" style="color: {{ $__cFg }};">
        {{ $block->settings['intro'] ?? 'Become an Insider to unlock exclusive posts, polls and behind-the-scenes content.' }}
    </p>
    <div class="insider-feed-list space-y-3" data-feed-url="{{ route('community.insider.feed', [$link->id, $block->id]) }}">
        <div class="text-xs opacity-50">Loading feed…</div>
    </div>

    {{-- Lightweight join modal --}}
    <form class="insider-join-form mt-3 hidden" data-action="{{ route('community.insider.join', [$link->id, $block->id]) }}">
        @csrf
        <input type="email" name="email" required placeholder="Your email" class="w-full px-3 py-2 rounded-lg mb-2" style="background: {{ $__cFg }}12; border: 1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
        <input type="text" name="display_name" placeholder="Display name (optional)" class="w-full px-3 py-2 rounded-lg mb-2" style="background: {{ $__cFg }}12; border: 1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
        <button type="submit" class="w-full py-2 rounded-lg" style="background: {{ $__cAccent }}; color:#fff;">Unlock Insider Feed</button>
    </form>
</div>
