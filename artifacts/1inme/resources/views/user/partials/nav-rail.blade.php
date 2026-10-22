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
       theme and again under [data-sidebar-appearance], which is how the
       light/dark sidebar options keep working here without this file
       knowing they exist. A colour literal in this block would be a
       hole in exactly that mechanism. */
    .navrail-wrap { display: flex; flex: 1 1 auto; min-height: 0; }

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
    .navrail-btn i { font-size: 16px; line-height: 1; }
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
       read as "nothing is selected". --accent is the one token that is
       the workspace's own colour in every theme and appearance. */
    .navrail-btn.is-active {
        background: var(--accent);
        color: #fff;
        border-color: transparent;
        box-shadow: 0 6px 16px -6px color-mix(in srgb, var(--accent) 70%, transparent);
    }
    .navrail-btn.is-active i { color: #fff; }
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

    .navrail-panel {
        flex: 1 1 auto;
        min-width: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior: contain;
        padding: 6px 10px 16px;
    }
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

    /* ---- minimised: the panel becomes a flyout ----------------------
       At 72px the panel was squeezed to 20px and still rendered, so
       clicking a rail icon did nothing you could see. It now floats
       beside the rail. This works because .sidebar-shell and
       .navrail-wrap are both overflow:visible - verified on the live
       page before this was written; a clipping ancestor would have
       needed the flyout in a portal instead. */
    .navrail-wrap[data-rail-min="1"] .navrail-panel {
        position: fixed;
        left: 56px;
        top: 64px;
        bottom: 12px;
        width: 248px;
        background: var(--nav-surface, var(--bg-card));
        border: 1px solid var(--border-strong);
        border-radius: 0 14px 14px 0;
        box-shadow: 0 24px 60px -20px rgba(0, 0, 0, .45);
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

    .navrail-panel .sidebar-link { transition: background .18s ease, color .18s ease; }

    @media (prefers-reduced-motion: reduce) {
        .navrail-btn,
        .navrail-btn:active,
        .navrail-wrap[data-rail-min="1"] .navrail-panel,
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
        }
     }"
     :data-rail-min="railMin ? '1' : '0'"
     :data-rail-open="railOpen ? '1' : '0'"
     @click.outside="railOpen = false"
     @keydown.escape.window="railOpen = false">

    <div class="navrail" role="tablist" aria-label="Navigation sections">
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
                <i class="fas {{ $railItem['icon'] }}" aria-hidden="true"></i>
                <span class="navrail-btn-label">{{ $railItem['label'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Open every group the panel inherited from the classic markup.
         Done through Alpine's own data rather than an !important that
         fights x-show, so a group that genuinely wants to collapse
         later still can. --}}
    <div class="navrail-panel sidebar-nav-scroll"
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
