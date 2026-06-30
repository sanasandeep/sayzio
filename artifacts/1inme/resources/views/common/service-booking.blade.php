@php
    /** @var \App\Modules\User\Models\Link $link */
    $config = $link->serviceBooking()->with(['categories', 'services'])->first();
    $accent = $config->accent_color ?: '#3d6bff';
    $currency = $config->currency ?: 'USD';
    $isBooking = $config->isBookingMode();
    $title = $link->title ?: $link->alias;

    $cats = $config->categories->where('is_active', true)->sortBy('sort_order')->values();
    $services = $config->services->where('is_active', true)->sortBy('sort_order')->values();
    $servicesByCat = $services->groupBy('category_id');
    $uncategorized = $servicesByCat->get(null) ?? $servicesByCat->get('') ?? collect();

    $fmt = fn ($n) => $currency . ' ' . number_format((float) $n, 2);
    $taxLabel = $config->taxEnabled() ? $config->taxLabel() : null;

    $durLabel = function ($mins) {
        $mins = (int) $mins;
        if ($mins < 60) return $mins . ' min';
        $h = intdiv($mins, 60); $m = $mins % 60;
        return $m ? ($h . 'h ' . $m . 'm') : ($h . 'h');
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <style>
        :root { color-scheme: light dark; --accent: {{ $accent }}; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; min-height:100%; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#f6f6f9; color:#111; }
        @media (prefers-color-scheme: dark) { html, body { background:#0b0b10; color:#f5f5f7; } }
        .page { max-width:760px; margin:0 auto; padding:0 16px 130px; }
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
        .item .meta { font-weight:700; font-size:14px; margin-top:6px; color:var(--accent); }
        .item .meta .dur { color:inherit; opacity:.6; font-weight:500; }
        .unavail { opacity:.45; }
        .unavail .name::after { content:" · Unavailable"; color:#b91c1c; font-size:12px; font-weight:600; }
        .addrow { margin-top:8px; }
        .qbtn { width:30px; height:30px; border-radius:8px; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; font-size:17px; cursor:pointer; line-height:1; }
        @media (prefers-color-scheme: dark) { .qbtn { border-color:rgba(255,255,255,.2); } }
        .qty { min-width:22px; text-align:center; display:inline-block; font-weight:600; }
        .add { border:none; background:var(--accent); color:#fff; border-radius:9px; padding:7px 14px; font-size:13px; font-weight:600; cursor:pointer; }
        .cartbar { position:fixed; left:0; right:0; bottom:0; padding:12px 16px calc(12px + env(safe-area-inset-bottom)); background:#fff; border-top:1px solid rgba(0,0,0,.1); display:none; }
        @media (prefers-color-scheme: dark) { .cartbar { background:#15151c; border-color:rgba(255,255,255,.1); } }
        .cartbar.show { display:block; }
        .cartbar .inner { max-width:760px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .cartbar button { border:none; background:var(--accent); color:#fff; border-radius:11px; padding:13px 20px; font-size:15px; font-weight:700; cursor:pointer; }
        .modal { position:fixed; inset:0; background:rgba(0,0,0,.5); display:none; align-items:flex-end; justify-content:center; z-index:50; }
        .modal.show { display:flex; }
        .sheet { background:#fff; color:#111; width:100%; max-width:760px; border-radius:18px 18px 0 0; padding:20px 18px calc(20px + env(safe-area-inset-bottom)); max-height:90vh; overflow:auto; }
        @media (prefers-color-scheme: dark) { .sheet { background:#15151c; color:#f5f5f7; } }
        .sheet h3 { margin:0 0 12px; font-size:18px; }
        .line { display:flex; justify-content:space-between; gap:10px; padding:7px 0; font-size:14px; }
        .field { width:100%; padding:11px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; font-size:14px; margin-top:8px; font-family:inherit; }
        @media (prefers-color-scheme: dark) { .field { border-color:rgba(255,255,255,.2); } }
        .bill-row { display:flex; justify-content:space-between; font-size:13.5px; margin-top:8px; opacity:.85; }
        .total { display:flex; justify-content:space-between; font-weight:800; font-size:16px; margin-top:12px; padding-top:12px; border-top:1px solid rgba(0,0,0,.1); }
        .primary { width:100%; border:none; background:var(--accent); color:#fff; border-radius:12px; padding:14px; font-size:15px; font-weight:700; cursor:pointer; margin-top:14px; }
        .primary:disabled { opacity:.5; cursor:not-allowed; }
        .ghost { width:100%; border:1px solid rgba(0,0,0,.15); background:transparent; color:inherit; border-radius:12px; padding:11px; font-size:14px; cursor:pointer; margin-top:8px; }
        .note { font-size:12.5px; opacity:.6; text-align:center; margin-top:10px; }
        .status-pill { display:inline-block; padding:4px 11px; border-radius:999px; font-size:12.5px; font-weight:700; background:var(--accent); color:#fff; }
        .empty { text-align:center; opacity:.5; padding:40px 0; }
        .sec-h { font-size:13px; font-weight:700; opacity:.7; margin:16px 0 6px; text-transform:uppercase; letter-spacing:.04em; }
        .day-h { font-size:14px; font-weight:700; margin:14px 0 8px; }
        .slots { display:flex; flex-wrap:wrap; gap:8px; }
        .slot { border:1px solid rgba(0,0,0,.18); background:transparent; color:inherit; border-radius:9px; padding:9px 13px; font-size:13.5px; cursor:pointer; font-family:inherit; }
        @media (prefers-color-scheme: dark) { .slot { border-color:rgba(255,255,255,.2); } }
        .slot.sel { background:var(--accent); color:#fff; border-color:var(--accent); }
        .slotbox { max-height:300px; overflow:auto; margin-top:4px; }
        .muted { opacity:.6; font-size:13px; }
    </style>
</head>
<body>
<div class="page">
    <div class="hero">
        <h1>{{ $title }}</h1>
        @if($desc = $link->description)<p>{{ $desc }}</p>@endif
        @if($isBooking)<span class="badge">Book an appointment</span>@endif
    </div>

    @php
        $renderService = function ($service) use ($fmt, $isBooking, $durLabel) {
            $unavail = $service->is_unavailable;
            echo '<div class="item ' . ($unavail ? 'unavail' : '') . '">';
            if ($service->photo_url) {
                echo '<img class="photo" src="' . e($service->photo_url) . '" alt="" loading="lazy">';
            }
            echo '<div class="info">';
            echo '<div class="name">' . e($service->name) . '</div>';
            if ($service->description) {
                echo '<div class="desc">' . e($service->description) . '</div>';
            }
            echo '<div class="meta">' . e($fmt($service->price));
            if ((int) $service->duration_minutes > 0) {
                echo ' <span class="dur">· ' . e($durLabel($service->duration_minutes)) . '</span>';
            }
            echo '</div>';
            if ($isBooking && !$unavail) {
                echo '<div class="addrow" data-add="' . $service->id . '" data-name="' . e($service->name)
                    . '" data-price="' . $service->price . '" data-duration="' . (int) $service->duration_minutes . '">';
                echo '<button class="add" type="button" onclick="SB.add(' . $service->id . ')">Select</button>';
                echo '<span data-stepper="' . $service->id . '" style="display:none;">';
                echo '<button class="qbtn" type="button" onclick="SB.dec(' . $service->id . ')">&minus;</button>';
                echo '<span class="qty" data-qty="' . $service->id . '">0</span>';
                echo '<button class="qbtn" type="button" onclick="SB.inc(' . $service->id . ')">+</button>';
                echo '</span></div>';
            }
            echo '</div></div>';
        };
    @endphp

    @if($services->isEmpty())
        <div class="empty">This booking page is being prepared. Check back soon.</div>
    @else
        @foreach($cats as $cat)
            @php $catServices = $servicesByCat[$cat->id] ?? collect(); @endphp
            @if($catServices->isNotEmpty())
                <div class="cat">
                    <h2>{{ $cat->name }}</h2>
                    @if($cat->description)<p class="cdesc">{{ $cat->description }}</p>@endif
                    @foreach($catServices as $service) @php $renderService($service); @endphp @endforeach
                </div>
            @endif
        @endforeach
        @if($uncategorized->isNotEmpty())
            <div class="cat">
                @if($cats->isNotEmpty())<h2>More services</h2>@endif
                @foreach($uncategorized as $service) @php $renderService($service); @endphp @endforeach
            </div>
        @endif
    @endif
</div>

@if($isBooking)
<div class="cartbar" id="cartbar">
    <div class="inner">
        <div><strong id="cartCount">0</strong> service(s) · <strong id="cartTotal">{{ $fmt(0) }}</strong></div>
        <button type="button" onclick="SB.openCart()">Choose a time</button>
    </div>
</div>

<div class="modal" id="cartModal">
    <div class="sheet">
        <h3>Your booking</h3>
        <div id="cartLines"></div>
        <div id="billBreakdown"></div>
        <div class="total"><span>Estimated total</span><span id="modalTotal">{{ $fmt(0) }}</span></div>
        <p class="muted" id="durSummary"></p>

        <div class="sec-h">Pick a time</div>
        <div class="slotbox" id="slotBox"><p class="muted">Loading available times…</p></div>

        <div class="sec-h">Your details</div>
        <input class="field" id="fName" placeholder="Your name" required>
        <input class="field" id="fEmail" type="email" placeholder="Email (optional)">
        <input class="field" id="fPhone" placeholder="Phone (optional)">
        <textarea class="field" id="fNote" rows="2" placeholder="Anything we should know? (optional)"></textarea>

        <button class="primary" id="bookBtn" type="button" onclick="SB.book()" disabled>Request booking</button>
        <button class="ghost" type="button" onclick="SB.closeCart()">Keep browsing</button>
        <p class="note">This is an estimated price, not a final bill. No online payment is taken — you'll settle with the provider directly. Your slot is a request and isn't confirmed until the provider accepts it.</p>
    </div>
</div>

<div class="modal" id="doneModal">
    <div class="sheet">
        <h3>Booking requested 🎉</h3>
        <p>Status: <span class="status-pill" id="bkStatus">Pending</span></p>
        <p class="muted" id="doneWhen"></p>
        <div id="doneBreakdown"></div>
        <div class="total"><span>Estimated total</span><span id="doneTotal"></span></div>
        <p class="note">This is an estimated price, not a final bill. The provider has been notified and will confirm your request. This page updates automatically.</p>
        <a id="statusLink" class="ghost" href="#" style="display:block;text-align:center;text-decoration:none;line-height:1.4">Save your status link</a>
        <button class="ghost" type="button" onclick="SB.reset()">Back to services</button>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const SLOTS_URL = @json(route('sb.public.slots', ['alias' => $link->alias]));
    const QUOTE_URL = @json(route('sb.public.quote', ['alias' => $link->alias]));
    const BOOK_URL = @json(route('sb.public.book', ['alias' => $link->alias]));
    const STATUS_BASE = @json(url('/sb/booking'));
    const CURRENCY = @json($currency);
    const SERVICES = {};
    let selectedSlot = null;
    let lastBill = null;
    let quoteSeq = 0, slotSeq = 0;
    let pollTimer = null;

    document.querySelectorAll('[data-add]').forEach(el => {
        const id = el.getAttribute('data-add');
        SERVICES[id] = {
            id: +id,
            name: el.getAttribute('data-name'),
            price: parseFloat(el.getAttribute('data-price')),
            duration: parseInt(el.getAttribute('data-duration'), 10) || 0,
            qty: 0,
        };
    });
    const fmt = n => CURRENCY + ' ' + (Math.round(n * 100) / 100).toFixed(2);
    const durLabel = m => { m = +m; if (m < 60) return m + ' min'; const h = Math.floor(m/60), r = m%60; return r ? (h+'h '+r+'m') : (h+'h'); };

    function render() {
        let count = 0, total = 0;
        Object.values(SERVICES).forEach(it => {
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
        document.getElementById('cartbar').classList.toggle('show', count > 0);
        return { count, total };
    }
    function lines(container) {
        const box = document.getElementById(container);
        box.innerHTML = '';
        Object.values(SERVICES).filter(i => i.qty > 0).forEach(it => {
            const row = document.createElement('div');
            row.className = 'line';
            row.innerHTML = '<span>' + it.qty + '× ' + it.name + '</span><span>' + fmt(it.qty * it.price) + '</span>';
            box.appendChild(row);
        });
    }
    function cartItems() {
        return Object.values(SERVICES).filter(i => i.qty > 0).map(i => ({ service_id: i.id, quantity: i.qty }));
    }
    function renderBill(boxId, totalId, bill) {
        const box = document.getElementById(boxId);
        if (!box) return;
        box.innerHTML = '';
        if (!bill) return;
        const add = (label, value) => {
            const r = document.createElement('div');
            r.className = 'bill-row';
            r.innerHTML = '<span>' + label + '</span><span>' + value + '</span>';
            box.appendChild(r);
        };
        add('Subtotal', fmt(bill.subtotal));
        if (bill.tax_enabled && bill.tax_amount > 0) {
            const label = (bill.tax_label || 'Tax') + ' (' + (+bill.tax_rate) + '%)' + (bill.tax_inclusive ? ' incl.' : '');
            add(label, fmt(bill.tax_amount));
        }
        const totalEl = document.getElementById(totalId);
        if (totalEl) totalEl.textContent = fmt(bill.total);
    }
    function updateBookBtn() {
        const items = cartItems();
        const name = (document.getElementById('fName').value || '').trim();
        document.getElementById('bookBtn').disabled = !(items.length && selectedSlot && name);
    }
    async function refreshQuote() {
        const items = cartItems();
        const fallback = Object.values(SERVICES).reduce((s, it) => s + it.qty * it.price, 0);
        if (!items.length) { lastBill = null; renderBill('billBreakdown', 'modalTotal', null); document.getElementById('modalTotal').textContent = fmt(0); return; }
        const seq = ++quoteSeq;
        try {
            const r = await fetch(QUOTE_URL, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                body: JSON.stringify({ services: items })
            });
            const j = await r.json();
            if (seq !== quoteSeq) return;
            if (!r.ok) { lastBill = null; document.getElementById('modalTotal').textContent = fmt(fallback); return; }
            lastBill = j.data.bill;
            renderBill('billBreakdown', 'modalTotal', lastBill);
            document.getElementById('durSummary').textContent = 'Total time · ' + durLabel(j.data.duration_minutes);
        } catch(e) {
            if (seq !== quoteSeq) return;
            document.getElementById('modalTotal').textContent = fmt(fallback);
        }
    }
    async function loadSlots() {
        const items = cartItems();
        const box = document.getElementById('slotBox');
        selectedSlot = null;
        updateBookBtn();
        if (!items.length) { box.innerHTML = '<p class="muted">Select a service first.</p>'; return; }
        box.innerHTML = '<p class="muted">Loading available times…</p>';
        const seq = ++slotSeq;
        try {
            const r = await fetch(SLOTS_URL, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                body: JSON.stringify({ services: items })
            });
            const j = await r.json();
            if (seq !== slotSeq) return;
            if (!r.ok) { box.innerHTML = '<p class="muted">' + ((j.error && j.error.message) || 'Could not load times') + '</p>'; return; }
            const days = j.data.days || [];
            if (!days.length) { box.innerHTML = '<p class="muted">No open times right now. Please check back later.</p>'; return; }
            box.innerHTML = '';
            days.forEach(day => {
                const h = document.createElement('div'); h.className = 'day-h'; h.textContent = day.label; box.appendChild(h);
                const wrap = document.createElement('div'); wrap.className = 'slots';
                day.slots.forEach(slot => {
                    const b = document.createElement('button');
                    b.type = 'button'; b.className = 'slot'; b.textContent = slot.label;
                    b.setAttribute('data-start', slot.start);
                    b.onclick = () => {
                        selectedSlot = slot.start;
                        box.querySelectorAll('.slot.sel').forEach(s => s.classList.remove('sel'));
                        b.classList.add('sel');
                        updateBookBtn();
                    };
                    wrap.appendChild(b);
                });
                box.appendChild(wrap);
            });
        } catch(e) {
            if (seq !== slotSeq) return;
            box.innerHTML = '<p class="muted">Network error. Please try again.</p>';
        }
    }

    document.getElementById('fName').addEventListener('input', updateBookBtn);

    window.SB = {
        add(id){ SERVICES[id].qty = 1; render(); },
        inc(id){ SERVICES[id].qty++; render(); },
        dec(id){ SERVICES[id].qty = Math.max(0, SERVICES[id].qty - 1); render(); },
        openCart(){ lines('cartLines'); refreshQuote(); loadSlots(); document.getElementById('cartModal').classList.add('show'); },
        closeCart(){ document.getElementById('cartModal').classList.remove('show'); },
        reset(){ if(pollTimer) clearInterval(pollTimer); location.href = location.pathname; },
        async book(){
            const items = cartItems();
            if (!items.length || !selectedSlot) return;
            const name = (document.getElementById('fName').value || '').trim();
            if (!name) return;
            const btn = document.getElementById('bookBtn');
            btn.disabled = true; btn.textContent = 'Requesting…';
            try {
                const r = await fetch(BOOK_URL, {
                    method:'POST',
                    headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},
                    body: JSON.stringify({
                        customer_name: name,
                        customer_email: document.getElementById('fEmail').value || null,
                        customer_phone: document.getElementById('fPhone').value || null,
                        customer_note: document.getElementById('fNote').value || null,
                        slot_start: selectedSlot,
                        services: items
                    })
                });
                const j = await r.json();
                if (!r.ok) { alert((j.error && j.error.message) || 'Could not request booking'); btn.disabled=false; btn.textContent='Request booking'; return; }
                this.showDone(j.data.booking);
            } catch(e) { alert('Network error, please try again.'); btn.disabled=false; btn.textContent='Request booking'; }
        },
        showDone(booking){
            this.closeCart();
            renderBill('doneBreakdown', 'doneTotal', {
                subtotal: booking.subtotal,
                tax_enabled: booking.tax_amount > 0,
                tax_inclusive: booking.tax_inclusive,
                tax_rate: booking.tax_rate,
                tax_label: 'Tax',
                tax_amount: booking.tax_amount,
                total: booking.total
            });
            document.getElementById('doneTotal').textContent = fmt(booking.total != null ? booking.total : booking.subtotal);
            document.getElementById('bkStatus').textContent = booking.status_label || 'Pending';
            if (booking.slot_start) {
                try {
                    const d = new Date(booking.slot_start);
                    document.getElementById('doneWhen').textContent = d.toLocaleString([], { weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
                } catch(e){}
            }
            const statusUrl = STATUS_BASE + '/' + booking.public_token;
            const sl = document.getElementById('statusLink');
            sl.href = statusUrl;
            document.getElementById('doneModal').classList.add('show');
            const pollUrl = statusUrl + '/status';
            pollTimer = setInterval(async () => {
                try {
                    const r = await fetch(pollUrl);
                    if (!r.ok) return;
                    const j = await r.json();
                    document.getElementById('bkStatus').textContent = j.data.booking.status_label;
                    if (['completed','cancelled','declined'].includes(j.data.booking.status)) clearInterval(pollTimer);
                } catch(e){}
            }, 5000);
        }
    };
})();
</script>
@endif
</body>
</html>
