{{--
    The rail navigation: a permanent icon rail plus a panel showing one
    section at a time.

    WHAT THIS REPLACES AND WHY

    The classic sidebar put eight ungrouped links and ten collapsible
    groups -- 53 destinations -- in a single scrolling column. Five of
    those groups had three items or fewer. The rail splits that into
    seven sections of five to ten items each, which is the shape this
    structure wants; a rail icon that opens a panel with one link in it
    looks broken, so the regroup was not optional.

    NOTHING HERE RETYPES A NAV LINK

    Every link lives in user/partials/nav/_*.blade.php, moved there
    verbatim from the layout. The routes, permission gates, icons,
    --nav-tint values and badge counts are the ones that already
    shipped. A regroup must not double as a chance to mistype a route,
    so the only new markup in this feature is the rail itself.

    THE PANEL KEEPS THE ORIGINAL GROUP HEADERS

    Each section partial still carries the group header it came with, so
    "Audience" opens to a labelled AUDIENCE & COMMUNITY list followed by
    a labelled GROWTH & MARKETING one, rather than ten links under one
    invented heading. That is also why none of the moved markup had to
    be unwrapped: the collapsible groups still collapse, inside the
    panel, exactly as before. Home is the one section that needs a
    header of its own, because its eight links never had a group.

    ONE PARTIAL, BOTH SCREENS

    Included once for the desktop sidebar and once for the mobile
    drawer. That is not tidiness: the two navs had already drifted, and
    Creator Profile and Linked identifiers existed ONLY in the mobile
    drawer, unreachable from the nav on a laptop. Sharing the markup is
    what makes that class of bug impossible rather than merely fixed.
    `$railSurface` turns the rail on its side for the drawer; it does
    not change which links render.

    THE DESKTOP SHELL IS ONE CARD

    Direction A of the shell boards: rail and panel are two columns of a
    single floating card, inset from the window, rather than a slab
    pinned to the edge. That geometry is why the brand mark, the
    workspace switcher and the account row live HERE and not in the
    layout -- the card has no full-width strip across its top for them
    to sit in. The layout keeps its own copies for the classic sidebar,
    which still looks the way it did.

    The account row is also the answer to three separate complaints that
    turned out to be one: the plan card, the Switch to admin button and
    the avatar-plus-logout strip were three stacked blocks competing for
    the foot of the sidebar. They are four menu items now. The Account
    group that carried a second Settings link and the badge chips is
    gone outright -- Settings is a rail section, and badges render on
    the dashboard and on the badges settings page, so nothing became
    unreachable.

    COLLAPSING

    In rail mode the existing edge toggle hides the PANEL and keeps the
    rail, which is what the structure is for. So the shell must not take
    the `collapsed` class here: those rules restyle .sidebar-link into
    44px icons, which is the classic sidebar's answer to the same
    question and would fight this one.

    Expects nothing. Reads $railSurface and $__can when they are set.
--}}
@php
    use App\Modules\User\Services\WorkspacePermissions as RailWP;
    use App\Modules\User\Support\NavRailSections;

    $railSections = NavRailSections::SECTIONS;
    $railOrder    = NavRailSections::order();
    $railActive   = NavRailSections::activeKey();
    $railSurface  = $railSurface ?? 'desktop';

    // The card chrome -- brand, switcher, account row -- is the desktop
    // shell. The drawer is already a card with its own header and
    // footer, so adding a second set would double every one of them.
    $railCard = $railSurface === 'desktop';

    // The layout resolves $__can once and both sidebars read it. This
    // partial resolves it only when nobody already has, so it can also
    // be rendered on its own -- which is what lets a test assert the
    // permission gates still bite after the markup moved, without
    // standing up the whole dashboard around it.
    $__can = $__can ?? [
        'tasks_view'     => RailWP::userCan('tasks.view'),
        'vault_view'     => RailWP::userCan('vault.view'),
        'files_view'     => RailWP::userCan('files.view'),
        'links_view'     => RailWP::userCan('links.view'),
        'forms_view'     => RailWP::userCan('forms.view'),
        'qr_view'        => RailWP::userCan('qr.view'),
        'links_create'   => RailWP::userCan('links.create'),
        'inbox_view'     => RailWP::userCan('inbox.view'),
        'posts_view'     => RailWP::userCan('posts.view'),
        'followers_view' => RailWP::userCan('followers.view'),
        'stats_view'     => RailWP::userCan('stats.view'),
        'settings_view'  => RailWP::userCan('settings.view'),
        'referrals_view' => RailWP::userCan('referrals.view'),
    ];
