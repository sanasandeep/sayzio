{{-- The button system.

     The app had one weight for "this is a button you press", and it was
     blue: `.btn-primary` painted the accent, `.btn-primary-gradient` painted
     an accent gradient, the editor and pill tabs painted the same gradient
     when active, the row buttons inside the menu editors painted it again,
     and several hundred call sites painted `bg-blue-600` by hand. On a
     single screen that produced eight blue slabs of equal weight and no way
     to tell the one action that mattered from the six that did not.

     Three weights now, and only one of them is coloured:

       1. GRADIENT -- the accent gradient, reserved. The page's own primary
          action (New Link), the AI entry points, and billing. These are the
          buttons that should pull the eye, and they only do that while they
          are rare.
       2. SOLID -- near-black in light mode, an elevated charcoal with a
          hairline in dark. Everything else that is a real action: Save, Add,
          Apply, Create. Black is the strongest contrast a light page has, so
          a solid button still reads as the heaviest thing in its row without
          spending a colour on it.
       3. GHOST -- unchanged, and still the default for anything secondary.

     Dark mode does NOT get black. The page ground is #0a0a0f; a black button
     on it is a hole. `--btn-solid` lifts to #1c1c24 with a hairline so the
     button reads as a raised surface rather than a cut-out, which is the
     same reasoning the rail card uses for its own ground.

     Selection is not an action, so the active tab no longer borrows the
     primary's surface: it takes a neutral ink tint, which says "you are
     here" without competing with the buttons beside it for weight.

     This file loads AFTER theme-styles on purpose -- it is overriding that
     file's own `.btn-primary`, and it does it by being later and more
     specific rather than by making every declaration !important. The one
     exception is the raw-utility rule at the bottom, which has to beat
     Tailwind utilities sitting on the element itself.

     `.keep-accent` is the opt-out, for a button that should stay on the
     accent on purpose. --}}
