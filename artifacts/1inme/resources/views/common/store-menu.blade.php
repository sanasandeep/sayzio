@php
    /** @var \App\Modules\User\Models\Link $link */
    $menu = $link->storeMenu()->with(['categories', 'products'])->first();
    $accent = $menu->accent_color ?: '#3d6bff';
    $currency = $menu->currency ?: 'USD';
    $isOrder = $menu->isOrderMode() && $menu->acceptingOrders();
    $title = $link->title ?: $link->alias;

    // Sections, their sub-sections and their products -- and which of
    // those a visitor may see. One definition, shared with the restaurant
    // menu and with the editor, so "hidden" means the same thing in all
    // three.
    $tree = \App\Modules\User\Support\MenuTree::build($menu->categories, $menu->products);

    // How this menu writes a price -- the code, a symbol, or nothing; before
    // or after; with the decimals the currency actually has. One definition,
    // shared with the cart JavaScript below and with the WhatsApp message,
    // so the three can never disagree about what a number looks like.
    $money = \App\Modules\User\Support\MenuMoney::resolve($currency, (array) ($menu->settings ?? []));
    $fmt = fn ($n) => \App\Modules\User\Support\MenuMoney::format($n, $money);

    // How this page paints itself: the font the creator picked on the
    // Appearance screen (which this template used to save and then ignore),
    // and which of the five layouts to draw the items in.
    $mp = \App\Modules\User\Support\MenuPresentation::resolve(
        $link->settings['biolink'] ?? [],
        (array) ($menu->settings ?? []),
        (string) $accent
    );
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
    $pbBs      = $link->settings['biolink'] ?? [];
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
            --ink-head:  {{ $mp['heading_color'] ?: 'inherit' }};
            --ink-item:  {{ $mp['item_color']    ?: 'inherit' }};
            --ink-desc:  {{ $mp['desc_color']    ?: 'inherit' }};
            --ink-price: {{ $mp['price_color']   ?: 'var(--accent)' }};
            --rule:      {{ $mp['divider_color'] ?: 'rgba(0,0,0,.07)' }};
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
        .page { max-width:760px; margin:0 auto; padding:0 16px 120px; }
        .hero { padding:28px 4px 18px; }
        .hero h1 { margin:0; font-size:26px; font-weight:800; letter-spacing:-.02em; font-family:{!! $mp['heading_css'] !!}; }
        .hero p { margin:6px 0 0; opacity:.65; font-size:14px; }
        .badge { display:inline-block; margin-top:12px; padding:6px 12px; border-radius:999px; background:var(--accent); color:#fff; font-size:12.5px; font-weight:600; }
        .cat { margin-top:26px; }
        .cat h2 { font-size:18px; font-weight:700; margin:0 0 4px; font-family:{!! $mp['heading_css'] !!}; color:var(--ink-head); }
        .cat .cdesc { font-size:13px; opacity:.6; margin:0 0 12px; }
        .item { display:flex; gap:14px; padding:14px 0; border-top:1px solid var(--rule); }
        @media (prefers-color-scheme: dark) { .item { border-color:{{ $mp['divider_color'] ?: 'rgba(255,255,255,.08)' }}; } }
        .item .photo { width:74px; height:74px; border-radius:14px; object-fit:cover; flex:0 0 auto; background:rgba(0,0,0,.05); }
        .item .info { flex:1; min-width:0; }
        .item .name { font-weight:650; font-size:15.5px; color:var(--ink-item); }
        .item .desc { font-size:13px; opacity:.62; margin-top:3px; line-height:1.4; color:var(--ink-desc); }
        .item .price { font-weight:700; font-size:14.5px; margin-top:6px; color:var(--ink-price); }
        .soldout { opacity:.45; }
        .soldout .name::after { content:" · Out of stock"; color:#b91c1c; font-size:12px; font-weight:600; }
        .addrow { margin-top:8px; }
        .qbtn { width:30px; height:30px; border-radius:8px; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; font-size:17px; cursor:pointer; line-height:1; }
        @media (prefers-color-scheme: dark) { .qbtn { border-color:rgba(255,255,255,.2); } }
        .qty { min-width:22px; text-align:center; display:inline-block; font-weight:600; }
        .add { border:none; background:var(--accent); color:#fff; border-radius:9px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer; }
        /* Cart bar */
        .cartbar { position:fixed; left:0; right:0; bottom:0; padding:12px 16px calc(12px + env(safe-area-inset-bottom)); background:#fff; border-top:1px solid rgba(0,0,0,.1); display:none; }
        @media (prefers-color-scheme: dark) { .cartbar { background:#15151c; border-color:rgba(255,255,255,.1); } }
        .cartbar.show { display:block; }
        .cartbar .inner { max-width:760px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .cartbar button { border:none; background:var(--accent); color:#fff; border-radius:11px; padding:13px 20px; font-size:15px; font-weight:700; cursor:pointer; }
        /* Modal */
        .modal { position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:flex-end; justify-content:center; z-index:50; }
        .modal.show { display:flex; }
        .sheet { background:#fff; color:#111; width:100%; max-width:760px; border-radius:18px 18px 0 0; padding:20px 18px calc(20px + env(safe-area-inset-bottom)); max-height:88vh; overflow:auto; }
        @media (prefers-color-scheme: dark) { .sheet { background:#15151c; color:#f5f5f7; } }
        .sheet h3 { margin:0 0 12px; font-size:18px; }
        .line { display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:14px; }
        .field { width:100%; padding:11px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; font-size:14px; margin-top:8px; font-family:inherit; }
        @media (prefers-color-scheme: dark) { .field { border-color:rgba(255,255,255,.2); } }
        .total { display:flex; justify-content:space-between; font-weight:800; font-size:16px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.1); }
        .primary { width:100%; border:none; background:var(--accent); color:#fff; border-radius:12px; padding:14px; font-size:15px; font-weight:700; cursor:pointer; margin-top:14px; }
        .ghost { width:100%; border:1px solid rgba(0,0,0,.15); background:transparent; color:inherit; border-radius:12px; padding:11px; font-size:14px; cursor:pointer; margin-top:8px; }
        .wa-btn { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; box-sizing:border-box; background:#25D366; color:#fff; border-radius:12px; padding:12px; font-size:14px; font-weight:700; text-decoration:none; margin-top:12px; }
        .note { font-size:12.5px; opacity:.6; text-align:center; margin-top:10px; }
        .status-pill { display:inline-block; padding:4px 11px; border-radius:999px; font-size:12.5px; font-weight:700; background:var(--accent); color:#fff; }
        .empty { text-align:center; opacity:.5; padding:40px 0; }
@include('common.partials.menu-layout-css')
            @if($pbOn)
        {{-- The page has committed to a scheme (see $pbInkLight): restate the
             surface rules unconditionally so the visitor's OS stops deciding
             what the cards look like. Emitted last, so it wins by order. --}}
        @include('common.page-background.'.($pbInkLight ? 'dark' : 'light').'-surfaces')
        @endif
</style>
</head>
<body>
@if($pbOn)@include('common.page-background.layers')@endif
{{-- The side-by-side layouts get a wider column; the reading layouts
     keep the narrow one they were designed for. --}}
{{-- Two classes decide how the card is SET, as opposed to how its items
     are laid out: where the section titles sit, and where the prices do.
     Both live on the wrapper so one choice paints sections and
     sub-sections together. See common/partials/menu-layout-css. --}}
<div class="page{{ in_array($mp['layout'], ['cards', 'grid'], true) ? ' wide' : '' }} head-{{ $mp['heading_style'] }} price-{{ $mp['price_style'] }}">
    <div class="hero">
        <h1>{{ $title }}</h1>
        @if($desc = $link->description)<p>{{ $desc }}</p>@endif
        @if($isOrder)
            <span class="badge">Order requests open</span>
        @endif
    </div>


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
        'blkEmpty'       => false,
    ])

    @include('common.partials.menu-section-list', [
        'msTree'    => $tree,
        'msLayout'  => $mp['layout'],
        'msFmt'     => $fmt,
        'msOrder'   => $isOrder,
        'msNs'      => 'SM',
        'msSoldKey' => 'is_out_of_stock',
        'msDivider' => $mp['divider'],
        'msEmpty'   => 'This store is being set up. Check back soon.',
    ])

    @include('common.partials.biolink-block-list', [
        'link'           => $link,
        'blkFontColor'   => $pbInk,
        'blkGlobalTheme' => $pbBs['block_theme'] ?? [],
        'blkBtnInline'   => '',
        'blkSlot'        => 'below',
        'blkEmpty'       => false,
    ])
</div>

@if($isOrder)
<div class="cartbar" id="cartbar">
    <div class="inner">
        <div><strong id="cartCount">0</strong> item(s) · <strong id="cartTotal">{{ $fmt(0) }}</strong></div>
        <button type="button" onclick="SM.openCart()">Review request</button>
    </div>
</div>

<div class="modal" id="cartModal">
    <div class="sheet">
        <h3>Your request</h3>
        <div id="cartLines"></div>
        <div class="total"><span>Estimated total</span><span id="modalTotal">{{ $fmt(0) }}</span></div>
        <input class="field" id="fName" placeholder="Your name (optional)">
        <input class="field" id="fContact" placeholder="Phone or email so we can reach you (optional)">
        <textarea class="field" id="fNote" rows="2" placeholder="Notes for your order (optional)"></textarea>
        <button class="primary" id="placeBtn" type="button" onclick="SM.place()">Send order request</button>
        <button class="ghost" type="button" onclick="SM.closeCart()">Keep browsing</button>
        <p class="note">This is an order request, not a checkout. No online payment is collected, the store will contact you to arrange fulfilment and payment.</p>
    </div>
</div>

<div class="modal" id="doneModal">
    <div class="sheet">
        <h3>Request sent 🎉</h3>
        <p>Status: <span class="status-pill" id="ordStatus">New</span></p>
        <div class="total"><span>Estimated total</span><span id="doneTotal"></span></div>
        <p class="note">This is an estimated total, not a final bill. The store has been notified and will reach out. This updates automatically.</p>
        <a id="waBtn" class="wa-btn" href="#" target="_blank" rel="noopener" style="display:none">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.739-.981 1.249.74zm5.392-15.327c-.235-.025-.47-.025-.706-.025-.235 0-.616.088-.939.441-.323.353-1.235 1.206-1.235 2.941 0 1.735 1.264 3.41 1.44 3.646.176.235 2.479 3.785 6.005 5.31.84.363 1.495.58 2.006.742.843.268 1.61.23 2.216.14.676-.101 2.082-.851 2.376-1.673.294-.823.294-1.528.206-1.674-.088-.147-.323-.235-.676-.412-.353-.176-2.082-1.028-2.405-1.146-.323-.117-.558-.176-.793.177-.235.353-.91 1.146-1.116 1.381-.206.235-.411.265-.764.088-.353-.177-1.49-.549-2.838-1.751-1.049-.935-1.757-2.09-1.963-2.443-.206-.353-.022-.544.155-.72.158-.157.353-.412.529-.618.176-.206.235-.353.353-.588.117-.235.059-.441-.029-.617-.088-.177-.793-1.912-1.087-2.617z"/></svg>
            <span>Send request via WhatsApp</span>
        </a>
        <button class="ghost" type="button" onclick="SM.reset()">Back to store</button>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ORDER_URL = @json(route('sm.public.order', ['alias' => $link->alias]));
    const STATUS_BASE = @json(url('/sm/order'));
    // The page's money format, handed to the cart rather than re-derived.
    // The cart total and the item prices used to be two independent copies
    // of "code, space, two decimals", which is how they drift.
    const MONEY = @json($money);
    const ITEMS = {};
    document.querySelectorAll('[data-add]').forEach(el => {
        const id = el.getAttribute('data-add');
        ITEMS[id] = { id: +id, name: el.getAttribute('data-name'), price: parseFloat(el.getAttribute('data-price')), qty: 0 };
    });
    const fmt = n => MONEY.prefix
        + (Math.round(n * 100) / 100).toLocaleString('en-US', {
            minimumFractionDigits: MONEY.decimals, maximumFractionDigits: MONEY.decimals })
        + MONEY.suffix;
    let pollTimer = null;

    function render() {
        let count = 0, total = 0;
        Object.values(ITEMS).forEach(it => {
            count += it.qty; total += it.qty * it.price;
            const step = document.querySelector('[data-stepper="' + it.id + '"]');
            const addBtn = document.querySelector('[data-add="' + it.id + '"] .add');
            const qEl = document.querySelector('[data-qty="' + it.id + '"]');
            if (qEl) qEl.textContent = it.qty;
            if (it.qty > 0) { if (step) step.style.display='inline'; if (addBtn) addBtn.style.display='none'; }
            else { if (step) step.style.display='none'; if (addBtn) addBtn.style.display='inline-block'; }
        });
        document.getElementById('cartCount').textContent = count;
        document.getElementById('cartTotal').textContent = fmt(total);
        document.getElementById('modalTotal').textContent = fmt(total);
        document.getElementById('cartbar').classList.toggle('show', count > 0);
        return { count, total };
    }
    function lines(container) {
        const box = document.getElementById(container);
        box.innerHTML = '';
        Object.values(ITEMS).filter(i => i.qty > 0).forEach(it => {
            const row = document.createElement('div');
            row.className = 'line';
            row.innerHTML = '<span>' + it.qty + '× ' + it.name + '</span><span>' + fmt(it.qty * it.price) + '</span>';
            box.appendChild(row);
        });
    }
    function cartItems() {
        return Object.values(ITEMS).filter(i => i.qty > 0).map(i => ({ product_id: i.id, quantity: i.qty }));
    }

    window.SM = {
        add(id){ ITEMS[id].qty = 1; render(); },
        inc(id){ ITEMS[id].qty++; render(); },
        dec(id){ ITEMS[id].qty = Math.max(0, ITEMS[id].qty - 1); render(); },
        openCart(){ lines('cartLines'); render(); document.getElementById('cartModal').classList.add('show'); },
        closeCart(){ document.getElementById('cartModal').classList.remove('show'); },
        reset(){ if(pollTimer) clearInterval(pollTimer); location.href = location.pathname; },
        async place(){
            const items = cartItems();
            if (!items.length) return;
            const btn = document.getElementById('placeBtn');
            btn.disabled = true; btn.textContent = 'Sending…';
            try {
                const r = await fetch(ORDER_URL, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({
                        customer_name: document.getElementById('fName').value || null,
                        customer_contact: document.getElementById('fContact').value || null,
                        customer_note: document.getElementById('fNote').value || null,
                        items
                    })
                });
                const j = await r.json();
                if (!r.ok) { alert((j.error && j.error.message) || 'Could not send request'); btn.disabled=false; btn.textContent='Send order request'; return; }
                this.showDone(j.data.order);
            } catch(e) { alert('Network error, please try again.'); btn.disabled=false; btn.textContent='Send order request'; }
        },
        showDone(order){
            this.closeCart();
            document.getElementById('doneTotal').textContent = fmt(order.total != null ? order.total : order.subtotal);
            document.getElementById('ordStatus').textContent = order.status_label || order.status;
            const waBtn = document.getElementById('waBtn');
            if (order.whatsapp && order.whatsapp.url) { waBtn.href = order.whatsapp.url; waBtn.style.display = 'flex'; }
            else { waBtn.style.display = 'none'; }
            document.getElementById('doneModal').classList.add('show');
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
