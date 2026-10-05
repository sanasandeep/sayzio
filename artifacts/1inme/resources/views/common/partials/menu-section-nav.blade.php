{{--
    Getting down a long menu without scrolling past everything.

    Sana, 2026-10-05: "Section jumping - suggest multi different layout type
    options: horizontal scroll tabs with all, select drop down, verticle tab
    with icon display or number (default)".

    ---- Why four shapes and not one ---------------------------------------

    None of them is better. A bar with six sections wants TABS; a cafe with
    three wants NOTHING and looks over-built with a nav; a long card on a
    tablet at a counter wants the RAIL, which stays put while the list
    moves; and past about ten sections only the DROPDOWN still fits, because
    a horizontal bar becomes a scroll inside a scroll.

    ---- No JavaScript ------------------------------------------------------

    Every one of these is an anchor link, and the browser does the scrolling
    with `scroll-behavior: smooth`. A menu is read on a phone with one bar
    of signal in a basement restaurant; a jump bar that needs a script to
    have loaded is a jump bar that sometimes does nothing.

    The dropdown is the exception and needs four lines, because a <select>
    cannot navigate on its own. It falls back to doing nothing rather than
    to being broken.

    Parameters:
      $snTargets  from MenuSectionNav::targets()
      $snNav      'tabs' | 'dropdown' | 'vertical' | 'none'
      $snMarker   'number' | 'icon' | 'none'
--}}
@php
    $snColours = \App\Modules\User\Support\MenuSectionNav::colours((array) ($menu->settings ?? []));
    $snStyle = '--sn-text: '.$snColours['section_nav_text_color'].'; --sn-bg: '.$snColours['section_nav_background_color'].'; --sn-border: '.$snColours['section_nav_border_color'].';';
    $snMark = function (array $t) use ($snMarker) {
        if ($snMarker === 'icon' && $t['icon']) {
            return '<i class="fas fa-'.e($t['icon']).'"></i>';
        }
        // "Sections without one fall back to their number" -- an icon
        // marker on a section nobody gave an icon to must not render as a
        // gap the creator cannot explain.
        if ($snMarker !== 'none') {
            return '<span class="sn-n">'.$t['number'].'</span>';
        }

        return '';
    };
@endphp

@if($snNav === 'dropdown')
    <nav class="sn sn-drop" aria-label="Jump to a section" style="{{ $snStyle }}">
        <select class="sn-select" onchange="if(this.value){location.hash=this.value}" aria-label="Jump to a section" style="{{ $snStyle }}">
            <option value="">Jump to a section…</option>
            @foreach($snTargets as $snT)
                <option value="#{{ $snT['anchor'] }}">
                    {{ $snMarker === 'number' ? $snT['number'].'. ' : '' }}{{ $snT['name'] }}
                </option>
            @endforeach
        </select>
    </nav>

@elseif($snNav === 'vertical')
    <nav class="sn sn-rail" aria-label="Jump to a section" style="{{ $snStyle }}">
        @foreach($snTargets as $snT)
            <a href="#{{ $snT['anchor'] }}" class="sn-rail-a" title="{{ $snT['name'] }}">
                {!! $snMark($snT) !!}
                <span class="sn-rail-t">{{ $snT['name'] }}</span>
            </a>
        @endforeach
    </nav>

@elseif($snNav === 'tabs')
    <nav class="sn sn-tabs" aria-label="Jump to a section" style="{{ $snStyle }}">
        {{-- "horizontal scroll tabs with all" -- All is first and goes back
             to the top, so the bar can undo itself. A jump bar you cannot
             get out of is how somebody loses the start of the menu. --}}
        <a href="#menu-top" class="sn-tab">All</a>
        @foreach($snTargets as $snT)
            <a href="#{{ $snT['anchor'] }}" class="sn-tab">
                {!! $snMark($snT) !!}
                <span>{{ $snT['name'] }}</span>
            </a>
        @endforeach
    </nav>
@endif

@once
<style>
    /* The browser does the scrolling. */
    html { scroll-behavior: smooth; }
    @media (prefers-reduced-motion: reduce) {
        html { scroll-behavior: auto; }
    }
    /* A jumped-to heading must not land under the sticky bar that sent you
       there, which is the classic version of this feature being broken. */
    .cat[id] { scroll-margin-top: 72px; }

    .sn { margin: 0 0 14px; }
    .sn-n {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 1.35em; height: 1.35em; padding: 0 .3em;
        border-radius: 6px;
        background: var(--ink-chip, rgba(128,128,128,.16));
        font-size: .82em; font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    /* ---- Scrolling tabs ------------------------------------------- */
    .sn-tabs {
        display: flex; gap: 7px;
        overflow-x: auto;
        /* Sticky, because a jump bar that scrolls away is only useful once. */
        position: sticky; top: 0; z-index: 5;
        padding: 9px 0;
        background: inherit;
        /* The scrollbar is noise on a menu; the overflow still works. */
        scrollbar-width: none;
        -webkit-overflow-scrolling: touch;
    }
    .sn-tabs::-webkit-scrollbar { display: none; }
    .sn .sn-tab {
        display: inline-flex; align-items: center; gap: 6px;
        flex: none;
        padding: 6px 13px;
        border-radius: 999px;
        border: 1px solid var(--sn-border, #d4d4d4);
        font-size: 13px; font-weight: 600;
        color: var(--sn-text, #262626); background: var(--sn-bg, #ffffff); text-decoration: none;
        white-space: nowrap;
    }
    .sn .sn-tab:hover, .sn .sn-tab:focus-visible { border-color: currentColor; }

    /* ---- Dropdown -------------------------------------------------- */
    .sn .sn-select {
        width: 100%;
        padding: 9px 12px;
        border-radius: 10px;
        border: 1px solid var(--sn-border, #d4d4d4);
        background: var(--sn-bg, #ffffff);
        color: var(--sn-text, #262626);
        font-size: 14px;
        font-family: inherit;
    }

    /* ---- Side rail -------------------------------------------------- */
    .sn-rail {
        display: flex; flex-direction: column; gap: 4px;
        position: sticky; top: 14px;
        float: left;
        width: 142px;
        margin: 0 18px 10px 0;
    }
    .sn .sn-rail-a {
        display: flex; align-items: center; gap: 8px;
        padding: 6px 10px;
        border-radius: 9px;
        font-size: 13px;
        color: var(--sn-text, #262626); background: var(--sn-bg, #ffffff); text-decoration: none;
        border: 1px solid var(--sn-border, #d4d4d4);
    }
    .sn .sn-rail-a:hover { border-color: var(--sn-text, #262626); }
    .sn-rail-t { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* A 142px rail beside a menu on a 360px screen leaves no menu. Below
       that width it lies down and becomes the tab bar it should have been. */
    @media (max-width: 720px) {
        .sn-rail {
            float: none; width: auto; flex-direction: row;
            overflow-x: auto; gap: 7px;
            top: 0; padding: 9px 0; margin: 0 0 10px;
            scrollbar-width: none;
        }
        .sn-rail::-webkit-scrollbar { display: none; }
        .sn .sn-rail-a {
            flex: none;
            border: 1px solid var(--sn-border, #d4d4d4);
            border-radius: 999px;
            opacity: 1;
        }
    }

    /* ---- The marker on a heading ------------------------------------ */
    .cat > h2 .sn-n,
    .cat > h2 .sn-i { margin-right: .45em; }
    .sn-i { opacity: .75; font-size: .85em; }
</style>
@endonce
