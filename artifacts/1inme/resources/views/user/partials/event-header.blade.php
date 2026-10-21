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

    /* ── The event surface ─────────────────────────────────────────────
       Restraint rather than effects: one accent, precise spacing, digits
       that line up. Token-driven throughout, so it reads in both themes
       without a second set of rules.                                   */

    .ev-panel {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 1rem;
        overflow: hidden;
        transition: border-color .2s;
    }
    /* A hairline of accent along the top edge -- the whole decoration
       budget for the page, spent once. */
    .ev-panel::before {
        content: '';
        position: absolute; inset: 0 0 auto 0; height: 1px;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        opacity: .5;
    }
    .ev-panel:hover { border-color: var(--border-glass-light); }

    .ev-section-head { display: flex; align-items: center; gap: .625rem; margin-bottom: 1.125rem; }
    .ev-section-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.75rem; height: 1.75rem; flex: 0 0 auto; border-radius: .5rem;
        background: color-mix(in srgb, var(--accent) 12%, transparent);
        color: var(--accent); font-size: .75rem;
    }
    .ev-section-title { font-size: .875rem; font-weight: 650; letter-spacing: -.01em; color: var(--text-primary); }
    .ev-section-meta  { margin-left: auto; font-size: .6875rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    /* Stat tiles. Hairline dividers rather than boxes: four numbers on one
       surface read as one set; four cards read as four things. */
    .ev-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem 0; }
    @media (min-width: 640px) { .ev-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; } }
    .ev-stat { position: relative; padding-right: 1.25rem; }
    @media (min-width: 640px) {
        .ev-stat + .ev-stat { padding-left: 1.5rem; }
        .ev-stat + .ev-stat::before {
            content: ''; position: absolute; left: 0; top: .125rem; bottom: .125rem;
            width: 1px; background: var(--border-glass);
        }
    }
    .ev-stat-label { font-size: .6875rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--text-muted); }
    .ev-stat-value {
        margin-top: .4rem; font-size: 1.875rem; font-weight: 800; line-height: 1.05;
        letter-spacing: -.025em; font-variant-numeric: tabular-nums; color: var(--text-primary);
    }
    /* Colour has to mean something. Zero connections rendered in
       success-green reads as "this is going well" when it is nothing at
       all, and a bold accent on 0% draws the eye to the least
       informative number on the page. So the semantic colour applies
       only once the figure is non-zero; until then the tile is just a
       number. The JS toggles is-zero. */
    .ev-stat-value.is-accent:not(.is-zero) { color: var(--accent); }
    .ev-stat-value.is-good:not(.is-zero)   { color: #10b981; }
    html.light-mode .ev-stat-value.is-good:not(.is-zero) { color: #047857; }
    .ev-stat-note { margin-top: .3rem; font-size: .6875rem; color: var(--text-muted); font-variant-numeric: tabular-nums; }

    /* A figure still waiting on its fetch. It keeps pulsing if the fetch
       never lands, rather than settling on a confident zero -- "we have
       not heard" and "none" are different answers. */
    .ev-stat-value[data-pending="1"] { opacity: .35; animation: evPulse 1.4s ease-in-out infinite; }
    @keyframes evPulse { 0%, 100% { opacity: .25; } 50% { opacity: .5; } }

    /* Progress track. Previously rgba(255,255,255,0.08) inline on both the
       check-in and RSVP pages: the dark theme's value, invisible on a white
       card. A div is not a form control, so #514's and #517's guards never
       looked at it. */
    .ev-meter { width: 100%; height: .5rem; border-radius: 999px; overflow: hidden; background: var(--border-glass); }
    .ev-meter-fill {
        height: 100%; border-radius: 999px; width: 0;
        background: linear-gradient(90deg, var(--accent), color-mix(in srgb, var(--accent) 60%, #10b981));
        transition: width .45s cubic-bezier(.4, 0, .2, 1);
    }

    .ev-empty { padding: 2.25rem 1rem; text-align: center; }
    .ev-empty-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.75rem; height: 2.75rem; border-radius: 999px; margin-bottom: .75rem;
        background: color-mix(in srgb, var(--accent) 9%, transparent);
        color: var(--accent); font-size: 1.125rem;
    }
    .ev-empty-title { font-size: .875rem; font-weight: 650; color: var(--text-primary); }
    .ev-empty-note  { margin-top: .25rem; font-size: .75rem; color: var(--text-muted); }

    @media (prefers-reduced-motion: reduce) {
        .ev-panel, .ev-meter-fill { transition: none; }
        .ev-stat-value[data-pending="1"] { animation: none; }
    }

</style>
@endonce
