{{-- Inline comments thread for a block. Visibility (public/members/followers)
     is enforced server-side via the block's `comments_visibility` setting.
     Themed from the biolink's own theme tokens ($fontColor / $btnColor). --}}
@php
    $__cFg = $fontColor ?? '#ffffff';
    $__cAccent = $btnColor ?? '#3d6bff';
    $__cBg = $__cFg . '0d';      // ~5% tint of the theme text color
    $__cBorder = $__cFg . '1a';  // ~10% tint of the theme text color
@endphp
<div class="community-comments-block rounded-2xl p-4 my-4" data-link-id="{{ $link->id }}" data-block-id="{{ $block->id }}" style="background: {{ $__cBg }}; border:1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
    <div class="flex items-center justify-between mb-3">
        <h4 class="font-semibold text-sm" style="color: {{ $__cFg }};"><i class="far fa-comments mr-1.5"></i>Comments</h4>
        <div class="reactions text-xs opacity-80">
            @foreach(\App\Modules\User\Models\BlockReaction::EMOJIS as $e)
            <button type="button" class="react-btn px-1" data-emoji="{{ $e }}" data-action="{{ route('community.reactions.toggle', [$link->id, $block->id]) }}">{{ $e }}</button>
            @endforeach
        </div>
    </div>

    <div class="comments-list space-y-2 mb-3" data-load-url="{{ route('community.comments.list', [$link->id, $block->id]) }}">
        <div class="text-xs opacity-50">Loading comments…</div>
    </div>

    <form class="comment-form" data-action="{{ route('community.comments.post', [$link->id, $block->id]) }}">
        @csrf
        <textarea name="body" required rows="2" placeholder="Add a comment…" class="w-full px-3 py-2 rounded-lg text-sm" style="background: {{ $__cFg }}12; border: 1px solid {{ $__cBorder }}; color: {{ $__cFg }};"></textarea>
        <div class="flex items-center gap-2 mt-2">
            <input type="text" name="author_name" placeholder="Name (optional)" class="flex-1 px-3 py-1.5 rounded-lg text-sm" style="background: {{ $__cFg }}12; border: 1px solid {{ $__cBorder }}; color: {{ $__cFg }};">
            <button class="px-3 py-1.5 rounded-lg text-sm" style="background: {{ $__cAccent }}; color:#fff;">Post</button>
        </div>
    </form>
</div>
