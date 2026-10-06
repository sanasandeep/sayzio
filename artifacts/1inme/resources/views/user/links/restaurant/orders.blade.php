@extends('user.layouts.app')
@section('title', 'Orders - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .ro-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:18px; margin-bottom:14px; }
    .ro-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .ro-table { font-weight:700; font-size:16px; color:var(--text-primary); }
    /* The number the kitchen calls out. Big enough to read across a
       counter, and first, because it is what staff match an order to a
       person by. */
    .ro-tok {
        display:inline-flex; align-items:center; justify-content:center;
        min-width:38px; height:38px; padding:0 9px; margin-right:10px;
        border-radius:10px; background:var(--bg-subtle,rgba(128,128,128,.14));
        font-weight:800; font-size:18px; font-variant-numeric:tabular-nums;
        color:var(--text-primary);
    }
    .ro-phone { color:inherit; text-decoration:none; border-bottom:1px dotted currentColor; }
    .ro-wanted { color:var(--text-primary); font-weight:600; }
    .ro-meta { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .ro-line { display:flex; justify-content:space-between; font-size:13.5px; padding:4px 0; color:var(--text-primary); }
    .ro-breakdown { margin-top:8px; padding-top:8px; border-top:1px dashed var(--border-glass); }
    .ro-bline { display:flex; justify-content:space-between; font-size:12.5px; padding:2px 0; color:var(--text-muted); }
    .ro-bline.ro-discount span:last-child { color:#10b981; }
    .ro-total { display:flex; justify-content:space-between; font-weight:700; margin-top:6px; padding-top:6px; border-top:1px dashed var(--border-glass); color:var(--text-primary); }
    .ro-estimate-note { font-size:11.5px; color:var(--text-muted); font-style:italic; margin-top:4px; }
    .ro-status { padding:4px 11px; border-radius:999px; font-size:12px; font-weight:700; color:#fff; }
    .st-new{background:#ef4444}.st-accepted{background:#f59e0b}.st-preparing{background:#3b82f6}.st-ready{background:#10b981}.st-completed{background:#6b7280}.st-cancelled{background:#9ca3af}
    .ro-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
    .ro-btn { padding:7px 13px; border-radius:999px; font-size:12.5px; font-weight:600; border:1px solid var(--border-glass); background:transparent; color:var(--text-muted); cursor:pointer; }
    .ro-btn.active { background:linear-gradient(135deg,#5c83ff,#6366f1); color:#fff; border:0; }
    .ro-note { font-size:12.5px; color:var(--text-muted); margin-top:6px; font-style:italic; }
    .ro-empty { text-align:center; padding:50px 0; color:var(--text-muted); }
    .ro-live { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
    .ro-dot { width:8px; height:8px; border-radius:50%; background:#10b981; animation:ropulse 1.6s infinite; }
    @keyframes ropulse { 0%,100%{opacity:1}50%{opacity:.3} }
    .ro-more { display:block; width:100%; margin-top:4px; text-align:center; }
    .ro-card.ro-highlight { border-color:#5c83ff; box-shadow:0 0 0 2px rgba(92,131,255,.45); }
</style>

<div class="w-full max-w-7xl mx-auto" x-data="ordersBoard()" x-init="init()">
    @include('user.links.partials.editor-header', [
        'link' => $link,
        'activeMainTab' => 'orders',
        'editorBack' => route('user.links.restaurant.editor', $link),
    ])
    <div class="flex justify-end mb-4">
        <span class="ro-live" role="status" aria-live="polite">
            <span class="ro-dot" x-show="meta.is_live" aria-hidden="true"></span>
            <span x-text="meta.is_live ? 'Live' : meta.label"></span>
        </span>
    </div>

    @include('user.links.partials.orders-range-bar', ['rbRoute' => route('user.links.restaurant.orders', $link)])

    @include('user.links.partials.orders-summary', [
        'osSummary'  => $summary,
        'osLabels'   => $labels,
        'osCurrency' => $menu->currency,
        'osExport'   => $exportUrl . (str_contains($exportUrl, '?') ? '' : '?'),
        'osRange'    => $range,
        'osKitchen'  => route('user.links.restaurant.kitchen', $link),
    ])

    @include('user.links.partials.orders-insights', [
        'oiData'     => $insights,
        'oiCurrency' => $menu->currency,
        'oiNoun'     => 'item',
        'oiRange'    => $range,
    ])

    @include('user.links.partials.orders-preorders', ['preorderKind' => 'restaurant'])
    @include('user.links.partials.orders-performance', ['performanceSummary' => $summary, 'performanceInsights' => $insights, 'performanceCurrency' => $menu->currency])
    @include('user.links.partials.orders-pickup-display', ['pickupUrl' => route('user.links.restaurant.kitchen.poll', $link).'?display=1'])

    @include('user.links.partials.orders-counter', [
        'ocBase' => \Illuminate\Support\Str::beforeLast(route('user.links.restaurant.meal-coupons.by-phone', $link), '/by-phone'),
        'ocNoun' => 'order',
        'ocBoard' => route('user.links.restaurant.orders', $link),
        'ocOrderBase' => \Illuminate\Support\Str::beforeLast(route('user.links.restaurant.orders.by-code', ['link' => $link, 'code' => 'X']), '/X'),
    ])


    <div class="flex gap-2 mb-4 flex-wrap">
        <button class="ro-btn" :class="filter==='open' ? 'active' : ''" @click="filter='open'">Open (<span x-text="openCount"></span>)</button>
        <button class="ro-btn" :class="filter==='all' ? 'active' : ''" @click="filter='all'">All</button>
    </div>

    <template x-if="visible().length === 0">
        <div class="ro-empty"><i class="fas fa-receipt text-3xl mb-3 block"></i><span class="block" x-text="emptyMessage()"></span></div>
    </template>

    <template x-for="o in visible()" :key="o.id">
        <div class="ro-card" :id="'order-' + o.id" :class="o.id === highlight ? 'ro-highlight' : ''">
            <div class="ro-head">
                <div>
                    <div style="display:flex;align-items:center">
                        <span class="ro-tok" x-show="o.token_number" x-text="o.token_number"></span>
                        <div>
                            <div class="ro-table" x-text="o.table_label ? ('Table ' + o.table_label) : 'Walk-in'"></div>
                            <div class="ro-meta">
                                <span x-show="o.customer_name" x-text="o.customer_name + ' · '"></span>
                                <template x-if="o.customer_phone">
                                    <span><a class="ro-phone" :href="'tel:' + o.customer_phone" x-text="o.customer_phone"></a> · </span>
                                </template>
                                <span x-text="timeAgo(o.created_at)"></span> · #<span x-text="o.id"></span>
                                <template x-if="o.wanted_at">
                                    <span class="ro-wanted" x-text="' · for ' + wantedLabel(o.wanted_at)"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <span class="ro-status" :class="'st-' + o.status" x-text="statusLabel(o.status)"></span>
            </div>
            <div style="margin-top:10px">
                <template x-for="it in o.items" :key="it.id">
                    <div class="ro-line"><span x-text="it.quantity + '× ' + it.name"></span><span x-text="money(it.line_total, o.currency)"></span></div>
                </template>
            </div>
            <div class="ro-note" x-show="o.customer_note" x-text="'“' + o.customer_note + '”'"></div>
            <template x-for="(part, index) in o.meta?.tax_breakdown || []" :key="index"><p class="text-sm" x-text="part.name + ' (' + part.rate_bps / 100 + '%): ' + money(part.amount_minor / 100, o.currency)"></p></template>
            <p class="text-sm" x-show="o.meta?.billing_company" x-text="o.meta?.billing_company?.legal_name || o.meta?.billing_company?.name || ''"></p>
            <p class="text-sm" x-show="o.meta?.billing_company" x-text="(o.meta?.billing_company?.tax_ids || []).join(' · ')"></p>
            <div class="ro-breakdown">
                <div class="ro-bline"><span>Subtotal</span><span x-text="money(o.subtotal, o.currency)"></span></div>
                <div class="ro-bline ro-discount" x-show="o.coupon_code && +o.discount_amount > 0">
                    <span x-text="'Discount (' + o.coupon_code + ')'"></span>
                    <span x-text="'−' + money(o.discount_amount, o.currency)"></span>
                </div>
                <div class="ro-bline" x-show="+o.tax_amount > 0">
                    <span x-text="'Tax (' + (+o.tax_rate) + '%)' + (o.tax_inclusive ? ' incl.' : '')"></span>
                    <span x-text="money(o.tax_amount, o.currency)"></span>
                </div>
            </div>
            <p class="ro-btn" x-show="o.meta?.coupon_reservation">Prepaid coupon serving · redeem coupon at collection</p>
            <div class="ro-total"><span>Estimated total</span><span x-text="money(o.total != null ? o.total : o.subtotal, o.currency)"></span></div>
            <p class="ro-estimate-note">Estimated bill, not the actual bill.</p>
            <div class="ro-actions">
                <button class="ro-btn" x-show="o.status !== 'cancelled'" @click="recordPayment(o)">Record payment</button>
                <template x-for="s in nextStatuses(o.status)" :key="s">
                    <button class="ro-btn" @click="setStatus(o, s)" x-text="actionLabel(s)"></button>
                </template>
                <a class="ro-btn" :href="projectBase + '?source_type=restaurant_order&source_id=' + o.id">Create project</a>
            </div>
        </div>
    </template>

    {{-- Paging is a fetch, not a navigation: appending to a list somebody
         is reading should not throw away where they were. --}}
    <template x-if="more">
        <button class="ro-btn ro-more" type="button" @click="loadMore()" :disabled="loadingMore">
            <span x-text="loadingMore ? 'Loading…' : ('Load more (' + Math.max(0, meta.total - inRange().length) + ' older)')"></span>
        </button>
    </template>

</div>

<script>
@php
    $ordersData = $orders->map(fn($o)=>['id'=>$o->id,'status'=>$o->status,'table_label'=>$o->table_label,'customer_name'=>$o->customer_name,'token_number'=>$o->token_number,'customer_phone'=>$o->customer_phone,'wanted_at'=>$o->wanted_at?->toIso8601String(),'meta'=>$o->meta,'fulfilment'=>$o->fulfilment,'customer_note'=>$o->customer_note,'subtotal'=>$o->subtotal,'coupon_code'=>$o->coupon_code,'discount_amount'=>$o->discount_amount,'tax_rate'=>$o->tax_rate,'tax_inclusive'=>(bool)$o->tax_inclusive,'tax_amount'=>$o->tax_amount,'total'=>$o->total,'currency'=>$o->currency,'created_at'=>$o->created_at?->toIso8601String(),'updated_at'=>$o->updated_at?->toIso8601String(),'items'=>$o->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'quantity'=>$i->quantity,'line_total'=>$i->line_total])])->values();
@endphp
function ordersBoard() {
    return {
        orders: @json($ordersData),
        preorderView: @js($preorderView),
        // ---- Which window the screen is showing ---------------------
        meta: @json($rangeMeta),
        page: {{ $page }},
        more: @json($hasMore),
        loadingMore: false,

        emptyMessage(){
            if (this.filter === 'open' && this.inRange().length) {
                return 'Nothing open in this range. Tap All to see the rest.';
            }
            // Written server-side: gluing "in " onto a lowercased label
            // gives "No orders in yesterday", which is what this said the
            // first time it was looked at.
            return this.meta.empty;
        },

        rangeSummary(){
            const m = this.meta;
            if (!m.total) { return m.empty; }
            const shown = this.inRange().length;
            const n = m.total + (m.total === 1 ? ' order' : ' orders');
            return shown >= m.total
                ? n + ' ' + m.suffix
                : 'Showing ' + shown + ' of ' + n + ' ' + m.suffix;
        },

        /** Everything loaded that belongs to the window being shown. */
        inRange(){
            const m = this.meta;
            return this.orders.filter(o => {
                if (this.preorderView) return o.wanted_at && Date.parse(o.wanted_at) > Date.now() && this.OPEN.includes(o.status);
                if (!o.created_at) { return true; }
                const t = Date.parse(o.created_at);
                if (m.from_ms != null && t < m.from_ms) { return false; }
                if (m.to_ms != null && t > m.to_ms) { return false; }
                return true;
            });
        },

        async loadMore(){
            if (this.loadingMore || !this.more) { return; }
            this.loadingMore = true;
            try {
                const url = new URL(this.base, location.origin);
                url.searchParams.set('format', 'json');
                if (this.preorderView) url.searchParams.set('schedule', 'upcoming');
                url.searchParams.set('range', this.meta.key);
                if (this.meta.from_date) { url.searchParams.set('from', this.meta.from_date); }
                if (this.meta.to_date) { url.searchParams.set('to', this.meta.to_date); }
                url.searchParams.set('page', this.page + 1);
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) { return; }
                const j = await r.json();
                (j.data.orders || []).forEach(o => this.merge(o));
                this.page = j.data.page;
                this.more = j.data.more;
                this.meta.total = j.data.range.total;
            } catch (e) {
                // Nothing to say: the button simply stays, and tapping it
                // again is the retry.
            } finally {
                this.loadingMore = false;
            }
        },
        projectBase: @json(route('user.delivery-projects.create')),
        openCount: {{ $openCount }},
        highlight: {{ (int) request()->query('highlight') ?: 'null' }},
        filter: @json(request()->query('highlight') ? 'all' : 'open'),
        base: @json(route('user.links.restaurant.orders', $link)),
        statusUrlBase: @json(rtrim(route('user.links.restaurant.orders', $link), '/')),
        pollUrl: @json(route('user.links.restaurant.orders.poll', $link)),
        csrf: @json(csrf_token()),
        cursor: @json(now()->toIso8601String()),
        OPEN: ['new','accepted','preparing','ready'],
        LABELS: { new:'New', accepted:'Accepted', preparing:'Preparing', ready:'Ready', completed:'Completed', cancelled:'Cancelled' },
        init(){ this.poll(); setInterval(()=>this.poll(), 5000); this.scrollToHighlight(); },
        scrollToHighlight(){ if (!this.highlight) return; this.$nextTick(()=>{ const el = document.getElementById('order-' + this.highlight); if (el) el.scrollIntoView({ behavior:'smooth', block:'center' }); }); },
        exportHref(base, format) {
            const url = new URL(base, window.location.origin);
            url.searchParams.set('format', format);
            if (this.filter === 'open') url.searchParams.set('status', 'open');
            else url.searchParams.delete('status');
            return url.href;
        },
        visible(){ const o = this.inRange().slice().sort((a,b)=>this.preorderView ? Date.parse(a.wanted_at)-Date.parse(b.wanted_at) : b.id-a.id); return this.filter==='open' ? o.filter(x=>this.OPEN.includes(x.status)) : o; },
        /**
         * When the customer asked for it. Blank means as soon as possible,
         * which is most orders, so it says nothing rather than saying "ASAP"
         * on every single card.
         */
        wantedLabel(iso){
            if (!iso) { return ''; }
            const at = new Date(iso);
            const now = new Date();
            const t = at.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
            if (at.toDateString() === now.toDateString()) { return t; }
            const d = at.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
            return d + ' ' + t;
        },
        statusLabel(s){ return this.LABELS[s] || s; },
        nextStatuses(s){
            const flow = { new:['accepted','cancelled'], accepted:['preparing','cancelled'], preparing:['ready'], ready:['completed'], completed:[], cancelled:[] };
            return flow[s] || [];
        },
        actionLabel(s){ return { accepted:'Accept', preparing:'Start preparing', ready:'Mark ready', completed:'Complete', cancelled:'Cancel' }[s] || s; },
        money(n, cur){ return (cur||'USD') + ' ' + (+n).toFixed(2); },
        timeAgo(iso){ if(!iso) return ''; const s = Math.floor((Date.now()-new Date(iso))/1000); if(s<60) return s+'s ago'; if(s<3600) return Math.floor(s/60)+'m ago'; return Math.floor(s/3600)+'h ago'; },
        async recordPayment(o){
            const entered = window.prompt('Total amount collected so far (replace previous amount)', o.meta?.collected_amount ?? '0');
            if (entered === null || entered.trim() === '') return;
            const amount = Number(entered);
            if (!Number.isFinite(amount) || amount < 0) { window.alert('Enter a valid amount.'); return; }
            try {
                const r = await fetch(this.statusUrlBase + '/' + o.id + '/status', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'}, body:JSON.stringify({status:o.status, collected_amount:amount})});
                const j = await r.json();
                if (r.ok) this.merge(j.data.order); else window.alert(j.message || 'Payment could not be saved.');
            } catch(e) { window.alert('Payment could not be saved.'); }
        },
        async setStatus(o, s){
            try {
                const r = await fetch(this.statusUrlBase + '/' + o.id + '/status', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ status:s, ...(s === 'cancelled' ? { cancellation_reason: window.prompt('Reason for cancellation (optional)') || '' } : {}) }) });
                const j = await r.json();
                if (r.ok) this.merge(j.data.order);
            } catch(e){}
        },
        async poll(){
            try {
                const r = await fetch(this.pollUrl + '?since=' + encodeURIComponent(this.cursor));
                if (!r.ok) return;
                const j = await r.json();
                this.cursor = j.data.server_time;
                this.openCount = j.data.open_count;
                // An order that falls outside the window being viewed is
                // still merged when it is ALREADY on screen -- a status
                // change on a visible order has to land -- but a brand new
                // one is not dragged into a view of last Tuesday.
                (j.data.orders || []).forEach(o => {
                    if (this.known(o.id) || this.fits(o)) { this.merge(o); }
                });
            } catch(e){}
        },
        known(id){ return this.orders.some(x => x.id === id); },
        fits(o){
            if (this.preorderView) return o.wanted_at && Date.parse(o.wanted_at) > Date.now() && this.OPEN.includes(o.status);
            const m = this.meta;
            if (!o.created_at) { return true; }
            const t = Date.parse(o.created_at);
            if (m.from_ms != null && t < m.from_ms) { return false; }
            if (m.to_ms != null && t > m.to_ms) { return false; }
            return true;
        },
        merge(o){
            const i = this.orders.findIndex(x => x.id === o.id);
            const norm = { id:o.id, status:o.status, table_label:o.table_label, customer_name:o.customer_name, token_number:o.token_number, customer_phone:o.customer_phone, wanted_at:o.wanted_at, meta:o.meta, fulfilment:o.fulfilment, customer_note:o.customer_note, subtotal:o.subtotal, coupon_code:o.coupon_code, discount_amount:o.discount_amount, tax_rate:o.tax_rate, tax_inclusive:o.tax_inclusive, tax_amount:o.tax_amount, total:o.total, currency:o.currency, created_at:o.created_at, updated_at:o.updated_at, items:(o.items||[]).map(it=>({id:it.id,name:it.name,quantity:it.quantity,line_total:it.line_total})) };
            if (i >= 0) this.orders[i] = norm; else this.orders.unshift(norm);
        },
    };
}
</script>
@endsection
