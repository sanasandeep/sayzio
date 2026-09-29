{{--
    How the cart presents itself: the floating button that opens it, and the
    panel it opens into. Shared by both menu pages.

    Sana, 2026-09-28: "Review button should be like floading buttton..",
    "order form should be like side or popup button..."

    ---- The button ------------------------------------------------------

    It was a full-width bar pinned across the bottom of the page. On a phone
    that is a wall across the menu you are still reading, and it only ever
    said the same two things. It is a pill now, sized to its own content,
    sitting clear of the bottom edge -- and it carries the count and the
    total, so the bar it replaces has nothing left to do.

    ---- The panel -------------------------------------------------------

    The sheet was pinned to the bottom on every screen. That is right on a
    phone, held one-handed with the thumb at the bottom. On a laptop it is a
    760px slab stuck to the bottom edge of a 1400px window with the menu
    still visible behind it, which is what looks wrong in a screenshot.

    So the same markup is a bottom sheet under 860px and a side panel above
    it, which is where a cart belongs on a wide screen: full height, against
    one edge, the menu still readable beside it.

    One copy, because these two pages have spent this whole month drifting
    apart wherever they each kept their own.
--}}
<style>
    /* ── The floating button ─────────────────────────────────────────── */
    .cartfab {
        position: fixed;
        right: 16px;
        bottom: calc(16px + env(safe-area-inset-bottom));
        z-index: 40;
        display: none;
        align-items: center;
        gap: 10px;
        padding: 13px 20px;
        border: none;
        border-radius: 999px;
        background: var(--accent);
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        box-shadow: 0 8px 24px -6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08) inset;
        transition: transform .15s ease, box-shadow .15s ease;
        max-width: calc(100vw - 32px);
    }
    .cartfab.show { display: inline-flex; }
    /* The share button floats at z-index 9990, which is above every sheet
       on this page, and on the confirmation it lands squarely on top of
       "Back to menu". Found by rendering the confirmation and asking the
       browser what was actually under that pixel -- the cart pill looks
       like the culprit and is not: at z-index 40 it sits safely behind
       the sheet's own dim layer.
       Sharing a menu is not something anyone does mid-order, so the
       button steps aside while a sheet is up. `:has` rather than a body
       class, so no close path has to remember to unset it. */
    body:has(.modal.show) .sz-share,
    body:has(.chz.show) .sz-share { display: none; }
    .cartfab:hover { transform: translateY(-1px); box-shadow: 0 12px 28px -6px rgba(0,0,0,.5); }
    .cartfab:active { transform: translateY(0); }
    .cartfab:focus-visible { outline: 3px solid rgba(255,255,255,.7); outline-offset: 2px; }
    /* The count sits in its own disc so the total stays the thing you read. */
    .cartfab .n {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 22px; height: 22px; padding: 0 6px;
        border-radius: 999px; background: rgba(0,0,0,.22);
        font-size: 12.5px; font-weight: 800;
    }
    .cartfab .sum { white-space: nowrap; }
    @media (prefers-reduced-motion: reduce) {
        .cartfab { transition: none; }
        .cartfab:hover { transform: none; }
    }

    /* ── The panel ───────────────────────────────────────────────────── */
    .modal { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; z-index: 50; }
    .modal.show { display: flex; }

    /* Phone: a bottom sheet, which is where a thumb is. */
    .modal { align-items: flex-end; justify-content: center; }
    .sheet {
        background: #fff; color: #111;
        width: 100%; max-width: 760px;
        border-radius: 18px 18px 0 0;
        padding: 20px 18px calc(20px + env(safe-area-inset-bottom));
        max-height: 88vh; overflow: auto;
    }
    @media (prefers-color-scheme: dark) { .sheet { background: #15151c; color: #f5f5f7; } }
    .sheet h3 { margin: 0 0 12px; font-size: 18px; }

    /* Laptop: a side panel. A 760px slab stuck to the bottom edge of a
       1400px window is a phone layout that wandered onto a desktop. */
    @media (min-width: 860px) {
        .modal { align-items: stretch; justify-content: flex-end; }
        .sheet {
            width: 420px;
            max-width: 92vw;
            max-height: none;
            height: 100%;
            border-radius: 0;
            padding: 24px 22px;
            box-shadow: -18px 0 48px -18px rgba(0,0,0,.55);
        }
    }
</style>
