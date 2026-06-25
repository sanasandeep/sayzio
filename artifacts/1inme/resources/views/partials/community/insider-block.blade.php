{{-- Public-facing Insider feed block rendered inline on a biolink page.
     Themed via the active card template's CSS variables; AJAX-powered. --}}
<div class="community-insider-block rounded-2xl p-5 my-4" data-link-id="{{ $link->id }}" data-block-id="{{ $block->id }}" style="background: var(--card-bg, rgba(255,255,255,0.05)); border: 1px solid var(--card-border, rgba(255,255,255,0.1));">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-base font-semibold" style="color: var(--card-fg, #fff);">
            <i class="fas fa-lock mr-1.5"></i>
            {{ $block->settings['title'] ?? 'Insider Feed' }}
        </h3>
        <button type="button" class="insider-join-btn text-xs px-3 py-1 rounded-full" style="background: var(--accent, #3d6bff); color:#fff;">
            Join
        </button>
    </div>
    <p class="text-sm opacity-70 mb-3" style="color: var(--card-fg, #fff);">
        {{ $block->settings['intro'] ?? 'Become an Insider to unlock exclusive posts, polls and behind-the-scenes content.' }}
    </p>
    <div class="insider-feed-list space-y-3" data-feed-url="{{ route('community.insider.feed', [$link->id, $block->id]) }}">
        <div class="text-xs opacity-50">Loading feed…</div>
    </div>

    {{-- Lightweight join modal --}}
    <form class="insider-join-form mt-3 hidden" data-action="{{ route('community.insider.join', [$link->id, $block->id]) }}">
        @csrf
        <input type="email" name="email" required placeholder="Your email" class="w-full px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white placeholder-white/50 mb-2">
        <input type="text" name="display_name" placeholder="Display name (optional)" class="w-full px-3 py-2 rounded-lg bg-black/20 border border-white/10 text-white placeholder-white/50 mb-2">
        <button type="submit" class="w-full py-2 rounded-lg" style="background: var(--accent, #3d6bff); color:#fff;">Unlock Insider Feed</button>
    </form>
</div>