@endphp

@once
<style>
    /* Tokens only. The sidebar already has its own token set --
       --sidebar-link, --sidebar-active-bg and the rest -- redefined per
       theme and again under [data-rail-appearance] / [data-panel-appearance],
       which is how the light/dark sidebar options keep working here
       without this file knowing they exist. A colour literal in this
       block would be a hole in exactly that mechanism. */
    /* Positioned, because the account block is a child of the wrap
       rather than of either column: minimised it sits at the foot of
       the rail, expanded at the foot of the panel, and it cannot be
       inside one column and appear at the foot of the other. */
    .navrail-wrap { position: relative; display: flex; flex: 1 1 auto; min-height: 0; }

    .navrail {
        flex: 0 0 56px;
        width: 56px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: 8px 0 12px;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        border-right: 1px solid var(--border-strong);
    }
    /* The rule, not a tint, is what separates rail from panel. In dark
       the two grounds already differ; in light they are both white, and
       inventing a grey here would be a colour literal that the sidebar
       appearance options could not override. */

    /* A square icon tile. It was a 56x45 rounded rectangle carrying a
       9.5px label, which reads as a box rather than an icon - "its more
       like rounded corner rectangle" (Sana, against the reference). The
       label becomes the accessible name and the tooltip instead of
       shipping two type sizes in a 56px column. */
    .navrail-btn {
        position: relative;
        width: 40px;
        height: 40px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 10px;
        color: var(--sidebar-link);
        cursor: pointer;
        transition: background .2s cubic-bezier(.4, 0, .2, 1),
                    color .2s cubic-bezier(.4, 0, .2, 1),
                    transform .12s ease;
    }
    .navrail-btn:active { transform: scale(.94); }
    /* The tile glyph is a sprite reference now, so it is sized in px
       rather than by font-size. The <i> rule stays for the workspace
       tile, whose symbol still comes from the database. */
    .navrail-btn i { font-size: 16px; line-height: 1; }
    .navrail-btn .nav-icon { width: 16px; height: 16px; }
    /* Kept in the DOM: it is the button's accessible name. */
    .navrail-btn-label {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }
    .navrail-btn:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }
    .navrail-btn:focus-visible {
        outline: 2px solid var(--sidebar-active-border);
        outline-offset: 2px;
    }
    /* The active tile takes the brand accent, not --sidebar-active-bg,
       which resolved to a beige rgb(237,235,230) on the live page and
       read as "nothing is selected". The gradient is the pair the rest
       of the app already uses for accent fills, so it tracks every
       theme and every appearance preset the way a literal could not. */
    .navrail-btn.is-active {
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
        color: #fff;
        border-color: transparent;
        box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--accent) 70%, transparent);
    }
    .navrail-btn.is-active :is(i, .nav-icon) { color: #fff; }
    /* is-current marks the section the CURRENT PAGE lives in. It stays
       marked while you browse another section's panel, so the rail can
       answer "where am I" and "what am I looking at" at the same time
       without the two states being confusable. */
    .navrail-btn.is-current::after {
        content: '';
        position: absolute;
        left: 3px;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 14px;
        border-radius: 999px;
        background: var(--accent);
    }
    /* On the active tile the accent marker would sit on the accent, so
       the tile itself is already the answer to "where am I". */
    .navrail-btn.is-active.is-current::after { background: #fff; opacity: 0; }

    /* The brand mark heads the rail, because the card has no full-width
       strip for a logo row. */
    .navrail-brand {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        margin-bottom: 6px;
        border-radius: 10px;
        flex: 0 0 auto;
    }

    /* The panel is a column, not a scroller: the switcher stays at its
       head and the account row at its foot while the sections scroll
       between them. */
    .navrail-panel {
        position: relative;
        flex: 1 1 auto;
        min-width: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .navrail-panel-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        /* The account block floats over the panel's foot, so the last
           links need room to clear it. 144px is the block's measured
           height (plan strip + row + padding) with headroom, and a test
           asserts the block never outgrows it. */
        padding: 6px 10px 144px;
    }
    /* Minimised, the account block is in the rail instead, so the
       flyout gets its ordinary bottom padding back. */
    .navrail-wrap[data-rail-min="1"] .navrail-panel-scroll { padding-bottom: 16px; }
    /* Matches the group headers the panel sits among: the classic
       markup uses text-[10px] font-bold uppercase tracking-[0.15em]
       with pt-5 pb-1.5 px-3, so Home's header has to as well or it
       reads as a different kind of thing. */
    .navrail-section-head {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .15em;
        color: var(--text-faint);
        padding: 20px 12px 6px;
    }

    /* ---- the account row --------------------------------------------
       Four destinations that were three stacked blocks: the plan card,
       the Switch to admin button and the avatar strip with its logout
       icon. */
    /* Expanded it spans the panel's foot; minimised it narrows to the
       rail and shows the avatar alone -- "login profile pic at bottom
       should be visible just like logo on top" (Sana). One block in
       two places rather than two blocks: a second copy would mean a
       second account menu, and two menus drift. */
    .navrail-account {
        position: absolute;
        /* Above the minimised flyout's 60. The account block is
           positioned, so it opens a stacking context and its menu's own
           z-index is sealed inside it -- at 2 the menu rendered BEHIND
           the flyout, which elementFromPoint caught and reading the
           rule did not. */
        z-index: 70;
        left: 56px;
        right: 0;
        bottom: 0;
        padding: 8px;
        border-top: 1px solid var(--border-strong);
        background: var(--nav-surface, var(--bg-card));
        border-radius: 0 0 15px 0;
    }
    .navrail-wrap[data-rail-min="1"] .navrail-account {
        left: 0;
        right: auto;
        /* The card's width, not the rail's fixed 56: the same 2px
           overhang the rail itself had, and the same answer. */
        width: 100%;
        padding: 6px 7px 8px;
        border-radius: 0 0 15px 15px;
    }
    .navrail-wrap[data-rail-min="1"] .navrail-account-row { justify-content: center; padding: 6px; }
    /* The name, the plan line and the chevron have nowhere to go in a
       56px column; the avatar is the whole control there. */
    .navrail-wrap[data-rail-min="1"] .navrail-account-row > :not(.navrail-account-avatar):not(.navrail-btn-label) {
        display: none;
    }
    .navrail-account-anchor { position: relative; }

    /* The collapse control lives in whichever surface is on screen: in
       the panel while it is open, in the top bar once it is not. One
       control in two places rather than one that is always in the same
       place and sometimes points at nothing. */
    /* MEASURED: the toggle's 26px box and the switcher's chevron
       overlapped by 12px -- two controls occupying the same corner, one
       of them invisible under the other. The switcher gives up the
       width rather than the toggle moving, because the toggle belongs
       on the panel's corner and the switcher has room to spare. */
    .rail-card .navrail-panel #workspace-switcher { padding-right: 40px; }

    .navrail-panel-toggle {
        position: absolute;
        top: 15px;
        right: 10px;
        z-index: 3;
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--text-faint);
        font-size: 14px;
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    .navrail-panel-toggle:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }
    .navrail-panel-toggle:focus-visible {
        outline: 2px solid var(--sidebar-active-border);
        outline-offset: 2px;
    }
    .navrail-account-row {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 7px 8px;
        border: 0;
        border-radius: 10px;
        background: transparent;
        text-align: left;
        cursor: pointer;
        transition: background .18s ease;
    }
    .navrail-account-row:hover,
    .navrail-account[data-open="1"] .navrail-account-row {
        background: var(--sidebar-link-hover-bg);
    }
    .navrail-account-row:focus-visible {
        outline: 2px solid var(--sidebar-active-border);
        outline-offset: 2px;
    }
    .navrail-account-avatar {
        width: 28px;
        height: 28px;
        flex: 0 0 auto;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
    }
    .navrail-account-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .navrail-account-name {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .navrail-account-plan {
        font-size: 10.5px;
        color: var(--text-dimmed);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    /* Opens upward: the row is at the foot of the card, so a menu
       hanging below it would be off the bottom of the window.
       Anchored to the row rather than to a fixed offset, so the plan
       strip above it can come and go without moving the menu. */
    .navrail-account-menu {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 100%;
        margin-bottom: 6px;
        z-index: 70;
        padding: 6px;
        border-radius: 13px;
        background: var(--bg-card);
        border: 1px solid var(--border-strong);
        box-shadow: var(--card-shadow-hover);
    }
    /* The rows are not all the same element: two are links, one is an
       inert div, and the two POSTs have to stay forms. So the row
       styling names the row itself in each case rather than every
       direct child, which would have landed on the <form> and left the
       <button> inside it unstyled. */
    .navrail-account-menu > form { display: block; }
    .navrail-account-row-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 9px 11px;
        border: 0;
        border-radius: 9px;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
        color: var(--sidebar-link);
        text-align: left;
        cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    .navrail-account-row-item:hover {
        background: var(--sidebar-link-hover-bg);
        color: var(--sidebar-link-hover-text);
    }
    .navrail-account-row-item[aria-disabled="true"] { cursor: default; }
    .navrail-account-row-item[aria-disabled="true"]:hover {
        background: transparent;
        color: var(--sidebar-link);
    }
    .navrail-account-menu i { width: 14px; font-size: 12px; text-align: center; }
    .navrail-account-menu .nav-icon { width: 14px; height: 14px; flex: none; }
    /* The plan row carries a label AND a status, and side by side in a
       218px menu both of them wrapped to two lines -- measured on the
       live page, not guessed. They stack instead, which also makes the
       row read the same way as the account row below it. */
    .navrail-account-stack {
        display: flex;
        flex-direction: column;
        min-width: 0;
        line-height: 1.35;
    }
    .navrail-account-stack > span {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .navrail-account-menu .navrail-account-aside {
        font-size: 10.5px;
        font-weight: 600;
        color: var(--accent);
    }
    .navrail-account-row-item[aria-disabled="true"] .navrail-account-aside {
        color: var(--text-dimmed);
    }
    /* Logging out is destructive and switching to admin is an
       elevation. Neither waits for a hover to say so: a menu where
       every row is the same grey makes you read four labels to find
       the one you came for. Both colours are per-theme tokens, unlike
       the two literals the classic sidebar's .logout-btn hard-codes. */
    .navrail-account-menu .navrail-account-out { color: var(--c-danger); }
    .navrail-account-menu .navrail-account-out:hover {
        background: var(--c-danger-soft);
        color: var(--c-danger);
    }
    .navrail-account-menu .navrail-account-admin { color: var(--accent); }
    .navrail-account-menu .navrail-account-admin:hover {
        background: color-mix(in srgb, var(--accent) 12%, transparent);
        color: var(--accent);
    }
    .navrail-account-sep {
        height: 1px;
        margin: 4px 6px;
        padding: 0;
        background: var(--border-strong);
    }
    /* Minimised the menu cannot open over a 56px column, so it opens
       beside the rail, bottom-aligned with the avatar that summoned
       it -- the same move the section flyout makes, and measured to
       land on the same left edge as it. The anchor goes static so the
       offset is read from the card's width rather than from the
       account block's padding box. */
    .navrail-wrap[data-rail-min="1"] .navrail-account-anchor { position: static; }
    .navrail-wrap[data-rail-min="1"] .navrail-account-menu {
        left: calc(100% + 10px);
        right: auto;
        bottom: 0;
        width: 240px;
        margin-bottom: 0;
    }

    /* ---- the plan strip ---------------------------------------------
       "Panel bottom can have about plan info with upgrade option"
       (Sana). It sits above the account row when there is width for
       it; minimised, the plan row inside the account menu takes over,
       so the information is never only in one of the two states. */
    .navrail-plan {
        margin-bottom: 6px;
        padding: 9px 10px;
        border-radius: 11px;
        background: var(--sidebar-active-bg);
    }
    .navrail-plan-top {
        display: flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 6px;
    }
    .navrail-plan-name {
        flex: 1;
        min-width: 0;
        font-size: 11.5px;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .navrail-plan-gem {
        width: 18px;
        height: 18px;
        flex: none;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        font-size: 8px;
        color: #fff;
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
    }
    .navrail-plan-note {
        display: block;
        font-size: 10px;
        line-height: 1.4;
        color: var(--text-dimmed);
    }
    .navrail-plan-cta {
        display: block;
        margin-top: 7px;
        padding: 6px 8px;
        border-radius: 8px;
        text-align: center;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #fff;
        background: linear-gradient(135deg, var(--accent), var(--accent-light));
        text-decoration: none;
    }
    .navrail-plan-cta:hover { filter: brightness(1.08); }

    /* ---- the two utility tiles --------------------------------------
       Minimised only. Expanded, the switcher is the panel's head row
       and Folders is the first group inside Home, so a rail tile for
       either would be the same destination twice. */
    .navrail-util-sep {
        width: 24px;
        height: 1px;
        margin: 5px 0;
        background: var(--border-strong);
        flex: none;
    }
    /* The first version made the workspace tile a filled colour block
       and left folders a bare glyph, so the pair matched neither each
       other nor the seven tiles below them. Both are ordinary rail
       tiles now; the workspace's colour is a dot on the corner, which
       is enough to tell two workspaces apart without the tile
       becoming a different kind of object. */
    .navrail-util-dot {
        position: absolute;
        right: 4px;
        bottom: 4px;
        width: 8px;
        height: 8px;
        border-radius: 999px;
        border: 2px solid var(--nav-surface, var(--bg-card));
    }

    /* The drawer turns the rail on its side. A 72px column inside a
       phone-width drawer would leave the panel too narrow to read. */
    .navrail-mobile { flex-direction: column; }
    .navrail-mobile .navrail {
        flex: 0 0 auto;
        width: 100%;
        flex-direction: row;
        justify-content: flex-start;
        gap: 2px;
        padding: 8px;
        overflow-x: auto;
        overflow-y: hidden;
        border-right: 0;
        border-bottom: 1px solid var(--border-strong);
    }
    /* 64px was room for the label; the label is the tooltip now. */
    .navrail-mobile .navrail-btn { flex: 0 0 auto; width: 40px; }
    .navrail-mobile .navrail-btn.is-current::after {
        left: 50%;
        top: auto;
        bottom: 2px;
        transform: translateX(-50%);
        width: 18px;
        height: 3px;
    }

    /* The four white literals that came across with the moved markup,
       on tokens instead. These were real light-mode failures, not the
       legitimate kind: white text on a red or blue badge is correct in
       both themes and was left alone, but text-white/30 on a white
       ground is a folder list you cannot read. The rules live here
       because nav-rail is the only thing that includes those partials. */
    .navrail-panel .navrail-subtle-btn {
        color: var(--text-faint);
        transition: color .15s ease, background .15s ease;
    }
    .navrail-panel .navrail-subtle-btn:hover {
        color: var(--text-muted);
        background: var(--sidebar-link-hover-bg);
    }
    .navrail-panel .folder-sidebar-item { color: var(--sidebar-link); }
    .navrail-panel .folder-sidebar-item:hover {
        color: var(--sidebar-link-hover-text);
        background: var(--sidebar-link-hover-bg);
    }
    .navrail-panel .folder-sidebar-item.is-current {
        color: var(--sidebar-active-text);
        background: var(--sidebar-active-bg);
    }
    .navrail-panel .navrail-soft-pill {
        background: var(--sidebar-active-bg);
        color: var(--text-muted);
    }

    /* ---- the card ---------------------------------------------------
       Direction A: one floating card holding both columns, inset from
       the window. The radii are split across the two columns rather
       than clipped by the shell, because the shell must stay
       overflow:visible for the flyout below. */
    .sidebar-shell.rail-card {
        top: 14px;
        bottom: 14px;
        left: 14px;
        border-radius: 16px;
        border: 1px solid var(--border-strong);
        /* Per-theme, including aurora's, rather than one shadow plus a
           dark-mode override that the third theme would never reach. */
        box-shadow: var(--card-shadow-hover);
    }
    .rail-card .navrail { border-radius: 15px 0 0 15px; }
    .rail-card .navrail-panel { border-radius: 0 15px 15px 0; }
    /* Minimised, the card IS the rail: it rounds on all four, drops the
       divider it no longer divides anything from, and takes the card's
       own width rather than its fixed 56. Measured: at a fixed 56 the
       rail is 2px wider than the card's content box, so it bleeds past
       the border it is supposed to sit inside. */
    .rail-card .navrail-wrap[data-rail-min="1"] .navrail {
        border-radius: 15px;
        border-right: 0;
        flex-basis: 100%;
        width: 100%;
    }

    /* ---- minimised: the panel becomes a flyout ----------------------
       At 72px the panel was squeezed to 20px and still rendered, so
       clicking a rail icon did nothing you could see. It now floats
       beside the rail as its own card.

       Positioned against the SHELL, which is position:fixed and so a
       containing block whatever else is on it. The earlier version was
       position:fixed with a hard left offset, which happened to land
       correctly only because the shell carries a transform; inset the
       card and that coincidence moves the flyout 14px. `calc(100% +
       10px)` is read off the shell's own width, so the gap holds at
       any rail width. */
    .navrail-wrap[data-rail-min="1"] .navrail-panel {
        position: absolute;
        left: calc(100% + 10px);
        top: 0;
        bottom: 0;
        width: 248px;
        background: var(--nav-surface, var(--bg-card));
        border: 1px solid var(--border-strong);
        border-radius: 15px;
        box-shadow: var(--card-shadow-hover);
        z-index: 60;
        opacity: 0;
        transform: translateX(-8px);
        pointer-events: none;
        transition: opacity .18s ease, transform .22s cubic-bezier(.4, 0, .2, 1);
    }
    .navrail-wrap[data-rail-min="1"][data-rail-open="1"] .navrail-panel {
        opacity: 1;
        transform: translateX(0);
        pointer-events: auto;
    }
    /* The drawer is already a flyout; it must not get a second one. */
    .navrail-mobile[data-rail-min="1"] .navrail-panel {
        position: static; width: auto; opacity: 1; transform: none;
        pointer-events: auto; border: 0; box-shadow: none; border-radius: 0;
    }

    /* ---- the groups inside a panel ----------------------------------
       Each panel IS one section, and the classic group partials wrap
       their links in an accordion on top of that. On the live page 16
       of 18 groups were collapsed, so most links sat behind a second
       click inside a section you had already chosen. The headers stay
       as headers; they just stop being buttons. */
    .navrail-panel .sidebar-group-toggle {
        pointer-events: none;
        cursor: default;
        padding-top: 14px;
        padding-bottom: 4px;
    }
    .navrail-panel .sidebar-group-toggle :is(i, svg) { display: none; }

    /* MEASURED: the rows sat 2px apart, which reads as one block of
       text rather than a list of destinations. The nav partials set
       their own space-y-0.5, so the margin adds to it rather than
       fighting it: 2 + 6 = 7px between 46px rows. */
    .navrail-panel .sidebar-link {
        margin-bottom: 6px;
        transition: background .18s ease, color .18s ease;
    }

    @media (prefers-reduced-motion: reduce) {
        .navrail-btn,
        .navrail-btn:active,
        .navrail-wrap[data-rail-min="1"] .navrail-panel,
        .navrail-account-row,
        .navrail-account-menu > *,
        .navrail-panel .sidebar-link,
        .navrail-panel .navrail-subtle-btn { transition: none; transform: none; }
    }
</style>
@endonce

{{-- `sidebarMode` comes from the shell's x-data above; reading it through
     `this` lets the drawer, which has no such parent, resolve undefined
     instead of throwing. --}}
<div class="navrail-wrap navrail-{{ $railSurface }}"
     x-data="{
        railSection: '{{ $railActive }}',
        railOpen: false,
        get railMin() { return this.sidebarMode === 'icons'; },
        pick(key) {
            if (this.railMin && this.railOpen && this.railSection === key) { this.railOpen = false; return; }
            this.railSection = key;
            this.railOpen = true;
        },
        /* The two utility tiles do NOT get their own flyouts. The
           workspace switcher and the folder list are single Alpine
           components with their own ids, and a second copy beside the
           rail would be a second #workspace-switcher on the page. So
           the tile opens the panel these already live in and tells the
           component to open itself. */
        utility(what) {
            this.railSection = 'home';
            this.railOpen = true;
            this.$nextTick(() => window.dispatchEvent(new CustomEvent(
                what === 'workspace' ? 'open-workspace-switcher' : 'open-nav-folders'
            )));
        }
     }"
     :data-rail-min="railMin ? '1' : '0'"
     :data-rail-open="railOpen ? '1' : '0'"
     @click.outside="railOpen = false"
     @keydown.escape.window="railOpen = false">

    <div class="navrail" role="tablist" aria-label="Navigation sections">
        @if($railCard)
            <a href="{{ route('user.dashboard') }}" class="navrail-brand" title="{{ config('app.name', 'Sayzio') }}">
                @include('common.partials.brand-logo', ['variant' => 'icon', 'height' => 'h-8'])
            </a>
            @php $railWs = app()->bound('current_workspace') ? app('current_workspace') : null; @endphp
            @if($railWs)
                <button type="button" class="navrail-btn" x-show="railMin" x-cloak
                        @click="utility('workspace')" title="Workspace: {{ $railWs->name }}">
                    {{-- Stays on Font Awesome on purpose: iconSymbol() is a
                         stored value and nothing constrains what it can be, so
                         a sprite reference would render an empty box for any
                         symbol the map has never seen. --}}
                    <i class="fas {{ $railWs->iconSymbol() }}" aria-hidden="true"></i>
                    <span class="navrail-util-dot" style="background: {{ $railWs->iconColor() }}" aria-hidden="true"></span>
                    <span class="navrail-btn-label">Workspace: {{ $railWs->name }}</span>
                </button>
            @endif
            <button type="button" class="navrail-btn" x-show="railMin" x-cloak
                    @click="utility('folders')" title="Folders">
                <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-folder"></use></svg>
                <span class="navrail-btn-label">Folders</span>
            </button>
            <div class="navrail-util-sep" x-show="railMin" x-cloak aria-hidden="true"></div>
        @endif
        @foreach($railOrder as $railKey)
            @php $railItem = $railSections[$railKey]; @endphp
            <button type="button"
                    role="tab"
                    id="navrail-tab-{{ $railSurface }}-{{ $railKey }}"
                    aria-controls="navrail-panel-{{ $railSurface }}-{{ $railKey }}"
                    :aria-selected="railSection === '{{ $railKey }}' ? 'true' : 'false'"
                    @click="pick('{{ $railKey }}')"
                    class="navrail-btn {{ $railKey === $railActive ? 'is-active is-current' : '' }}"
                    :class="railSection === '{{ $railKey }}' ? 'is-active' : ''"
                    title="{{ $railItem['label'] }}">
                <svg class="nav-icon" aria-hidden="true"><use href="#i-{{ \App\Modules\User\Support\NavIcons::lucide($railItem['icon']) }}"></use></svg>
                <span class="navrail-btn-label">{{ $railItem['label'] }}</span>
            </button>
        @endforeach
    </div>

    <div class="navrail-panel">
        @if($railCard)
            {{-- Deliberately OUTSIDE the wrapper below: that wrapper shadows
                 `sidebarMode` so the switcher always reads 'full', and a
                 collapse control that cannot see the real mode would be
                 reading a lie. --}}
            <button type="button" class="navrail-panel-toggle" x-show="!railMin" x-cloak
                    @click="setSidebar('icons')"
                    title="Collapse sidebar" aria-label="Collapse sidebar">
                <svg class="nav-icon" aria-hidden="true"><use href="#i-panel-left"></use></svg>
            </button>
            {{-- The switcher hides its own text on `sidebarMode === 'icons'`,
                 which was right when it sat in a 72px column. In the panel it
                 is never narrow: minimised, the panel is a 248px flyout. A
                 nested x-data shadows the shell's property for this subtree
                 only, so the switcher reads 'full' here and the rail reads the
                 real mode two lines up. --}}
            <div x-data="{ sidebarMode: 'full' }" class="flex-shrink-0">
                @auth
                    @include('user.partials.workspace-switcher')
                @endauth
            </div>
        @endif

        {{-- Open every group the panel inherited from the classic markup.
             Done through Alpine's own data rather than an !important that
             fights x-show, so a group that genuinely wants to collapse
             later still can. --}}
        <div class="navrail-panel-scroll sidebar-nav-scroll"
             x-init="$nextTick(() => $el.querySelectorAll('[x-data]').forEach(n => {
                try { const d = Alpine.$data(n); if (d && 'open' in d) d.open = true; } catch (e) {}
             }))">
            {{-- Every section renders; the server picks which one is visible
                 so the panel is right in the first painted frame. x-cloak on
                 all seven would blank the whole nav until Alpine boots, and
                 leaving the initial state to Alpine alone would show the
                 wrong section for a frame. Alpine takes over after init. --}}
            @foreach($railOrder as $railKey)
                <div role="tabpanel"
                     id="navrail-panel-{{ $railSurface }}-{{ $railKey }}"
                     aria-labelledby="navrail-tab-{{ $railSurface }}-{{ $railKey }}"
                     x-show="railSection === '{{ $railKey }}'"
                     @if($railKey !== $railActive) style="display:none" @endif>
                    @include($railSections[$railKey]['partial'])
                </div>
            @endforeach
        </div>

    </div>

        @if($railCard && auth()->check())
            @php
                $railUser  = auth()->user();
                $railPlan  = $railUser->plan->name ?? 'Free';
                $railAdmin = !session('impersonate_user_id') && $railUser->hasActiveAdminAccount();
            @endphp
            <div class="navrail-account"
                 x-data="{ menu: false }"
                 :data-open="menu ? '1' : '0'"
                 @click.outside="menu = false"
                 @keydown.escape="menu = false">

                {{-- The plan strip. Which plan you are on is standing
                     information, so it is on the surface rather than one click
                     inside a menu. What stays conditional is the ASK:
                     canUpgradePlan() is false only when nothing higher exists,
                     and showing Upgrade to someone with nothing to upgrade to
                     is a dark pattern, so that account gets the plan name and a
                     quiet line instead. --}}
                <div class="navrail-plan" x-show="!railMin" x-cloak>
                    <div class="navrail-plan-top">
                        <span class="navrail-plan-gem" aria-hidden="true"><svg class="nav-icon" aria-hidden="true"><use href="#i-gem"></use></svg></span>
                        <span class="navrail-plan-name">{{ $railPlan }} plan</span>
                    </div>
                    @if($railUser->canUpgradePlan())
                        <span class="navrail-plan-note">More links, analytics and custom domains on the higher plans.</span>
                        <a href="{{ route('user.upgrade') }}" class="navrail-plan-cta">Compare plans</a>
                    @else
                        <span class="navrail-plan-note">You are on the highest plan. Everything is unlocked.</span>
                    @endif
                </div>

                <div class="navrail-account-anchor">
                <div class="navrail-account-menu"
                     x-show="menu"
                     x-cloak
                     x-transition.opacity.duration.150ms
                     role="menu">
                    {{-- Minimised there is no room for the strip above, so the
                         plan moves into the menu. One of the two is always on
                         screen and never both. --}}
                    @if($railUser->canUpgradePlan())
                        <a href="{{ route('user.upgrade') }}" role="menuitem" class="navrail-account-row-item" x-show="railMin" x-cloak>
                            <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-gem"></use></svg>
                            <span class="navrail-account-stack">
                                <span>{{ $railPlan }} plan</span>
                                <span class="navrail-account-aside">Compare plans</span>
                            </span>
                        </a>
                    @else
                        <div role="menuitem" aria-disabled="true" class="navrail-account-row-item" x-show="railMin" x-cloak>
                            <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-gem"></use></svg>
                            <span class="navrail-account-stack">
                                <span>{{ $railPlan }} plan</span>
                                <span class="navrail-account-aside">Everything unlocked</span>
                            </span>
                        </div>
                    @endif

                    @if($__can['settings_view'])
                        <a href="{{ route('user.profile.edit') }}" role="menuitem" class="navrail-account-row-item" data-account-settings-link>
                            <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-sliders-horizontal"></use></svg>
                            <span>Settings</span>
                        </a>
                    @endif

                    @if($railAdmin)
                        <form action="{{ route('user.switch-to-admin') }}" method="POST">
                            @csrf
                            <button type="submit" role="menuitem" class="navrail-account-row-item navrail-account-admin">
                                <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-shield-user"></use></svg>
                                <span>Switch to admin</span>
                            </button>
                        </form>
                    @endif

                    <div class="navrail-account-sep" aria-hidden="true"></div>

                    <form action="{{ route('user.logout') }}" method="POST">
                        @csrf
                        <button type="submit" role="menuitem" class="navrail-account-row-item navrail-account-out">
                            <svg class="nav-icon" aria-hidden="true" aria-hidden="true"><use href="#i-log-out"></use></svg>
                            <span>Log out</span>
                        </button>
                    </form>
                </div>

                <button type="button"
                        class="navrail-account-row"
                        @click="menu = !menu"
                        :aria-expanded="menu ? 'true' : 'false'"
                        aria-haspopup="menu">
                    <span class="navrail-account-avatar" aria-hidden="true">
                        <img src="{{ $railUser->resolveAvatarUrl() }}"
                             alt=""
                             onerror="this.style.display='none';this.parentNode.textContent='{{ strtoupper(substr($railUser->name, 0, 1)) }}';">
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="navrail-account-name block">{{ $railUser->name }}</span>
                        <span class="navrail-account-plan block">{{ $railPlan }}</span>
                    </span>
                    {{-- Alpine swaps the symbol rather than the class: with a
                         sprite the glyph is the href, not the class name. --}}
                    <svg class="nav-icon text-[10px]"
                         style="color: var(--text-faint);"
                         aria-hidden="true"><use :href="menu ? '#i-chevron-down' : '#i-ellipsis'" href="#i-ellipsis"></use></svg>
                    <span class="navrail-btn-label">Account menu</span>
                </button>
                </div>
            </div>
        @endif
</div>
