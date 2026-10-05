{{--
    The pill bar, as a thing rather than as a copy.

    Sana, 2026-10-05: "move settings column to another tab menu menu...
    this way it will look uniform and all will be looking same layout
    type".

    The settings screens have had this bar since they were built. The menu
    editors had no bar at all -- their settings lived in a 320px column
    bolted to the side of the item list, which is why a menu page looked
    like a different product from every other page in the app.

    ---- Why these classes and not .settings-tab ---------------------------

    Because that one is load-bearing for something else. settings-header
    attaches a click handler to `.settings-tab` that fetches the next tab's
    HTML and swaps one column in place; renaming it to share this file
    would quietly disable that. The menu panes do not navigate at all --
    they switch inside one Alpine scope -- so they need the look and not the
    behaviour.

    The honest position: settings-header should be migrated onto these
    classes and its own copy of the CSS deleted, as a change of its own
    where that swap script can be tested properly. Not folded into a
    restructure of a different screen.

    Parameters: none. Include it anywhere a `.pill-tabs` bar is drawn; the
    styles are emitted once per page however many bars there are.
--}}
@once
<style>
    /* Narrow viewports swipe the bar instead of clipping later tabs off
       the screen -- a menu editor on a phone has three panes and a tablet
       in a kitchen is not a wide screen. */
    .pill-tabs-scroll {
        max-width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .pill-tabs-scroll::-webkit-scrollbar { display: none; }
    .pill-tabs {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px;
        border-radius: 9999px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        backdrop-filter: blur(16px) saturate(140%);
        -webkit-backdrop-filter: blur(16px) saturate(140%);
    }
    .pill-tab {
        position: relative;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 16px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        color: var(--text-faint);
        background: transparent;
        border: 0;
        border-radius: 9999px;
        cursor: pointer;
        white-space: nowrap;
        flex: 0 0 auto;
        transition: color .2s ease, background .2s ease, box-shadow .2s ease;
    }
    .pill-tab:hover { color: var(--text-primary); }
    .pill-tab.is-active {
        color: #fff;
        background: linear-gradient(135deg, rgba(144,172,255,0.95), rgba(103,232,249,0.85));
        box-shadow: 0 6px 18px -6px rgba(144,172,255,0.55), 0 2px 8px -2px rgba(103,232,249,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    html.light-mode .pill-tab.is-active {
        background: linear-gradient(135deg, #5c83ff, #3d6bff);
        box-shadow: 0 4px 14px -4px rgba(61,107,255,0.45);
    }
    /* A count or a warning beside a pane's name. */
    .pill-tab-dot {
        width: 6px; height: 6px;
        border-radius: 999px;
        background: #f59e0b;
        flex: none;
    }
</style>
@endonce
