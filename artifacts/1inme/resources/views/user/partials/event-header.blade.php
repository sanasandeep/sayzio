{{--
    The header every event page wears: hero card, then the tab bar.

    Six pages manage one event, and each built its own header. They
    drifted, visibly: three different page widths across the seven tabs
    (none, max-w-3xl, max-w-7xl), so the whole page resized as the
    organiser moved between tabs of the SAME event. Edit carried status
    chips and the others did not, so the tab bar jumped vertically too.

    So the header is one thing now, and width is set here rather than by
    each caller. The Overview tab is deliberately not routed through
    this partial: it is the generic link page shared by biolinks,
    resumes and the rest, and its hero belongs to all of them. It is
    already full width, which is what these six now match.

    Usage, replacing a page-hero + event-tabs pair:

        @include('user.partials.event-header', [
            'link'  => $link,
            'title' => 'Message guests',
            'icon'  => 'fa-paper-plane',
        ])

    Page-specific chips and actions still belong to the page -- the
    ticket totals and the RSVP count are real numbers, not decoration,
    and uniformity is not a reason to delete them. They are appended
    after the status chips every tab shows.
--}}
@php
    $evLink     = $link;
    $evTitle    = $evTitle    ?? ($title ?? 'Event');
    $evIcon     = $evIcon     ?? ($icon  ?? 'fa-calendar');
    $evExtra    = $chips      ?? [];
    $evActions  = $actions    ?? [];
    $evIcs      = $evLink->relationLoaded('icsData') ? $evLink->icsData : $evLink->icsData()->first();

    // Shown on every tab, so the chip row always exists and the tab bar
    // below it stops moving when the organiser switches tabs.
    $evChips = [[
        'icon' => 'fa-circle ' . (($evLink->is_active ?? true) ? 'text-emerald-400' : 'text-slate-400'),
        'text' => ($evLink->is_active ?? true) ? 'Active' : 'Inactive',
    ]];

    if ($evIcs && $evIcs->start_date) {
        $evChips[] = ['icon' => 'fa-calendar', 'text' => $evIcs->start_date->format('M d, Y')];
    }

    $evChips = array_merge($evChips, is_array($evExtra) ? $evExtra : []);
@endphp

@include('user.partials.page-hero', [
    'title'    => $evTitle,
    'subtitle' => $evLink->title ?: $evLink->alias,
    'icon'     => $evIcon,
    'chips'    => $evChips,
    'back'     => route('user.links.show', $evLink),
    'actions'  => $evActions,
])

@include('user.partials.event-tabs', ['link' => $evLink])

@once
<style>
    /*
        Form controls on the event pages.

        These exist because the event views styled their inputs with
        hardcoded rgba(255,255,255,...) literals. Those are the DARK
        theme's token values, pasted inline. In light mode the tokens
        resolve to #ffffff on an #e3e0da border; hardcoded, the field
        renders transparent-white on a white card with a 10%-white
        border and is simply not visible until it takes focus.

        The values below are the tokens those literals were copied
        from, so dark mode is unchanged and only light mode differs.
    */
    .ev-input {
        width: 100%;
        background-color: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        border-radius: 0.75rem;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .ev-input::placeholder { color: var(--text-muted); opacity: 1; }
    .ev-input:hover:not(:focus) { border-color: var(--border-glass-light); }
    .ev-input:focus {
        outline: 2px solid transparent;
        background-color: var(--bg-glass-input-focus);
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-glow);
    }
    .ev-input textarea, textarea.ev-input { resize: vertical; min-height: 7rem; }
    /* The inline table controls on Ticketing are deliberately tighter. */
    .ev-input-sm { padding: 0.3125rem 0.5rem; border-radius: 0.5rem; }
    .ev-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--text-muted);
        margin-bottom: 0.375rem;
    }
</style>
@endonce