<style>
    :root {
        /* Dark is the base, as everywhere else in this app. */
        --btn-solid: #1c1c24;
        --btn-solid-hover: #272730;
        --btn-solid-ink: #f3f1f7;
        --btn-solid-line: rgba(255, 255, 255, 0.09);
        --btn-solid-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
        /* Selection, derived from the page's own ink so it follows a theme
           or a nav preset rather than being pinned to one grey. */
        /* Fallbacks are not belt-and-braces here, they are load-bearing.
           Measured on sayzio.app/: the public pages do not load
           theme-styles, so --text-primary, --border-glass and --accent
           are all empty there. A var() with no fallback computes
           invalid, and `background: var(--undefined)` is a transparent
           button -- the exact failure we just spent a PR fixing on the
           marketing header. */
        --tab-active-bg: color-mix(in srgb, var(--text-primary, #f3f1f7) 10%, transparent);
        --tab-active-line: color-mix(in srgb, var(--text-primary, #f3f1f7) 18%, transparent);
    }
    html.light-mode {
        --btn-solid: #14161c;
        --btn-solid-hover: #232733;
        --btn-solid-ink: #ffffff;
        /* No hairline in light mode: the button is already the darkest thing
           on the page, and an outline on top of that reads as a double edge. */
        --btn-solid-line: transparent;
        --btn-solid-shadow: 0 1px 2px rgba(15, 23, 42, 0.18);
    }

    /* ---- 2. SOLID -------------------------------------------------------
       `html` on the front is not decoration: theme-styles sets the light
       variant as `html.light-mode .btn-primary` (0,2,1), so a bare
       `.btn-primary:not(...)` (0,2,0) would lose to it on box-shadow and the
       button would keep a blue glow under a black surface. */
    html .btn-primary:not(.btn-primary-gradient):not(.is-ai-cta):not(.keep-accent),
    html .rm-btn:not(.ghost):not(.danger):not(.keep-accent) {
        background: var(--btn-solid);
        background-image: none;
        color: var(--btn-solid-ink);
        box-shadow: inset 0 0 0 1px var(--btn-solid-line), var(--btn-solid-shadow);
    }
    html .btn-primary:not(.btn-primary-gradient):not(.is-ai-cta):not(.keep-accent):hover,
    html .rm-btn:not(.ghost):not(.danger):not(.keep-accent):hover {
        background: var(--btn-solid-hover);
        background-image: none;
        box-shadow: inset 0 0 0 1px var(--btn-solid-line), var(--btn-solid-shadow);
    }

    /* ---- 1. GRADIENT ----------------------------------------------------
       `.is-ai-cta` carries the gradient onto a button whose geometry comes
       from somewhere else (the slides toolbar, the resume toolbar, the link
       editor header), so those entry points can be promoted without each of
       them growing a copy of the primary button's padding and radius. */
    html .is-ai-cta {
        background: linear-gradient(135deg, var(--accent, #3d6bff), var(--accent-light, #7b45d6));
        color: #fff;
        border-color: transparent;
    }
    html .is-ai-cta:hover {
        background: linear-gradient(135deg, var(--accent-light, #7b45d6), var(--accent, #3d6bff));
        box-shadow: 0 4px 14px -4px var(--accent-glow, rgba(61,107,255,0.35));
    }
    html .is-ai-cta i { color: inherit; }

    /* ---- Selection, not action -----------------------------------------
       The active tab had the primary gradient, which made every editor read
       as though it had three primary buttons in a row.

       The list below is the full inventory, not a list of the ones that were
       noticed. The first pass covered `.editor-tab` and `.pill-tab` because
       those were the two visible on the page being looked at, and the inner
       row on a link's Settings -- `.settings-tab` -- was still blue after
       the deploy. So every rule in resources/views that paints an active or
       aria-selected state with a gradient or the accent was enumerated by
       scanning the blade files, and each selection control in that list is
       named here.

       Two things in that inventory are deliberately NOT here:
       `.sidebar-link.active`, which is nav chrome and belongs to the
       navigation-appearance presets, and `.qr-shape-card.active`, which is a
       preview tile in a swatch grid rather than a tab -- the accent is doing
       real work as the "this one is chosen" marker among a dozen look-alike
       cards, and a 10% ink tint would be a worse signal there, not a
       quieter one.

       !important here, and measured rather than assumed: the tab styles live
       in page-local @push('styles') blocks, so they render AFTER this
       partial, and the light variant is written as
       `html.light-mode .editor-tab.is-active` -- (0,3,1), later in source.
       Nothing this file can write wins that on specificity without a
       selector that is a lie about where the rule belongs. The solid-button
       rules above need no !important and do not get one; they win on
       specificity, which was checked on the live page. */
    html .editor-tab.is-active,
    html .pill-tab.is-active,
    html .settings-tab.is-active,
    html .settings-tab.active,
    html .settings-subtab.active,
    html .cv-tab.is-active,
    html .palette-tab.active,
    html .gallery-tab.active,
    html .qr-type-tab.active,
    html .qr-cat-pill.active,
    html .sl-span-btn.active,
    html .ro-btn.active,
    html .sb-btn.active,
    html .et-page-btn.active,
    html .view-toggle button.active,
    html .bk-tabs button[aria-selected="true"],
    html .kb-tabs button.is-active {
        background: var(--tab-active-bg) !important;
        background-image: none !important;
        color: var(--text-primary, #f3f1f7) !important;
        box-shadow: inset 0 0 0 1px var(--tab-active-line) !important;
        text-shadow: none !important;
    }
    html .editor-tab.is-active i,
    html .pill-tab.is-active i,
    html .settings-tab.is-active i,
    html .settings-tab.active i,
    html .settings-subtab.active i,
    html .cv-tab.is-active i,
    html .palette-tab.active i,
    html .gallery-tab.active i,
    html .qr-type-tab.active i,
    html .qr-cat-pill.active i,
    html .sl-span-btn.active i,
    html .ro-btn.active i,
    html .sb-btn.active i,
    html .et-page-btn.active i,
    html .view-toggle button.active i,
    html .bk-tabs button[aria-selected="true"] i,
    html .kb-tabs button.is-active i { color: inherit !important; }

    /* ---- Hand-painted blue ----------------------------------------------
       Several hundred call sites across the app paint `bg-blue-600` /
       `bg-blue-700` / `bg-indigo-600` directly. Retinting them here rather
       than editing each one keeps the change reviewable and reversible, and
       means a new view written in the old habit still comes out right.

       Narrowed to interactive elements on purpose: the same utilities are
       used for icon chips, badges, legend swatches and avatar grounds, and
       those are information rather than actions -- blackening them would
       destroy the thing they encode. !important here because the utility
       sits on the element itself and would otherwise win on source order. */
    :is(body[data-app-layout], body[data-site-layout]) :is(button, a, label, summary, [role="button"],
        input[type="submit"], input[type="button"]):is([class*="bg-blue-6"],
        [class*="bg-blue-7"], [class*="bg-indigo-6"]):not(.keep-accent):not(.is-ai-cta) {
        background-color: var(--btn-solid) !important;
        background-image: none !important;
        color: var(--btn-solid-ink) !important;
    }
    :is(body[data-app-layout], body[data-site-layout]) :is(button, a, label, summary, [role="button"],
        input[type="submit"], input[type="button"]):is([class*="bg-blue-6"],
        [class*="bg-blue-7"], [class*="bg-indigo-6"]):not(.keep-accent):not(.is-ai-cta):hover {
        background-color: var(--btn-solid-hover) !important;
    }

    /* ---- The marketing pages, same three weights ---------------------
       The public site does not use the raw bg-blue utilities at all --
       measured: zero on the homepage. It has its own small vocabulary, and
       the gradients in it are the signup CTA repeated down the page, which
       is one action offered three times rather than three actions
       competing. Those keep the gradient, by Sana's call.

       What changes is the hand-painted coloured solids, enumerated from the
       source rather than noticed by eye: a secondary action should not be
       wearing a saturated fill when the same weight in the app is black.

       Deliberately NOT here: `.sy-cta`, the plan cards' buy button. It
       inverts to white on the lead card and has light and dark variants I
       have not seen in every state, and a pricing page's buy button is the
       wrong place to find that out. It stays as it is until someone looks
       at it.

       The doubled class is not a typo. `html .sy-btn` is (0,1,1) and was
       measured losing to the two-class rule that owns it; `html
       .sy-btn.sy-btn` is (0,2,1) and wins. Each selector here was checked
       against the live page rather than assumed -- the first version of this
       block did nothing at all, and read exactly like a rule that worked. */
    html .sf-create.sf-create,
    html .sy-btn.sy-btn,
    html .xcta-btn-primary.xcta-btn-primary {
        background: var(--btn-solid) !important;
        background-image: none !important;
        color: var(--btn-solid-ink) !important;
        border-color: transparent;
        box-shadow: inset 0 0 0 1px var(--btn-solid-line), var(--btn-solid-shadow);
    }
    html .sf-create.sf-create:hover,
    html .sy-btn.sy-btn:hover,
    html .xcta-btn-primary.xcta-btn-primary:hover {
        background: var(--btn-solid-hover) !important;
        background-image: none !important;
    }

    /* A pressed filter is selection, not an action -- same rule as the
       app's tabs, and the !important matches the source it is overriding. */
    html .sf-filters button[aria-pressed="true"].sf-filters-pressed,
    html .sf-filters button[aria-pressed="true"] {
        background: var(--tab-active-bg) !important;
        background-image: none !important;
        color: var(--text-primary, #f3f1f7) !important;
        box-shadow: inset 0 0 0 1px var(--tab-active-line) !important;
    }
</style>
