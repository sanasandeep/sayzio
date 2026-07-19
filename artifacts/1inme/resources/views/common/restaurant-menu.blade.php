@php
    /** @var \App\Modules\User\Models\Link $link */
    $menu = $link->restaurantMenu()->with(['categories', 'items'])->first();
    $accent = $menu->accent_color ?: '#3d6bff';
    $currency = $menu->currency ?: 'USD';
    $isOrder = $menu->isOrderMode();
    $title = $link->title ?: $link->alias;

    // Resolve the scanned table (?t=code) so order placement is bound to it.
    $tableCode = request()->query('t');
    $activeTable = null;
    if ($isOrder && $tableCode) {
        $activeTable = $menu->tables()->where('code', $tableCode)->first();
    }

    $cats = $menu->categories->where('is_active', true)->sortBy('sort_order')->values();
    $itemsByCat = $menu->items->where('is_active', true)->sortBy('sort_order')->groupBy('category_id');

    $fmt = fn ($n) => $currency . ' ' . number_format((float) $n, 2);

    // Only the seeded demo restaurant carries a sample WhatsApp number, so we
    // flag the confirmation's "Send order via WhatsApp" button as a demo so
    // real visitors aren't confused by a dead chat. Scoped strictly to the
    // demo (marked by settings.demo on the link), never live restaurants.
    $isDemoRestaurant = (bool) data_get($link->settings, 'demo');
