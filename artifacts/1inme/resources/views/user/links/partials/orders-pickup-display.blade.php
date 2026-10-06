@include('user.links.partials.menu-fullscreen')
<style>
.pickup-panel{border:1px solid var(--border-glass);border-radius:18px;background:var(--bg-card);padding:20px;margin-bottom:18px;color:var(--text-primary)}
.pickup-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.pickup-columns{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:18px}.pickup-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px}
.pickup-ticket{padding:18px;text-align:center;border-radius:14px;color:#fff}.pickup-number{font-size:42px;font-weight:800;line-height:1.15}.pickup-status{font-size:14px;font-weight:600}.pickup-new{background:#b91c1c}.pickup-accepted{background:#92400e}.pickup-preparing{background:#1d4ed8}.pickup-ready{background:#047857}
@media(max-width:640px){.pickup-columns{grid-template-columns:1fr}}
</style>
<section class="pickup-panel" data-screen-panel x-data="pickupDisplay(@js($pickupUrl))" x-init="start()">
    <div class="pickup-head"><div><h2 class="font-bold text-lg">Order status display</h2><p class="text-sm">{{ $link->title ?: $link->alias }}</p></div>
        <button type="button" class="btn-ghost" onclick="menuFullscreen(this)">Fullscreen / Exit</button>
    </div>
    <p class="text-sm mt-2" role="status" x-text="error || (updated ? 'Updated ' + updated : 'Loading orders…')"></p>
    <div class="pickup-columns">
        <div><h3 class="font-bold mb-3">Ready to collect</h3><div class="pickup-grid">
            <template x-for="order in list(true)" :key="order.id"><div class="pickup-ticket pickup-ready"><div class="pickup-number" x-text="order.ref"></div><div class="pickup-status">Ready to collect</div></div></template>
        </div><p x-show="!list(true).length">No orders ready yet.</p></div>
        <div><h3 class="font-bold mb-3">In progress</h3><div class="pickup-grid">
            <template x-for="order in list(false)" :key="order.id"><div class="pickup-ticket" :class="'pickup-' + order.status"><div class="pickup-number" x-text="order.ref"></div><div class="pickup-status" x-text="labels[order.status]"></div></div></template>
        </div><p x-show="!list(false).length">No orders in progress.</p></div>
    </div>
</section>
<script>
function pickupDisplay(url) {
    return { orders:[], updated:'', error:'', busy:false, timer:null,
        labels:{new:'New',accepted:'Accepted',preparing:'Preparing'},
        list(ready){ return this.orders.filter(function (order) { return ready ? order.status === 'ready' : ['new','accepted','preparing'].includes(order.status); }).sort(function(a,b){return a.id-b.id}); },
        start(){this.refresh();this.timer=setInterval(()=>this.refresh(),5000);},
        destroy(){clearInterval(this.timer);},
        async refresh(){
            if(this.busy)return;this.busy=true;
            try{var response=await fetch(url,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)throw new Error();var payload=await response.json();this.orders=payload.data.orders||[];this.updated=new Date().toLocaleTimeString();this.error='';}
            catch(error){this.error='Updates paused. Display may be out of date; reconnecting…';}
            finally{this.busy=false;}
        }
    };
}
</script>
