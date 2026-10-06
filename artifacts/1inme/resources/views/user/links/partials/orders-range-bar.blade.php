{{--
    Which day's orders the screen is showing.

    Sana, 2026-09-28: "wht is too many order? selected with dates?"

    ---- Why changing the range is a navigation ---------------------------

    The server decides what is in the window; having the browser decide
    too would be two answers to one question, and they would disagree the
    first time a timezone or a boundary was involved. So picking a range
    reloads with it in the URL -- which also means the owner can bookmark
    "yesterday" and send somebody a link to a specific week.

    Paging is a fetch, because appending to a list you are reading should
    not throw away your scroll position.

    ---- Why the screen says when it is not live -------------------------

    A kitchen screen with a pulsing "Live" dot is a promise that new
    orders will appear on it. While somebody is looking at last Tuesday,
    that promise is false, and an owner who leaves the screen on a past
    range would never know they had stopped seeing orders. So the dot is
    swapped for what it is actually doing.

    Parameters:
      $rbRoute  the orders route for this link
--}}
<div class="ro-range">
    <div class="ro-range-row">


        <form data-date-filters data-list-filters method="GET" action="{{ $rbRoute }}" class="ro-range-custom" data-range-label="{{ $range['label'] }}">
            <div class="flex flex-wrap gap-2">
        @foreach(\App\Modules\User\Support\MenuOrderRange::LABELS as $rbKey => $rbLabel)
            <a class="ro-btn {{ $range['key'] === $rbKey ? 'active' : '' }}"
               href="{{ $rbRoute }}?range={{ $rbKey }}">{{ $rbLabel }}</a>
        @endforeach
            </div>
            <input type="hidden" name="range" value="custom">
            <label>From date<input type="date" name="from" value="{{ $range['from_date'] }}"
                   aria-label="From date" class="ro-date"></label>
            <span class="ro-range-to">to</span>
            <label>To date<input type="date" name="to" value="{{ $range['to_date'] }}"
                   aria-label="To date" class="ro-date"></label>
            <button type="submit" class="ro-btn {{ $range['key'] === 'custom' ? 'active' : '' }}">Go</button>
        </form>
    </div>

    <p class="ro-range-note">
        {{-- Only when there is something to count. With no orders this
             printed the empty sentence, and the empty state eight pixels
             below printed the SAME sentence again -- two identical lines
             stacked on a screen whose whole content was those two lines. --}}
        <span x-show="meta.total" x-text="rangeSummary()"></span>
        {{-- Said out loud, because a screen that has quietly stopped
             receiving orders looks exactly like a quiet evening. --}}
        <template x-if="!meta.is_live">
            <span class="ro-not-live">Not live. New orders will not appear while you are looking at this.</span>
        </template>
    </p>
</div>

<style>
    .ro-range { margin-bottom: 14px; }
    .ro-range-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .ro-range-custom { display: inline-flex; align-items: center; gap: 6px; margin-left: 4px; }
    .ro-range-to { font-size: 12px; color: var(--text-muted); }
    .ro-date {
        padding: 6px 9px;
        border-radius: 8px;
        border: 1px solid var(--border-glass);
        background: var(--bg-glass-input, rgba(0,0,0,.2));
        color: var(--text-primary);
        font-size: 12.5px;
        font-family: inherit;
    }
    .ro-range-note { margin-top: 8px; font-size: 12.5px; color: var(--text-muted); }
    .ro-not-live {
        display: inline-block;
        margin-left: 6px;
        padding: 1px 8px;
        border-radius: 999px;
        background: rgba(234,179,8,.16);
        color: #eab308;
        font-weight: 600;
    }
    @media (max-width: 640px) {
        .ro-range-custom { margin-left: 0; width: 100%; }
        .ro-date { flex: 1; min-width: 0; }
    }
</style>
