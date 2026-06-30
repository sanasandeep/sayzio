@extends('user.layouts.app')
@section('title', 'Bookings - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .sb-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:18px; margin-bottom:14px; }
    .sb-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .sb-when { font-weight:700; font-size:16px; color:var(--text-primary); }
    .sb-meta { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .sb-line { display:flex; justify-content:space-between; font-size:13.5px; padding:4px 0; color:var(--text-primary); }
    .sb-breakdown { margin-top:8px; padding-top:8px; border-top:1px dashed var(--border-glass); }
    .sb-bline { display:flex; justify-content:space-between; font-size:12.5px; padding:2px 0; color:var(--text-muted); }
    .sb-total { display:flex; justify-content:space-between; font-weight:700; margin-top:6px; padding-top:6px; border-top:1px dashed var(--border-glass); color:var(--text-primary); }
    .sb-estimate-note { font-size:11.5px; color:var(--text-muted); font-style:italic; margin-top:4px; }
    .sb-status { padding:4px 11px; border-radius:999px; font-size:12px; font-weight:700; color:#fff; }
    .st-pending{background:#f59e0b}.st-confirmed{background:#3b82f6}.st-completed{background:#10b981}.st-cancelled{background:#9ca3af}.st-declined{background:#ef4444}
    .sb-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
    .sb-btn { padding:7px 13px; border-radius:999px; font-size:12.5px; font-weight:600; border:1px solid var(--border-glass); background:transparent; color:var(--text-muted); cursor:pointer; }
    .sb-btn.active { background:linear-gradient(135deg,#5c83ff,#6366f1); color:#fff; border:0; }
    .sb-note { font-size:12.5px; color:var(--text-muted); margin-top:6px; font-style:italic; }
    .sb-empty { text-align:center; padding:50px 0; color:var(--text-muted); }
    .sb-live { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
    .sb-dot { width:8px; height:8px; border-radius:50%; background:#10b981; animation:sbpulse 1.6s infinite; }
    @keyframes sbpulse { 0%,100%{opacity:1}50%{opacity:.3} }
    .sb-contact { font-size:12.5px; color:var(--text-muted); margin-top:6px; }
    .sb-contact a { color:#5c83ff; }
</style>

<div class="max-w-4xl mx-auto" x-data="bookingsBoard()" x-init="init()">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary)">Bookings</h1>
            <p class="text-sm" style="color:var(--text-muted)">{{ $link->title ?: $link->alias }} · <span class="sb-live"><span class="sb-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('user.links.service-booking.editor', $link) }}" class="sb-btn"><i class="fas fa-arrow-left"></i> Back to services</a>
    </div>

    <div class="flex gap-2 mb-4 flex-wrap">
        <button class="sb-btn" :class="filter==='open' ? 'active' : ''" @click="filter='open'">Open (<span x-text="openCount"></span>)</button>
        <button class="sb-btn" :class="filter==='all' ? 'active' : ''" @click="filter='all'">All</button>
    </div>

    <template x-if="visible().length === 0">
        <div class="sb-empty"><i class="fas fa-calendar-check text-3xl mb-3 block"></i>No bookings yet. New requests appear here automatically.</div>
    </template>

    <template x-for="b in visible()" :key="b.id">
        <div class="sb-card">
            <div class="sb-head">
                <div>
                    <div class="sb-when" x-text="slotLabel(b.slot_start)"></div>
                    <div class="sb-meta">
                        <span x-show="b.customer_name" x-text="b.customer_name + ' · '"></span>
                        <span x-text="b.duration_minutes + ' min'"></span> · #<span x-text="b.id"></span>
                    </div>
                </div>
                <span class="sb-status" :class="'st-' + b.status" x-text="statusLabel(b.status)"></span>
            </div>
            <div style="margin-top:10px">
                <template x-for="it in b.items" :key="it.id">
                    <div class="sb-line"><span x-text="(it.quantity>1 ? it.quantity + '× ' : '') + it.name + ' (' + it.duration_minutes + ' min)'"></span><span x-text="money(it.line_total, b.currency)"></span></div>
                </template>
            </div>
            <div class="sb-contact">
                <span x-show="b.customer_email"><i class="fas fa-envelope"></i> <a :href="'mailto:'+b.customer_email" x-text="b.customer_email"></a></span>
                <span x-show="b.customer_phone" style="margin-left:10px"><i class="fas fa-phone"></i> <a :href="'tel:'+b.customer_phone" x-text="b.customer_phone"></a></span>
            </div>
            <div class="sb-note" x-show="b.customer_note" x-text="'“' + b.customer_note + '”'"></div>
            <div class="sb-breakdown">
                <div class="sb-bline"><span>Subtotal</span><span x-text="money(b.subtotal, b.currency)"></span></div>
                <div class="sb-bline" x-show="+b.tax_amount > 0">
                    <span x-text="'Tax (' + (+b.tax_rate) + '%)' + (b.tax_inclusive ? ' incl.' : '')"></span>
                    <span x-text="money(b.tax_amount, b.currency)"></span>
                </div>
            </div>
            <div class="sb-total"><span>Estimated total</span><span x-text="money(b.total != null ? b.total : b.subtotal, b.currency)"></span></div>
            <p class="sb-estimate-note">Estimated price, not the final bill. No payment is collected here.</p>
            <div class="sb-actions">
                <template x-for="s in nextStatuses(b.status)" :key="s">
                    <button class="sb-btn" @click="setStatus(b, s)" x-text="actionLabel(s)"></button>
                </template>
            </div>
        </div>
    </template>
</div>

<script>
@php
    $bookingsData = $bookings->map(fn($b)=>['id'=>$b->id,'status'=>$b->status,'customer_name'=>$b->customer_name,'customer_email'=>$b->customer_email,'customer_phone'=>$b->customer_phone,'customer_note'=>$b->customer_note,'slot_start'=>$b->slot_start?->toIso8601String(),'slot_end'=>$b->slot_end?->toIso8601String(),'duration_minutes'=>$b->duration_minutes,'subtotal'=>$b->subtotal,'tax_rate'=>$b->tax_rate,'tax_inclusive'=>(bool)$b->tax_inclusive,'tax_amount'=>$b->tax_amount,'total'=>$b->total,'currency'=>$b->currency,'created_at'=>$b->created_at?->toIso8601String(),'updated_at'=>$b->updated_at?->toIso8601String(),'items'=>$b->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'quantity'=>$i->quantity,'duration_minutes'=>$i->duration_minutes,'line_total'=>$i->line_total])])->values();
@endphp
function bookingsBoard() {
    return {
        bookings: @json($bookingsData),
        openCount: {{ $bookings->whereIn('status', \App\Modules\User\Models\ServiceBookingRequest::OPEN_STATUSES)->count() }},
        filter: 'open',
        statusUrlBase: @json(rtrim(route('user.links.service-booking.bookings', $link), '/')),
        pollUrl: @json(route('user.links.service-booking.bookings.poll', $link)),
        csrf: @json(csrf_token()),
        cursor: @json(now()->toIso8601String()),
        OPEN: ['pending','confirmed'],
        LABELS: { pending:'Pending', confirmed:'Confirmed', completed:'Completed', cancelled:'Cancelled', declined:'Declined' },
        init(){ this.poll(); setInterval(()=>this.poll(), 5000); },
        visible(){ const b = this.bookings.slice().sort((a,b)=>b.id-a.id); return this.filter==='open' ? b.filter(x=>this.OPEN.includes(x.status)) : b; },
        statusLabel(s){ return this.LABELS[s] || s; },
        nextStatuses(s){
            const flow = { pending:['confirmed','declined','cancelled'], confirmed:['completed','cancelled'], completed:[], cancelled:[], declined:[] };
            return flow[s] || [];
        },
        actionLabel(s){ return { confirmed:'Confirm', declined:'Decline', completed:'Mark complete', cancelled:'Cancel' }[s] || s; },
        money(n, cur){ return (cur||'USD') + ' ' + (+n).toFixed(2); },
        slotLabel(iso){ if(!iso) return ''; try { return new Date(iso).toLocaleString([], {weekday:'short', month:'short', day:'numeric', hour:'numeric', minute:'2-digit'}); } catch(e){ return iso; } },
        async setStatus(b, s){
            try {
                const r = await fetch(this.statusUrlBase + '/' + b.id + '/status', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ status:s }) });
                const j = await r.json();
                if (r.ok) this.merge(j.data.booking); else alert((j.error && j.error.message) || 'Failed');
            } catch(e){}
        },
        async poll(){
            try {
                const r = await fetch(this.pollUrl + '?since=' + encodeURIComponent(this.cursor));
                if (!r.ok) return;
                const j = await r.json();
                this.cursor = j.data.server_time;
                this.openCount = j.data.open_count;
                (j.data.bookings || []).forEach(b => this.merge(b));
            } catch(e){}
        },
        merge(b){
            const i = this.bookings.findIndex(x => x.id === b.id);
            const norm = { id:b.id, status:b.status, customer_name:b.customer_name, customer_email:b.customer_email, customer_phone:b.customer_phone, customer_note:b.customer_note, slot_start:b.slot_start, slot_end:b.slot_end, duration_minutes:b.duration_minutes, subtotal:b.subtotal, tax_rate:b.tax_rate, tax_inclusive:b.tax_inclusive, tax_amount:b.tax_amount, total:b.total, currency:b.currency, created_at:b.created_at, updated_at:b.updated_at, items:(b.items||[]).map(it=>({id:it.id,name:it.name,quantity:it.quantity,duration_minutes:it.duration_minutes,line_total:it.line_total})) };
            if (i >= 0) this.bookings[i] = norm; else this.bookings.unshift(norm);
        },
    };
}
</script>
@endsection
