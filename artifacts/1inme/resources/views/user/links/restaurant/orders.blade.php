@extends('user.layouts.app')
@section('title', 'Orders - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .ro-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:18px; margin-bottom:14px; }
    .ro-head { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .ro-table { font-weight:700; font-size:16px; color:var(--text-primary); }
    .ro-meta { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .ro-line { display:flex; justify-content:space-between; font-size:13.5px; padding:4px 0; color:var(--text-primary); }
    .ro-total { display:flex; justify-content:space-between; font-weight:700; margin-top:8px; padding-top:8px; border-top:1px dashed var(--border-glass); color:var(--text-primary); }
    .ro-status { padding:4px 11px; border-radius:999px; font-size:12px; font-weight:700; color:#fff; }
    .st-new{background:#ef4444}.st-accepted{background:#f59e0b}.st-preparing{background:#3b82f6}.st-ready{background:#10b981}.st-completed{background:#6b7280}.st-cancelled{background:#9ca3af}
    .ro-actions { display:flex; flex-wrap:wrap; gap:6px; margin-top:12px; }
    .ro-btn { padding:7px 13px; border-radius:999px; font-size:12.5px; font-weight:600; border:1px solid var(--border-glass); background:transparent; color:var(--text-muted); cursor:pointer; }
    .ro-btn.active { background:linear-gradient(135deg,#8b5cf6,#6366f1); color:#fff; border:0; }
    .ro-note { font-size:12.5px; color:var(--text-muted); margin-top:6px; font-style:italic; }
    .ro-empty { text-align:center; padding:50px 0; color:var(--text-muted); }
    .ro-live { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
    .ro-dot { width:8px; height:8px; border-radius:50%; background:#10b981; animation:ropulse 1.6s infinite; }
    @keyframes ropulse { 0%,100%{opacity:1}50%{opacity:.3} }
</style>

<div class="max-w-4xl mx-auto" x-data="ordersBoard()" x-init="init()">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary)">Orders</h1>
            <p class="text-sm" style="color:var(--text-muted)">{{ $link->title ?: $link->alias }} · <span class="ro-live"><span class="ro-dot"></span>Live</span></p>
        </div>
        <a href="{{ route('user.links.restaurant.editor', $link) }}" class="ro-btn"><i class="fas fa-arrow-left"></i> Back to menu</a>
    </div>

    <div class="flex gap-2 mb-4 flex-wrap">
        <button class="ro-btn" :class="filter==='open' ? 'active' : ''" @click="filter='open'">Open (<span x-text="openCount"></span>)</button>
        <button class="ro-btn" :class="filter==='all' ? 'active' : ''" @click="filter='all'">All</button>
    </div>

    <template x-if="visible().length === 0">
        <div class="ro-empty"><i class="fas fa-receipt text-3xl mb-3 block"></i>No orders yet. New orders appear here automatically.</div>
    </template>

    <template x-for="o in visible()" :key="o.id">
        <div class="ro-card">
            <div class="ro-head">
                <div>
                    <div class="ro-table" x-text="o.table_label ? ('Table ' + o.table_label) : 'Walk-in'"></div>
                    <div class="ro-meta">
                        <span x-show="o.customer_name" x-text="o.customer_name + ' · '"></span>
                        <span x-text="timeAgo(o.created_at)"></span> · #<span x-text="o.id"></span>
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
            <div class="ro-total"><span>Total</span><span x-text="money(o.subtotal, o.currency)"></span></div>
            <div class="ro-actions">
                <template x-for="s in nextStatuses(o.status)" :key="s">
                    <button class="ro-btn" @click="setStatus(o, s)" x-text="actionLabel(s)"></button>
                </template>
            </div>
        </div>
    </template>
</div>

<script>
function ordersBoard() {
    return {
        orders: @json($orders->map(fn($o)=>['id'=>$o->id,'status'=>$o->status,'table_label'=>$o->table_label,'customer_name'=>$o->customer_name,'customer_note'=>$o->customer_note,'subtotal'=>$o->subtotal,'currency'=>$o->currency,'created_at'=>$o->created_at?->toIso8601String(),'updated_at'=>$o->updated_at?->toIso8601String(),'items'=>$o->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'quantity'=>$i->quantity,'line_total'=>$i->line_total])])->values()),
        openCount: {{ $orders->whereIn('status', \App\Modules\User\Models\RestaurantOrder::OPEN_STATUSES)->count() }},
        filter: 'open',
        base: @json(route('user.links.restaurant.orders', $link)),
        statusUrlBase: @json(rtrim(route('user.links.restaurant.orders', $link), '/')),
        pollUrl: @json(route('user.links.restaurant.orders.poll', $link)),
        csrf: @json(csrf_token()),
        cursor: @json(now()->toIso8601String()),
        OPEN: ['new','accepted','preparing','ready'],
        LABELS: { new:'New', accepted:'Accepted', preparing:'Preparing', ready:'Ready', completed:'Completed', cancelled:'Cancelled' },
        init(){ this.poll(); setInterval(()=>this.poll(), 5000); },
        visible(){ const o = this.orders.slice().sort((a,b)=>b.id-a.id); return this.filter==='open' ? o.filter(x=>this.OPEN.includes(x.status)) : o; },
        statusLabel(s){ return this.LABELS[s] || s; },
        nextStatuses(s){
            const flow = { new:['accepted','cancelled'], accepted:['preparing','cancelled'], preparing:['ready'], ready:['completed'], completed:[], cancelled:[] };
            return flow[s] || [];
        },
        actionLabel(s){ return { accepted:'Accept', preparing:'Start preparing', ready:'Mark ready', completed:'Complete', cancelled:'Cancel' }[s] || s; },
        money(n, cur){ return (cur||'USD') + ' ' + (+n).toFixed(2); },
        timeAgo(iso){ if(!iso) return ''; const s = Math.floor((Date.now()-new Date(iso))/1000); if(s<60) return s+'s ago'; if(s<3600) return Math.floor(s/60)+'m ago'; return Math.floor(s/3600)+'h ago'; },
        async setStatus(o, s){
            try {
                const r = await fetch(this.statusUrlBase + '/' + o.id + '/status', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify({ status:s }) });
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
                (j.data.orders || []).forEach(o => this.merge(o));
            } catch(e){}
        },
        merge(o){
            const i = this.orders.findIndex(x => x.id === o.id);
            const norm = { id:o.id, status:o.status, table_label:o.table_label, customer_name:o.customer_name, customer_note:o.customer_note, subtotal:o.subtotal, currency:o.currency, created_at:o.created_at, updated_at:o.updated_at, items:(o.items||[]).map(it=>({id:it.id,name:it.name,quantity:it.quantity,line_total:it.line_total})) };
            if (i >= 0) this.orders[i] = norm; else this.orders.unshift(norm);
        },
    };
}
</script>
@endsection
