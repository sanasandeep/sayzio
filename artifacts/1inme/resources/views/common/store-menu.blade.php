@php
    /** @var \App\Modules\User\Models\Link $link */
    $menu = $link->storeMenu()->with(['categories', 'products'])->first();
    $accent = $menu->accent_color ?: '#3d6bff';
    $currency = $menu->currency ?: 'USD';
    $staffMode = $staffMode ?? false;
    $isOrder = $staffMode || ($menu->isOrderMode() && $menu->acceptingOrders());
    $title = $link->title ?: $link->alias;

    // Sections, their sub-sections and their products -- and which of
    // those a visitor may see. One definition, shared with the restaurant
    // menu and with the editor, so "hidden" means the same thing in all
    // three.
    $tree = \App\Modules\User\Support\MenuTree::build($menu->categories, $menu->products);

    // The sections a block may be pinned after -- top level only, because a
    // sub-section is drawn inside its parent and a block between the two
    // would land in the middle of one card's worth of dishes.
    $blkSectionIds = collect($tree)->map(fn ($n) => (int) $n['category']->id)->all();

    // How this menu writes a price -- the code, a symbol, or nothing; before
    // or after; with the decimals the currency actually has. One definition,
    // shared with the cart JavaScript below and with the WhatsApp message,
    // so the three can never disagree about what a number looks like.
    $money = \App\Modules\User\Support\MenuMoney::resolve($currency, (array) ($menu->settings ?? []));
    // The handovers this store offers, and which of them needs an address.
    $fulModes = \App\Modules\User\Support\MenuFulfilment::modesFor((array) ($menu->settings ?? []), false);
    $fulNeedsAddress = collect($fulModes)
        ->mapWithKeys(fn ($m) => [$m => \App\Modules\User\Support\MenuFulfilment::needsAddress($m)])
        ->all();
    $fmt = fn ($n) => \App\Modules\User\Support\MenuMoney::format($n, $money);

    // How this page paints itself: the font the creator picked on the
    // Appearance screen (which this template used to save and then ignore),
    // and which of the five layouts to draw the items in.
    $mp = \App\Modules\User\Support\MenuPresentation::resolve(
        $link->settings['biolink'] ?? [],
        (array) ($menu->settings ?? []),
        (string) $accent
    );

    // Sana, 2026-10-05: "Section jumping". Resolved from the same menu
    // settings everything else here reads, and handed to BOTH the bar and
    // the headings -- so a tab marked 3 cannot land on a heading marked 4.
    $snNav    = \App\Modules\User\Support\MenuSectionNav::nav($menu->settings['section_nav'] ?? null);
    $snMarker = \App\Modules\User\Support\MenuSectionNav::marker($menu->settings['section_marker'] ?? null);
@endphp
<!doctype html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @if($mp['font_href'])<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link rel="stylesheet" href="{{ $mp['font_href'] }}">@endif
    @include('common.partials.biolink-block-assets')
    <style>
@php
    /*
     * Page background (shared renderer).
     *
     * Opt-in: this page had its own colours long before it had a picker, so
     * a link with NO saved background_type renders exactly as it always has.
     *
     * When a background IS chosen the page also COMMITS to a colour scheme,
     * because this page's cards, sheets and borders switch on
     * prefers-color-scheme too. Leaving the visitor's OS in charge of those
     * would land light-mode cards on a creator's dark background for half
     * the audience. $pbInkLight reads the creator's own font colour, which
     * is the one signal they actually set in that same panel.
     */
    // Every product's choices in ONE query rather than one per product.
    $smChoices = [];
    if ($isOrder) {
        $smChoices = collect(\App\Modules\User\Support\MenuOptionSelection::groupsForMany(
            \App\Modules\User\Models\MenuItemOptionGroup::STORE_PRODUCT,
            $menu->products->pluck('id')->map(fn ($i) => (int) $i)->all()
        ))->map(fn ($groups) => $groups->map(fn ($g) => [
            'id'             => (int) $g->id,
            'name'           => $g->name,
            'hint'           => $g->hint,
            'is_required'    => (bool) $g->is_required,
            'min_select'     => (int) $g->min_select,
            'max_select'     => $g->max_select === null ? null : (int) $g->max_select,
            'max_per_option' => $g->perOptionCap(),
            'options'        => $g->options->filter(fn ($o) => $o->is_active)->map(fn ($o) => [
                'id'          => (int) $o->id,
                'name'        => $o->name,
                'icon'        => $o->iconKey(),
                'icon_repeat' => $o->iconRepeat(),
                'price_delta' => (float) $o->price_delta,
                'is_sold_out' => (bool) $o->is_sold_out,
            ])->values(),
        ])->values())->all();
    }
    // Built on the server, in the owner's clock: a phone an hour out of
    // sync would otherwise offer times the kitchen refuses on submit.
    $smSlots = $isOrder
        ? \App\Modules\User\Support\MenuHandoverTiming::slots(
            (array) ($menu->settings ?? []),
            $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault()
        )
        : [];
    $pbBs      = $link->settings['biolink'] ?? [];
    // The hero's own settings. Sana, 2026-10-04: "Priyumm tiffins and order
    // at table both..... can be optional hidden.. also alignment and color
    // and style changes". Defaults are the markup that was hard-coded here,
    // so a menu nobody has touched renders identically.
    $hero = \App\Modules\User\Support\MenuHero::resolve((array) ($menu->settings ?? []));
    // The page description, from where it is actually stored.
    //
    // These templates read `$link->description` -- a property Link has no
    // column and no accessor for, so it was null on every menu ever
    // rendered and the description a creator typed into Page Design has
    // never once appeared on a menu page. Same source and same fallback as
    // the Link in Bio page, which has been reading it correctly all along.
    $menuDesc = $pbBs['biolink_description'] ?? $link->seo_description ?? '';
    $pbOn      = \App\Modules\User\Support\PageBackground::chosen($pbBs);
    $pb        = $pbOn ? \App\Modules\User\Support\PageBackground::resolve($pbBs) : null;
    $pbInkLight = $pbOn && \App\Modules\User\Support\PageBackground::inkIsLight($pbBs);
    $pbInk     = $pbBs['font_color'] ?? ($pbInkLight ? '#f5f5f7' : '#111');
