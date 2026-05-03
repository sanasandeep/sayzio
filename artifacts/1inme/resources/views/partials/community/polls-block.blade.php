{{-- Inline polls list for any biolink block. Visibility per poll
     (public/members/followers) is enforced server-side by the
     community.polls.list endpoint. --}}
<div class="community-polls-block my-4" data-link-id="{{ $link->id }}" data-block-id="{{ $block->id }}"
     data-load-url="{{ route('community.polls.list', [$link->id, $block->id]) }}"
     data-vote-url-template="{{ url('community/' . $link->id . '/blocks/' . $block->id . '/polls/__POLL__/vote') }}">
    <div class="polls-list space-y-3"></div>
</div>