@endphp
<!doctype html>
<html lang="en">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light dark; --accent: {{ $accent }}; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; min-height:100%; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#f6f6f9; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        .page { max-width:760px; margin:0 auto; padding:0 16px 120px; }
        .hero { padding:28px 4px 18px; }
        .hero h1 { margin:0; font-size:26px; font-weight:800; letter-spacing:-.02em; }
        .hero p { margin:6px 0 0; opacity:.65; font-size:14px; }
        .badge { display:inline-block; margin-top:12px; padding:6px 12px; border-radius:999px; background:var(--accent); color:#fff; font-size:12.5px; font-weight:600; }
        .cat { margin-top:26px; }
        .cat h2 { font-size:18px; font-weight:700; margin:0 0 4px; }
        .cat .cdesc { font-size:13px; opacity:.6; margin:0 0 12px; }
        .item { display:flex; gap:14px; padding:14px 0; border-top:1px solid rgba(0,0,0,.07); }
        @media (prefers-color-scheme: dark) { .item { border-color:rgba(255,255,255,.08); } }
        .item .photo { width:74px; height:74px; border-radius:14px; object-fit:cover; flex:0 0 auto; background:rgba(0,0,0,.05); }
        .item .info { flex:1; min-width:0; }
        .item .name { font-weight:650; font-size:15.5px; }
        .item .desc { font-size:13px; opacity:.62; margin-top:3px; line-height:1.4; }
        .item .price { font-weight:700; font-size:14.5px; margin-top:6px; color:var(--accent); }
        .soldout { opacity:.45; }
        .soldout .name::after { content:" · Sold out"; color:#b91c1c; font-size:12px; font-weight:600; }
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
        .coupon-row { display:flex; gap:8px; align-items:center; margin-top:12px; }
        .coupon-row .field { flex:1 1 auto; }
        .coupon-msg { font-size:12.5px; margin:6px 0 0; min-height:0; }
        .coupon-msg.ok { color:#16a34a; }
        .coupon-msg.err { color:#dc2626; }
        .bill-row { display:flex; justify-content:space-between; font-size:13.5px; margin-top:8px; opacity:.85; }
        .bill-row.discount span:last-child { color:#16a34a; }
        .total { display:flex; justify-content:space-between; font-weight:800; font-size:16px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.1); }
        .primary { width:100%; border:none; background:var(--accent); color:#fff; border-radius:12px; padding:14px; font-size:15px; font-weight:700; cursor:pointer; margin-top:14px; }
        .ghost { width:100%; border:1px solid rgba(0,0,0,.15); background:transparent; color:inherit; border-radius:12px; padding:11px; font-size:14px; cursor:pointer; margin-top:8px; }
        .wa-btn { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; box-sizing:border-box; background:#25D366; color:#fff; border-radius:12px; padding:12px; font-size:14px; font-weight:700; text-decoration:none; margin-top:12px; }
        .note { font-size:12.5px; opacity:.6; text-align:center; margin-top:10px; }
        .status-pill { display:inline-block; padding:4px 11px; border-radius:999px; font-size:12.5px; font-weight:700; background:var(--accent); color:#fff; }
        .empty { text-align:center; opacity:.5; padding:40px 0; }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <h1>{{ $title }}</h1>
        @if($desc = $link->description)<p>{{ $desc }}</p>@endif
        @if($isOrder)
            <span class="badge">
                @if($activeTable) Ordering · Table {{ $activeTable->label }}
                @else Order at table @endif
            </span>
        @endif
    </div>

    @forelse($cats as $cat)
        <div class="cat">
            <h2>{{ $cat->name }}</h2>
            @if($cat->description)<p class="cdesc">{{ $cat->description }}</p>@endif
            @foreach(($itemsByCat[$cat->id] ?? collect()) as $item)
                <div class="item {{ $item->is_sold_out ? 'soldout' : '' }}">
                    @if($item->photo_url)<img class="photo" src="{{ $item->photo_url }}" alt="" loading="lazy">@endif
                    <div class="info">
                        <div class="name">{{ $item->name }}</div>
                        @if($item->description)<div class="desc">{{ $item->description }}</div>@endif
                        <div class="price">{{ $fmt($item->price) }}</div>
                        @if($isOrder && !$item->is_sold_out)
                            <div class="addrow" data-add="{{ $item->id }}"
                                 data-name="{{ e($item->name) }}" data-price="{{ $item->price }}">
                                <button class="add" type="button" onclick="RM.add({{ $item->id }})">Add</button>
                                <span data-stepper="{{ $item->id }}" style="display:none;">
                                    <button class="qbtn" type="button" onclick="RM.dec({{ $item->id }})">−</button>
                                    <span class="qty" data-qty="{{ $item->id }}">0</span>
                                    <button class="qbtn" type="button" onclick="RM.inc({{ $item->id }})">+</button>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="empty">This menu is being prepared. Check back soon.</div>
    @endforelse
</div>

@if($isOrder)
<div class="cartbar" id="cartbar">
    <div class="inner">
        <div><strong id="cartCount">0</strong> item(s) · <strong id="cartTotal">{{ $fmt(0) }}</strong></div>
        <button type="button" onclick="RM.openCart()">Review order</button>
    </div>
</div>

<div class="modal" id="cartModal">
    <div class="sheet">
        <h3>Your order</h3>
        <div id="cartLines"></div>
        <div class="coupon-row">
            <input class="field" id="fCoupon" placeholder="Discount code (optional)" style="text-transform:uppercase;margin-top:0">
            <button class="ghost" type="button" id="couponBtn" onclick="RM.applyCoupon()" style="margin-top:0;width:auto;padding:11px 16px;white-space:nowrap">Apply</button>
        </div>
        <p class="coupon-msg" id="couponMsg"></p>
        <div id="billBreakdown"></div>
        <div class="total"><span>Estimated total</span><span id="modalTotal">{{ $fmt(0) }}</span></div>
        @unless($activeTable)
            <input class="field" id="fTable" placeholder="Table number (optional)">
        @endunless
        <input class="field" id="fName" placeholder="Your name (optional)">
        <textarea class="field" id="fNote" rows="2" placeholder="Notes for the kitchen (optional)"></textarea>
        <button class="primary" id="placeBtn" type="button" onclick="RM.place()">Place order</button>
        <button class="ghost" type="button" onclick="RM.closeCart()">Keep browsing</button>
        <p class="note">This is an estimated bill, not the actual bill. No online payment, you'll pay staff directly at your table.</p>
    </div>
</div>

<div class="modal" id="doneModal">
    <div class="sheet">
        <h3>Order placed 🎉</h3>
        <p>Status: <span class="status-pill" id="ordStatus">New</span></p>
        <div id="doneBreakdown"></div>
        <div class="total"><span>Estimated total</span><span id="doneTotal"></span></div>
        <p class="note">This is an estimated bill, not the actual bill. A staff member has been notified. This updates automatically.</p>
        <a id="waBtn" class="wa-btn" href="#" target="_blank" rel="noopener" style="display:none">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M.057 24l1.687-6.163a11.867 11.867 0 01-1.587-5.946C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 018.413 3.488 11.824 11.824 0 013.48 8.414c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 01-5.688-1.448L.057 24zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884a9.86 9.86 0 001.51 5.26l-.999 3.648 3.739-.981 1.249.74zm5.392-15.327c-.235-.025-.47-.025-.706-.025-.235 0-.616.088-.939.441-.323.353-1.235 1.206-1.235 2.941 0 1.735 1.264 3.41 1.44 3.646.176.235 2.479 3.785 6.005 5.31.84.363 1.495.58 2.006.742.843.268 1.61.23 2.216.14.676-.101 2.082-.851 2.376-1.673.294-.823.294-1.528.206-1.674-.088-.147-.323-.235-.676-.412-.353-.176-2.082-1.028-2.405-1.146-.323-.117-.558-.176-.793.177-.235.353-.91 1.146-1.116 1.381-.206.235-.411.265-.764.088-.353-.177-1.49-.549-2.838-1.751-1.049-.935-1.757-2.09-1.963-2.443-.206-.353-.022-.544.155-.72.158-.157.353-.412.529-.618.176-.206.235-.353.353-.588.117-.235.059-.441-.029-.617-.088-.177-.793-1.912-1.087-2.617z"/></svg>
            <span>Send order via WhatsApp</span>
        </a>
@if($isDemoRestaurant)
        <p class="note" id="waDemoNote" style="display:none">Demo only, this is a sample WhatsApp number, so the chat won't open a real conversation. On a live menu this opens a chat with the restaurant.</p>
@endif
        <button class="ghost" type="button" onclick="RM.reset()">Back to menu</button>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const ORDER_URL = @json(route('rm.public.order', ['alias' => $link->alias]));
    const QUOTE_URL = @json(route('rm.public.quote', ['alias' => $link->alias]));
    const STATUS_BASE = @json(url('/rm/order'));
    const CURRENCY = @json($currency);
    const TABLE_CODE = @json($activeTable->code ?? null);
    let appliedCoupon = '';
    let lastBill = null;
    let quoteSeq = 0;
    const ITEMS = {};
    document.querySelectorAll('[data-add]').forEach(el => {
        const id = el.getAttribute('data-add');
        ITEMS[id] = { id: +id, name: el.getAttribute('data-name'), price: parseFloat(el.getAttribute('data-price')), qty: 0 };
    });
    const fmt = n => CURRENCY + ' ' + (Math.round(n * 100) / 100).toFixed(2);
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
        return Object.values(ITEMS).filter(i => i.qty > 0).map(i => ({ item_id: i.id, quantity: i.qty }));
    }
    // Render the itemised estimate from a calculator breakdown into a target box,
    // and set the matching "estimated total" element.
    function renderBill(boxId, totalId, bill) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.innerHTML = '';
        if (!bill) { return; }
        const add = (label, value, cls) => {
            const r = document.createElement('div');
            r.className = 'bill-row' + (cls ? (' ' + cls) : '');
            r.innerHTML = '<span>' + label + '</span><span>' + value + '</span>';
            box.appendChild(r);
        };
        add('Subtotal', fmt(bill.subtotal));
        if (bill.coupon_applied && bill.discount_amount > 0) {
            add('Discount (' + bill.coupon_code + ')', '−' + fmt(bill.discount_amount), 'discount');
        }
        if (bill.tax_enabled && bill.tax_amount > 0) {
            const label = (bill.tax_label || 'Tax') + ' (' + (+bill.tax_rate) + '%)' + (bill.tax_inclusive ? ' incl.' : '');
            add(label, fmt(bill.tax_amount));
        }
        const totalEl = document.getElementById(totalId);
        if (totalEl) totalEl.textContent = fmt(bill.total);
    }
    // Ask the server for a fresh estimate whenever the cart or coupon changes.
    async function refreshQuote() {
        const items = cartItems();
        const fallbackTotal = Object.values(ITEMS).reduce((s, it) => s + it.qty * it.price, 0);
        if (!items.length) { lastBill = null; renderBill('billBreakdown', 'modalTotal', null); document.getElementById('modalTotal').textContent = fmt(0); return; }
        const seq = ++quoteSeq;
        try {
            const r = await fetch(QUOTE_URL, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                body: JSON.stringify({ items, coupon_code: appliedCoupon || null })
            });
            const j = await r.json();
            if (seq !== quoteSeq) return;
            if (!r.ok) { lastBill = null; document.getElementById('modalTotal').textContent = fmt(fallbackTotal); return; }
            lastBill = j.data.bill;
            renderBill('billBreakdown', 'modalTotal', lastBill);
            const msg = document.getElementById('couponMsg');
            if (appliedCoupon && lastBill.coupon_applied) { msg.textContent = 'Code applied ✓'; msg.className = 'coupon-msg ok'; }
            else if (appliedCoupon && lastBill.coupon_error) { msg.textContent = lastBill.coupon_error; msg.className = 'coupon-msg err'; }
            else { msg.textContent = ''; msg.className = 'coupon-msg'; }
        } catch(e) {
            if (seq !== quoteSeq) return;
            document.getElementById('modalTotal').textContent = fmt(fallbackTotal);
        }
    }

    window.RM = {
        add(id){ ITEMS[id].qty = 1; render(); },
        inc(id){ ITEMS[id].qty++; render(); },
        dec(id){ ITEMS[id].qty = Math.max(0, ITEMS[id].qty - 1); render(); },
        openCart(){ lines('cartLines'); refreshQuote(); document.getElementById('cartModal').classList.add('show'); },
        closeCart(){ document.getElementById('cartModal').classList.remove('show'); },
        applyCoupon(){
            appliedCoupon = (document.getElementById('fCoupon').value || '').trim().toUpperCase();
            document.getElementById('fCoupon').value = appliedCoupon;
            refreshQuote();
        },
        reset(){ if(pollTimer) clearInterval(pollTimer); location.href = location.pathname + (TABLE_CODE ? ('?t='+TABLE_CODE) : ''); },
        async place(){
            const items = cartItems();
            if (!items.length) return;
            const btn = document.getElementById('placeBtn');
            btn.disabled = true; btn.textContent = 'Placing…';
            try {
                const r = await fetch(ORDER_URL, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({
                        table_code: TABLE_CODE,
                        customer_name: document.getElementById('fName').value || null,
                        customer_note: document.getElementById('fNote').value || null,
                        coupon_code: appliedCoupon || null,
                        items
                    })
                });
                const j = await r.json();
                if (!r.ok) { alert((j.error && j.error.message) || 'Could not place order'); btn.disabled=false; btn.textContent='Place order'; return; }
                this.showDone(j.data.order);
            } catch(e) { alert('Network error, please try again.'); btn.disabled=false; btn.textContent='Place order'; }
        },
        showDone(order){
            this.closeCart();
            lines('doneLines');
            renderBill('doneBreakdown', 'doneTotal', {
                subtotal: order.subtotal,
                coupon_applied: !!order.coupon_code,
                coupon_code: order.coupon_code,
                discount_amount: order.discount_amount,
                tax_enabled: order.tax_amount > 0,
                tax_inclusive: order.tax_inclusive,
                tax_rate: order.tax_rate,
                tax_label: 'Tax',
                tax_amount: order.tax_amount,
                total: order.total
            });
            document.getElementById('doneTotal').textContent = fmt(order.total != null ? order.total : order.subtotal);
            document.getElementById('ordStatus').textContent = order.status;
            const waBtn = document.getElementById('waBtn');
            const waDemoNote = document.getElementById('waDemoNote');
            if (order.whatsapp && order.whatsapp.url) {
                waBtn.href = order.whatsapp.url; waBtn.style.display = 'flex';
                if (waDemoNote) waDemoNote.style.display = 'block';
            }
            else {
                waBtn.style.display = 'none';
                if (waDemoNote) waDemoNote.style.display = 'none';
            }
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
@include('common.partials.link-type-pairings', ['pairingType' => 'restaurant_menu', 'theme' => 'light'])
</body>
</html>