@endphp
        {{-- The font stacks below are echoed raw: {{ }} turns the quotes
             around a family name into &#039;, which is not CSS. They are
             safe to echo because MenuPresentation::cleanFamily only ever
             returns a family that FontCatalog::isKnown() recognises -- no
             creator input reaches this string. --}}
        {{-- The colours a creator chose, as variables. Each one is only
             emitted when it was actually picked, so an unset colour keeps
             inheriting the page ink exactly as this page did before. --}}
        :root {
            color-scheme: light dark; --accent: {{ $accent }};
            {{-- One list, in MenuPresentation::inkVars(). These were written
                 out twice -- here and in the other menu template -- so a new
                 colour reached one page and not the other, which is the most
                 repeated bug report on this project. --}}
            {!! \App\Modules\User\Support\MenuPresentation::inkVars($mp) !!}
        }
        * { box-sizing: border-box; }
        @if($pbOn)
        html, body { margin:0; padding:0; min-height:100%; font-family:{!! $mp['font_css'] !!}; color:{{ $pbInk }}; }
        body { @include('common.page-background.body-declarations') }
        @include('common.page-background.css')
        @else
        html, body { margin:0; padding:0; min-height:100%; font-family:{!! $mp['font_css'] !!}; background:#f6f6f9; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        @endif
        /* The Layout card's numbers, not literals. A fixed width and a
           fixed padding were written here, while the editor's Content Max
           Width and Page Padding boxes saved happily into settings and
           reached this page never. */
        .page {
{!! \App\Modules\User\Support\PageLayout::containerCss($pbBs, \App\Modules\User\Support\PageLayout::MENU_DEFAULTS) !!}
        }
{!! \App\Modules\User\Support\PageLayout::widthQueriesCss($pbBs, '.page', \App\Modules\User\Support\PageLayout::MENU_DEFAULTS) !!}
        /* Sana, 2026-10-04: "block: i want option to make ith full width".
           A block marked full bleed runs from screen edge to screen edge,
           out through the page column's own side padding.

           `calc(50% - 50vw)` is the distance from the column's content edge
           to the viewport's: half the column minus half the screen. A
           percentage margin resolves against the containing block's
           *content* width, which is why the same expression cancels the
           container's padding here and the per-child margin on a biolink
           page, with nothing hard-coded about either.

           `clip` and not `hidden`: 100vw includes the scrollbar, so without
           it the page gains a sliver of sideways scroll -- but `hidden`
           would make the element a scroll container, and `clip` crops
           without becoming one. */
        html, body { overflow-x: clip; }
        .biolink-block-wrap.full-bleed {
            margin-left: calc(50% - 50vw);
            margin-right: calc(50% - 50vw);
            max-width: 100vw;
        }
        .hero { padding:28px 4px 18px; }
        .hero h1 { margin:0; font-size:26px; font-weight:800; letter-spacing:-.02em; font-family:{!! $mp['heading_css'] !!}; }
        .hero p { margin:6px 0 0; opacity:.65; font-size:14px; }
{!! \App\Modules\User\Support\MenuHero::css($hero) !!}
        .badge { display:inline-block; margin-top:12px; padding:6px 12px; border-radius:999px; background:var(--accent); color:#fff; font-size:12.5px; font-weight:600; }
        .cat { margin-top:26px; }
        .cat h2 { font-size:18px; font-weight:700; margin:0 0 4px; font-family:{!! $mp['heading_css'] !!}; color:var(--ink-head); }
        .cat .cdesc { font-size:13px; opacity:.6; margin:0 0 12px; color:var(--ink-cdesc); }
        .item { display:flex; gap:14px; padding:14px 0; border-top:1px solid var(--rule); }
        @media (prefers-color-scheme: dark) { .item { border-color:{{ $mp['divider_color'] ?: 'rgba(255,255,255,.08)' }}; } }
        .item .photo { width:74px; height:74px; border-radius:14px; object-fit:cover; flex:0 0 auto; background:rgba(0,0,0,.05); }
        .item .info { flex:1; min-width:0; }
        .item .name { font-weight:650; font-size:15.5px; color:var(--ink-item); }
        .item .desc { font-size:13px; opacity:.62; margin-top:3px; line-height:1.4; color:var(--ink-desc); }
        /* The minimum/maximum rule under an item. Smaller and quieter than
           the description: it is a constraint, not a selling point. */
        .item .qty-rule { font-size:11.5px; opacity:.6; margin-top:3px; letter-spacing:.01em; color:var(--ink-desc); }
        /* The cap message, on the Add row. Its own line under the
           stepper rather than squeezed in beside it. */
        .qty-cap { display:block; font-size:11.5px; margin-top:4px; color:var(--ink-desc); opacity:.85; }
        .item .price { font-weight:700; font-size:14.5px; margin-top:6px; color:var(--ink-price); }
        .soldout { opacity:.45; }
        .soldout .name::after { content:" · Out of stock"; color:#b91c1c; font-size:12px; font-weight:600; }
        .addrow { margin-top:8px; }
        /* Sana, 2026-10-05: "- 1 + are shown in light color". The glyph
           inherited the page colour and the border was rgba(0,0,0,.18) --
           on a cream card with pale ink, a control you hunt for. Both now
           come from one setting, which defaults to the ITEM NAME colour:
           the one thing on this page that is legible by definition. */
        .qbtn { width:30px; height:30px; border-radius:8px; border:1.5px solid var(--step-edge); background:transparent; color:var(--ink-step); font-size:17px; font-weight:600; cursor:pointer; line-height:1; }
        .qbtn:hover { border-color:var(--ink-step); }
        .qty { min-width:22px; text-align:center; display:inline-block; font-weight:700; color:var(--ink-step); }
        .add { border:none; background:var(--accent); color:#fff; border-radius:9px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer; }
        /* Cart bar */
        /* Modal */
        .line { display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:14px; }
        .field { width:100%; padding:11px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; font-size:14px; margin-top:8px; font-family:inherit; }
        @media (prefers-color-scheme: dark) { .field { border-color:rgba(255,255,255,.2); } }
        .total { display:flex; justify-content:space-between; font-weight:800; font-size:16px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.1); }
        .primary { width:100%; border:none; background:var(--accent); color:#fff; border-radius:12px; padding:14px; font-size:15px; font-weight:700; cursor:pointer; margin-top:14px; }
        .ghost { width:100%; border:1px solid rgba(0,0,0,.15); background:transparent; color:inherit; border-radius:12px; padding:11px; font-size:14px; cursor:pointer; margin-top:8px; }
        .wa-btn { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; box-sizing:border-box; background:#25D366; color:#fff; border-radius:12px; padding:12px; font-size:14px; font-weight:700; text-decoration:none; margin-top:12px; }
        .note { font-size:12.5px; opacity:.6; text-align:center; margin-top:10px; }
        /* A failed order says so here rather than in an alert() box, which
           covers the bill and cannot say what to do next. */
        .order-err { margin:10px 0 0; padding:10px 12px; border-radius:10px; font-size:13px; line-height:1.4;
                     background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.35); color:#ef4444; }
        .status-pill { display:inline-block; padding:4px 11px; border-radius:999px; font-size:12.5px; font-weight:700; background:var(--accent); color:#fff; }
        .empty { text-align:center; opacity:.5; padding:40px 0; }
        .bill-row { display:flex; justify-content:space-between; font-size:13.5px; margin-top:8px; opacity:.85; }
        .ful-row { display:flex; gap:8px; margin-top:12px; }
        .ful-opt { flex:1; display:flex; align-items:center; justify-content:center; gap:7px; padding:10px 8px; border:1px solid rgba(0,0,0,.18); border-radius:11px; font-size:13.5px; font-weight:600; cursor:pointer; }
        .ful-opt:has(input:checked) { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 12%, transparent); }
        @media (prefers-color-scheme: dark) { .ful-opt { border-color:rgba(255,255,255,.2); } }
@include('common.partials.menu-layout-css')
            @if($pbOn)
        {{-- The page has committed to a scheme (see $pbInkLight): restate the
             surface rules unconditionally so the visitor's OS stops deciding
             what the cards look like. Emitted last, so it wins by order. --}}
        @include('common.page-background.'.($pbInkLight ? 'dark' : 'light').'-surfaces')
        @endif
</style>
@include('common.partials.menu-order-shell-css')
</head>
<body>
@if($staffMode)
    <div style="padding:12px;background:#fff7ed;color:#713f12;text-align:center;position:relative;z-index:10">
        <strong>Staff order for a customer</strong>
        <p style="margin:4px 0">Enter the customer's name and phone at checkout. Bulk prices and meal coupons apply automatically at the item's threshold. Payment is collected separately.</p>
        <a href="{{ route('user.links.store.orders', $link) }}" style="color:inherit">Back to orders</a>
    </div>
@endif
@if($pbOn)@include('common.page-background.layers')@endif
{{-- The side-by-side layouts get a wider column; the reading layouts
     keep the narrow one they were designed for. --}}
{{-- Two classes decide how the card is SET, as opposed to how its items
     are laid out: where the section titles sit, and where the prices do.
     Both live on the wrapper so one choice paints sections and
     sub-sections together. See common/partials/menu-layout-css. --}}
<div class="page{{ in_array($mp['layout'], ['cards', 'grid'], true) ? ' wide' : '' }} head-{{ $mp['heading_style'] }} price-{{ $mp['price_style'] }}">

    {{-- Before the hero. Sana, 2026-09-28, of a block set to "Top of page":
         "its not going top of table.. still i see more heading and sub
         heading" -- the one slot above the menu rendered AFTER the title
         and description, so a block claiming to be at the top of the page
         had the restaurant's name above it. --}}
    @include('common.partials.biolink-block-list', [
        'link'           => $link,
        'blkFontColor'   => $pbInk,
        'blkGlobalTheme' => $pbBs['block_theme'] ?? [],
        'blkBtnInline'   => '',
        'blkSlot'        => \App\Modules\User\Support\MenuBlockSlot::TOP,
        'blkSectionIds'  => $blkSectionIds,
        'blkEmpty'       => false,
    ])
    {{-- See the restaurant page: skipped when nothing is left in it. --}}
    @unless(\App\Modules\User\Support\MenuHero::isEmpty($hero, $menuDesc !== '', $isOrder))
    <div class="hero">
        @unless($hero['hero_title_hidden'])<h1>{{ $title }}</h1>@endunless
        @if($menuDesc !== '')<p>{{ $menuDesc }}</p>@endif
        @if($isOrder && ! $hero['hero_badge_hidden'])
            <span class="badge">Order requests open</span>
        @endif
    </div>
    @endunless


    {{-- Blocks a creator added to this page. They save through the shared
         block editor and, until now, nothing on the public menu rendered
         them. Each block picks its side; the default is below, because the
         menu is what the page is for. --}}
    @include('common.partials.biolink-block-list', [
        'link'           => $link,
        'blkFontColor'   => $pbInk,
        'blkGlobalTheme' => $pbBs['block_theme'] ?? [],
        'blkBtnInline'   => '',
        'blkSlot'        => 'above',
        'blkSectionIds'  => $blkSectionIds,
        'blkEmpty'       => false,
    ])

    @php
        // Built from the SAME tree the page renders, so the bar can never
        // offer a section the page does not show -- a jump link to a hidden
        // section is a link to nowhere, and the customer who taps it decides
        // the menu is broken rather than that the section was hidden.
        $snTargets = \App\Modules\User\Support\MenuSectionNav::targets($tree);
    @endphp
    @if(\App\Modules\User\Support\MenuSectionNav::worthDrawing($snNav, count($snTargets)))
        @include('common.partials.menu-section-nav', [
            'snTargets' => $snTargets,
            'snNav'     => $snNav,
            'snMarker'  => $snMarker,
        ])
    @endif

    @if($menu->settings['item_search_enabled'] ?? false)
        @include('common.partials.menu-item-search', ['searchTree' => $tree])
    @endif

    @include('common.partials.menu-section-list', [
        'msTree'    => $tree,
        'msMarker'  => $snMarker,
        'msLayout'  => $mp['layout'],
        'msFmt'     => $fmt,
        'msOrder'   => $isOrder,
        'msNs'      => 'SM',
        'msSoldKey' => 'is_out_of_stock',
        'msDivider' => $mp['divider'],
        'msEmpty'   => 'This store is being set up. Check back soon.',
        'msBlockSlot' => fn ($catId) => view('common.partials.biolink-block-list', [
            'link'           => $link,
            'blkFontColor'   => $pbInk,
            'blkGlobalTheme' => $pbBs['block_theme'] ?? [],
            'blkBtnInline'   => '',
            'blkSlot'        => \App\Modules\User\Support\MenuBlockSlot::forSection((int) $catId),
            'blkSectionIds'  => $blkSectionIds,
            'blkEmpty'       => false,
        ])->render(),
    ])

    @include('common.partials.biolink-block-list', [
        'link'           => $link,
        'blkFontColor'   => $pbInk,
        'blkGlobalTheme' => $pbBs['block_theme'] ?? [],
        'blkBtnInline'   => '',
        'blkSlot'        => 'below',
        'blkSectionIds'  => $blkSectionIds,
        'blkEmpty'       => false,
    ])
</div>

@if($isOrder)
{{-- The cart, as one floating control. It was a full-width bar
     pinned across the bottom of the page -- a wall across the menu
     you are still reading, saying the same two things it now
     carries itself. --}}
<button class="cartfab sz-pinned" id="cartfab" type="button" onclick="SM.openCart()"
        aria-label="Review order">
    <span class="n" id="cartCount">0</span>
    <span class="sum"><span id="cartLabel">Review order</span> · <strong id="cartTotal">{{ $fmt(0) }}</strong></span>
</button>

<div class="modal sz-pinned" id="cartModal">
    <div class="sheet">
        <h3>Your request</h3>
        {{-- Said once, at the top of the sheet, when a restored cart no
             longer matches the menu. Silently handing someone a different
             order from the one they left is worse than losing it. --}}
        <p class="cart-restored" id="cartRestored" role="status" style="display:none"></p>
        <div id="cartLines"></div>
        <div id="billBreakdown"></div>
        <div class="total"><span>Estimated total</span><span id="modalTotal">{{ $fmt(0) }}</span></div>
        @if(count($fulModes) > 1)
            <div class="ful-row">
                @foreach($fulModes as $fm)
                    <label class="ful-opt">
                        <input type="radio" name="ful" value="{{ $fm }}" {{ $loop->first ? 'checked' : '' }}
                               onchange="SM.setFulfilment(this.value)">
                        <span>{{ \App\Modules\User\Support\MenuFulfilment::label($fm, false) }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        <textarea class="field" id="fAddress" rows="2" placeholder="Delivery address" style="display:none"></textarea>
        @include('common.partials.menu-when', ['whSlots' => $smSlots])
        <input class="field" id="fName" placeholder="{{ $staffMode ? 'Customer name' : 'Your name' }}" required>
        <input class="field" id="fPhone" type="tel" inputmode="tel" autocomplete="tel" placeholder="{{ $staffMode ? 'Customer phone number' : 'Phone number' }}" required>
        <input class="field" id="fContact" placeholder="Email (optional)">
        <textarea class="field" id="fNote" rows="2" placeholder="Notes for your order (optional)"></textarea>
        {{-- Where a failed request says so, instead of an alert() box. --}}
        <p class="order-err" id="orderErr" role="alert" style="display:none"></p>
        <button class="primary" id="placeBtn" type="button" onclick="SM.place()">Send order request</button>
        <button class="ghost" type="button" onclick="SM.closeCart()">Keep browsing</button>
        <p class="note">This is an order request, not a checkout. No online payment is collected, the store will contact you to arrange fulfilment and payment.</p>
    </div>
</div>

<div class="modal sz-pinned" id="doneModal">
    <div class="sheet">
        <h3 id="doneHead">Request sent 🎉</h3>
        <div id="ordToken" style="display:none"></div>
        {{-- Second to the number, not instead of it: the number is
             what a guest quotes, the square is what the counter
             scans. --}}
        <div id="ordQr" style="display:none"></div>
        <p id="doneStatusRow">Status: <span class="status-pill" id="ordStatus">New</span></p>
        <p class="done-msg" id="doneMsg" style="display:none"></p>
        {{-- Above the total: whoever ordered in bulk came here for the
             passes, not for the estimate. --}}
        <div id="mealCoupons" style="display:none"></div>
        <div class="total" id="doneTotalRow"><span>Estimated total</span><span id="doneTotal"></span></div>
        <p class="note" id="doneNote">This is an estimated total, not a final bill. The store has been notified and will reach out. This updates automatically.</p>
        <a id="waBtn" class="wa-btn" href="#" target="_blank" rel="noopener" style="display:none">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.739-.981 1.249.74zm5.392-15.327c-.235-.025-.47-.025-.706-.025-.235 0-.616.088-.939.441-.323.353-1.235 1.206-1.235 2.941 0 1.735 1.264 3.41 1.44 3.646.176.235 2.479 3.785 6.005 5.31.84.363 1.495.58 2.006.742.843.268 1.61.23 2.216.14.676-.101 2.082-.851 2.376-1.673.294-.823.294-1.528.206-1.674-.088-.147-.323-.235-.676-.412-.353-.176-2.082-1.028-2.405-1.146-.323-.117-.558-.176-.793.177-.235.353-.91 1.146-1.116 1.381-.206.235-.411.265-.764.088-.353-.177-1.49-.549-2.838-1.751-1.049-.935-1.757-2.09-1.963-2.443-.206-.353-.022-.544.155-.72.158-.157.353-.412.529-.618.176-.206.235-.353.353-.588.117-.235.059-.441-.029-.617-.088-.177-.793-1.912-1.087-2.617z"/></svg>
            <span>Send request via WhatsApp</span>
        </a>
        {{-- Only when the owner configured their own page AND this request
             has coupons: the redirect was held back so the guest could keep
             the codes, so the way onward is theirs. --}}
        <a id="doneOnward" class="wa-btn" href="#" style="display:none">Continue</a>
        <button class="ghost" type="button" onclick="SM.reset()">Back to store</button>
    </div>
</div>

@include('common.partials.menu-guest-post')
@include('common.partials.menu-meal-coupons')
@include('common.partials.menu-quantity-rules')
@include('common.partials.menu-cart-store')
@include('common.partials.menu-token')
@include('common.partials.menu-order-qr')
@include('common.partials.menu-contact')
@include('common.partials.menu-chooser')
@include('common.partials.menu-confirmation')
<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ORDER_URL = @json($staffMode ? route('user.links.store.staff-order.place', $link) : route('sm.public.order', ['alias' => $link->alias]));
    const STATUS_BASE = @json(url('/sm/order'));
    // The page's money format, handed to the cart rather than re-derived.
    // The cart total and the item prices used to be two independent copies
    // of "code, space, two decimals", which is how they drift.
    const MONEY = @json($money);
    const QUOTE_URL = @json($staffMode ? route('user.links.store.staff-order.quote', $link) : route('sm.public.quote', ['alias' => $link->alias]));
    const FUL_MODES = @json($fulModes);
    const FUL_ADDRESS = @json((object) $fulNeedsAddress);
    const FUL_TIMED = ['takeaway', 'delivery'];
    const CHOICES = @json((object) $smChoices);

    // What the owner chose to happen once the order goes through, resolved
    // server-side so the page never sees a half-configured mode.
    const CONFIRM = @json(\App\Modules\User\Support\MenuConfirmation::resolve($staffMode ? [] : (array) ($menu->settings ?? [])));
    // Known at render time, so a menu with no number never opens a tab it
    // would have to close again.
    const WA_ON = @json((bool) \App\Modules\Common\Services\WhatsappOrderLink::numberFor($menu));
    let fulfilment = FUL_MODES[0] || null;
    let lastBill = null;
    let quoteSeq = 0;
    // ITEMS is the CATALOG: what the store offers. The cart is LINES,
    // because one product can be in it twice with different choices -- two
    // large mugs and one standard -- and a single qty per product cannot
    // hold that.
    const ITEMS = {};
    document.querySelectorAll('[data-add]').forEach(el => {
        const id = el.getAttribute('data-add');
        ITEMS[id] = { id: +id, name: el.getAttribute('data-name'), price: parseFloat(el.getAttribute('data-price')),
            // The quantity rule, as the Add row carries it. Absent means
            // no rule, which is every product that has never had one.
            bulkPrice: el.getAttribute('data-bulk-price'), couponFrom: el.getAttribute('data-coupon-from'), min: el.getAttribute('data-min'), max: el.getAttribute('data-max') || null };
    });
    menuChooser.install(CHOICES, n => fmt(n));
    let LINES = [];

    // ---- The cart survives a reload ---------------------------------
    //
    // Rebuilt against the menu as it is RIGHT NOW, never from stored
    // prices: see common/partials/menu-cart-store for why that matters.
    // Anything that cannot be rebuilt honestly is dropped and said out
    // loud rather than quietly swapped for something else.
    const CART_KEY = @json(($staffMode ? 'staff:' : '').$link->alias);
    let restoredNotice = '';

    function repriceLines() {
        LINES.forEach(l => {
            const it = ITEMS[l.id];
            const bulk = it.bulkPrice !== '' && +it.couponFrom > 0 && l.qty >= +it.couponFrom;
            const base = bulk ? +it.bulkPrice : it.price;
            l.perUnit = Math.round((base + menuChooser.extraFor(l.opts)) * 100) / 100;
        });
    }
    function saveCart() { repriceLines(); menuCart.save(CART_KEY, LINES); }

    (function restoreCart() {
        const back = menuCart.restore(CART_KEY, ITEMS, menuChooser);
        if (!back) { return; }
        LINES = back.lines;
        restoredNotice = menuCart.notice(back, 'item', 'items');
        saveCart();
    })();
    const qtyOf = id => LINES.filter(l => l.id === +id).reduce((n, l) => n + l.qty, 0);
    const plainLine = id => LINES.find(l => l.id === +id && !l.opts.length);
    const fmt = n => MONEY.prefix
        + (Math.round(n * 100) / 100).toLocaleString('en-US', {
            minimumFractionDigits: MONEY.decimals, maximumFractionDigits: MONEY.decimals })
        + MONEY.suffix;
    let pollTimer = null;

    function render() {
        let count = 0, total = 0;
        LINES.forEach(l => { count += l.qty; total += l.qty * l.perUnit; });

        Object.values(ITEMS).forEach(it => {
            const n = qtyOf(it.id);
            const step = document.querySelector('[data-stepper="' + it.id + '"]');
            const addBtn = document.querySelector('[data-add="' + it.id + '"] .add');
            const qEl = document.querySelector('[data-qty="' + it.id + '"]');
            if (qEl) qEl.textContent = n;

            // A product with choices cannot use the +/- stepper: plus WHICH
            // one? So it keeps its Add button, which opens the chooser
            // again, and says how many are already in the order.
            if (menuChooser.asks(it.id)) {
                if (step) step.style.display = 'none';
                if (addBtn) {
                    addBtn.style.display = 'inline-block';
                    addBtn.textContent = n > 0 ? ('Add · ' + n + ' in order') : 'Add';
                }
                return;
            }

            if (n > 0) { if (step) step.style.display='inline'; if (addBtn) addBtn.style.display='none'; }
            else { if (step) step.style.display='none'; if (addBtn) addBtn.style.display='inline-block'; }
        });
        document.getElementById('cartCount').textContent = count;
        document.getElementById('cartTotal').textContent = fmt(total);
        // The line sum until the server answers; the quote replaces it with
        // the figure that includes whatever charges the handover adds.
        if (!lastBill) document.getElementById('modalTotal').textContent = fmt(total);
        document.getElementById('cartfab').classList.toggle('show', count > 0);
        return { count, total };
    }
    function lines(container) {
        const box = document.getElementById(container);
        // A missing container used to throw here, and the throw landed
        // inside showDone() -- which is called AFTER the order is already
        // placed, so the order went through and the guest was left looking
        // at a button that said "Placing..." forever. Never again: a panel
        // that has lost a box renders without it.
        if (!box) { return; }
        box.innerHTML = '';
        LINES.forEach(l => {
            const row = document.createElement('div');
            row.className = 'line';

            const left = document.createElement('span');
            const title = document.createElement('span');
            title.textContent = l.qty + '× ' + l.name;
            left.appendChild(title);
            if (l.opts.length) {
                const sub = document.createElement('small');
                sub.className = 'line-opts';
                sub.textContent = menuChooser.label(l.opts);
                left.appendChild(document.createElement('br'));
                left.appendChild(sub);
            }

            const right = document.createElement('span');
            right.textContent = fmt(l.qty * l.perUnit);

            row.appendChild(left);
            row.appendChild(right);
            box.appendChild(row);
        });
    }
    // This page had TWO cartItems() definitions, the second silently
    // winning. One now.
    function cartItems() {
        return LINES.map(l => ({
            product_id: l.id,
            quantity: l.qty,
            options: menuChooser.payload(l.opts),
        }));
    }
    async function refreshQuote() {
        const items = cartItems();
        const box = document.getElementById('billBreakdown');
        // The cart is LINES, and has been since the chooser landed. Summing
        // ITEMS was summing the CATALOG, whose entries have carried no qty
        // since then, so this read `undefined * price` for every dish and
        // the guest's estimated total said "INR NaN" -- on every quote that
        // failed, which at a table on patchy wifi is routine. Found by
        // rendering the sheet with the endpoint unreachable.
        const fallback = LINES.reduce((s, l) => s + l.qty * l.perUnit, 0);
        if (!items.length) { lastBill = null; if (box) box.innerHTML = ''; document.getElementById('modalTotal').textContent = fmt(0); return; }
        const seq = ++quoteSeq;
        const res = await menuPost(QUOTE_URL, { items, fulfilment });
        if (seq !== quoteSeq) return;
        if (!res.ok || !res.data || !res.data.data) {
            // An estimate shown while browsing: fall back to the line-item
            // sum rather than interrupting someone mid-shop.
            lastBill = null;
            document.getElementById('modalTotal').textContent = fmt(fallback);
            return;
        }
        {
            lastBill = res.data.data.bill;
            if (box) {
                box.innerHTML = '';
                const add = (label, value) => {
                    const row = document.createElement('div');
                    row.className = 'bill-row';
                    row.innerHTML = '<span>' + label + '</span><span>' + value + '</span>';
                    box.appendChild(row);
                };
                if ((lastBill.charges || []).length) {
                    add('Subtotal', fmt(lastBill.subtotal));
                    lastBill.charges.forEach(c => { if (c && c.amount > 0) add(c.label, fmt(c.amount)); });
                }
            }
            document.getElementById('modalTotal').textContent = fmt(lastBill.total);
        }
    }

    // Show or clear the inline failure line above the order button.
    function orderError(message) {
        const el = document.getElementById('orderErr');
        if (!el) { return; }
        if (!message) { el.style.display = 'none'; el.textContent = ''; return; }
        el.textContent = message;
        el.style.display = '';
        el.scrollIntoView({ block: 'nearest' });
    }
    window.SM = {
        add(id){
            const it = ITEMS[id];
            if (!menuChooser.asks(id)) { this.put(it, []); return; }
            menuChooser.open({ id: it.id, name: it.name, base: it.price }, (item, opts) => {
                this.put(ITEMS[item.id], opts);
            });
        },
        // One line per distinct set of choices; adding the same set again
        // is one more of that line rather than a second identical one.
        put(it, opts){
            const key = menuChooser.key(it.id, opts);
            const found = LINES.find(l => l.key === key);
            if (found) {
                // At the ceiling, say so instead of counting past it.
                const up = menuLimits.up(it, found.qty);
                if (up === null) { menuLimits.say(it.id, menuLimits.atCeiling(it, found.qty)); return; }
                found.qty = up;
            }
            else {
                LINES.push({
                    key, id: it.id, name: it.name, opts,
                    perUnit: Math.round((it.price + menuChooser.extraFor(opts)) * 100) / 100,
                    // A case of twelve starts at twelve, not at one.
                    qty: menuLimits.first(it),
                });
            }
            saveCart();
            render();
            if (document.getElementById('cartModal').classList.contains('show')) { lines('cartLines'); refreshQuote(); }
        },
        // The +/- on a product with no choices: there is only ever one line.
        inc(id){
            const l = plainLine(id);
            if (!l) { this.add(id); return; }
            const up = menuLimits.up(ITEMS[id], l.qty);
            if (up === null) { menuLimits.say(id, menuLimits.atCeiling(ITEMS[id], l.qty)); return; }
            l.qty = up;
            saveCart();
            render();
        },
        dec(id){
            const l = plainLine(id);
            if (!l) { return; }
            // At the floor this returns 0: eleven of a case-of-twelve
            // product is not an order, so minus clears the line instead.
            l.qty = menuLimits.down(ITEMS[id], l.qty);
            if (l.qty <= 0) { LINES = LINES.filter(x => x !== l); }
            saveCart();
            render();
        },
        openCart(){
            const note = document.getElementById('cartRestored');
            if (note) {
                note.textContent = restoredNotice;
                note.style.display = restoredNotice ? '' : 'none';
            }
            lines('cartLines'); render(); refreshQuote(); document.getElementById('cartModal').classList.add('show'); },
        setFulfilment(mode){
            fulfilment = mode;
            this.paintFulfilment();
            refreshQuote();
        },
        /**
         * Show the fields the chosen handover needs.
         *
         * Called at startup as well as on change, and that is the fix for a
         * live bug: the radios only render when a menu offers MORE THAN ONE
         * mode, so on a delivery-only menu setFulfilment never fired and the
         * address box stayed hidden -- while the server went on requiring an
         * address. The customer had nothing to type into and no way through.
         */
        paintFulfilment(){
            const box = document.getElementById('fAddress');
            if (box) { box.style.display = FUL_ADDRESS[fulfilment] ? '' : 'none'; }
            const when = document.getElementById('whenRow');
            if (when) { when.style.display = FUL_TIMED.includes(fulfilment) ? '' : 'none'; }
        },
        closeCart(){ document.getElementById('cartModal').classList.remove('show'); },
        reset(){ if(pollTimer) clearInterval(pollTimer); location.href = location.pathname; },
        async place(){
            const items = cartItems();
            if (!items.length) return;
            // Told which box is empty, with the keyboard in it, before
            // anything is sent. The server refuses too; this is the
            // courtesy, not the enforcement.
            const missing = menuContact.check();
            if (missing) { orderError(missing); return; }
            const btn = document.getElementById('placeBtn');
            btn.disabled = true; btn.textContent = 'Sending…';
            // Reserved here, while the tap is still on the stack -- after the
            // await a popup blocker swallows it silently.
            const waWin = menuWhatsappReserve(WA_ON);
            const res = await menuPost(ORDER_URL, {
                customer_name: document.getElementById('fName').value.trim(),
                customer_phone: document.getElementById('fPhone').value.trim(),
                wanted_at: (document.getElementById('fWhen') || {}).value || null,
                customer_contact: document.getElementById('fContact').value || null,
                fulfilment,
                customer_address: FUL_ADDRESS[fulfilment]
                    ? (document.getElementById('fAddress').value || null) : null,
                customer_note: document.getElementById('fNote').value || null,
                items
            });
            if (!res.ok || !res.data || !res.data.data) {
                menuWhatsappHandoff(waWin, null);
                orderError(res.message);
                btn.disabled = false; btn.textContent = 'Send order request';
                return;
            }
            const order = res.data.data.order;
            // The order exists; the saved cart is spent. Storage only --
            // LINES still has to paint the confirmation below.
            menuCart.clear(CART_KEY);
            restoredNotice = '';
            menuWhatsappHandoff(waWin, order.whatsapp);
            // The order EXISTS by now. If painting the confirmation fails
            // for any reason, the one thing the guest must not be left with
            // is a button that still says it is working.
            try {
                this.showDone(order);
            } catch (e) {
                btn.disabled = false; btn.textContent = 'Send order request';
                orderError('Your request went through, but this page could not show the confirmation. Please check with us before ordering again.');
            }
        },
        showDone(order){
            this.closeCart();
            // The owner may be sending the guest somewhere else entirely, in
            // which case none of the rest of this is for anyone.
            const mealCoupons = (order.meal_coupons || []);
            if (menuConfirmation(CONFIRM, {
                headline: document.getElementById('doneHead'),
                message:  document.getElementById('doneMsg'),
                onward:   document.getElementById('doneOnward'),
                bill: [
                    document.getElementById('doneStatusRow'),
                    document.getElementById('doneTotalRow'),
                    document.getElementById('doneNote')
                ]
            // The codes are the only copy the guest gets, so a configured
            // redirect waits for them to take it.
            }, { keep: mealCoupons.length > 0 }) === 'redirected') { return; }
            menuMealCoupons.show(document.getElementById('mealCoupons'), mealCoupons);
            menuToken.show(document.getElementById('ordToken'), order, 'Quote it when you collect or when you write in.');
            menuOrderQr.show(document.getElementById('ordQr'), order);
            document.getElementById('doneTotal').textContent = fmt(order.total != null ? order.total : order.subtotal);
            document.getElementById('ordStatus').textContent = order.status_label || order.status;
            const waBtn = document.getElementById('waBtn');
            if (order.whatsapp && order.whatsapp.url) { waBtn.href = order.whatsapp.url; waBtn.style.display = 'flex'; }
            else { waBtn.style.display = 'none'; }
            document.getElementById('doneModal').classList.add('show');
            // Nothing to keep up to date when the status pill is not on screen.
            if (CONFIRM.mode === 'message') { return; }
            const url = STATUS_BASE + '/' + order.public_token + '/status';
            pollTimer = setInterval(async () => {
                try {
                    const r = await fetch(url);
                    if (!r.ok) return;
                    const j = await r.json();
                    document.getElementById('ordStatus').textContent = j.data.order.status_label;
                    if (['completed','cancelled'].includes(j.data.order.status)) clearInterval(pollTimer);
                } catch(e){}
            }, 5000);
        }
    };

    SM.paintFulfilment();
    // Paints a restored cart onto the pill and the steppers. Not inside
    // restoreCart(): render() reaches fmt, which is a `const` declared
    // further down and would still be in its dead zone there.
    render();
})();
</script>
@endif
@include('common.partials.link-type-pairings', ['pairingType' => 'store_menu', 'theme' => 'light'])

{{-- The same share button every other page type carries. It reads the
     link's own settings, so whether it appears at all, and what it looks
     like, is decided on that link's settings screen. --}}
@include('common.partials.share-button', [
    'sbLink'  => $link,
    'sbUrl'   => $link->getShortUrl(),
    'sbTitle' => $link->title ?: config('app.name'),
])

</body>
</html>
