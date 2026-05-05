{{--
    Branded reaction row, shared by every Creator Post on /@handle. The
    six reactions are platform-wide and rendered the same way on every
    surface — see CreatorPostReaction::REACTIONS for the canonical list.

    Required vars:
      $post           CreatorPost instance
      $creator        owning User
      $totals         array<reaction_key, int>  per-post tallies
      $myReaction     ?string  the viewer's currently selected reaction key
      $reactionDefs   array (config above)
--}}
@php
    $endpoint = route('creator-profile.react', ['handle' => $creator->handle, 'post' => $post->id]);
@endphp
<div class="flex flex-wrap items-center gap-1.5"
     data-cp-reactions="{{ $post->id }}"
     data-cp-endpoint="{{ $endpoint }}">
    @foreach($reactionDefs as $r)
        @php
            $count = (int) ($totals[$r['key']] ?? 0);
            $active = $myReaction === $r['key'];
        @endphp
        <button type="button"
                data-cp-reaction="{{ $r['key'] }}"
                style="--accent: {{ $r['color'] }};"
                class="cp-reaction-btn group inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition
                       {{ $active
                            ? 'bg-[color:var(--accent)] text-white border-[color:var(--accent)]'
                            : 'bg-white text-slate-700 border-slate-200 hover:border-[color:var(--accent)] hover:text-[color:var(--accent)]' }}"
                aria-label="{{ $r['label'] }}"
                title="{{ $r['label'] }}">
            <i class="{{ $r['icon'] }}"></i>
            <span data-cp-count>{{ $count > 0 ? $count : '' }}</span>
        </button>
    @endforeach
</div>
