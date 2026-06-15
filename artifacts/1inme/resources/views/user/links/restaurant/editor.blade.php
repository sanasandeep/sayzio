@extends('user.layouts.app')
@section('title', 'Restaurant Menu - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))
@section('content')
<style>
    .rm-grid { display:grid; grid-template-columns: minmax(0,1fr) 320px; gap:20px; align-items:start; }
    @media (max-width:1100px){ .rm-grid { grid-template-columns: minmax(0,1fr); } }
    .rm-card { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:20px; margin-bottom:16px; backdrop-filter:blur(20px); }
    .rm-card h5 { color:var(--text-primary); font-weight:700; margin:0 0 14px; font-size:15px; display:flex; justify-content:space-between; align-items:center; }
    .rm-label { display:block; font-size:12px; font-weight:600; color:var(--text-muted); margin-bottom:6px; }
    .rm-input, .rm-select, .rm-textarea { width:100%; border:1px solid var(--border-glass); border-radius:.75rem; background:var(--bg-glass-input); color:var(--text-primary); padding:10px 12px; font-size:14px; outline:none; }
    .rm-textarea { resize:vertical; min-height:60px; }
    .rm-row { margin-bottom:14px; }
    .rm-btn { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border:0; border-radius:999px; font-weight:600; font-size:13.5px; color:#fff; cursor:pointer; background:linear-gradient(135deg,#8b5cf6,#6366f1); }
    .rm-btn.sm { padding:6px 12px; font-size:12.5px; }
    .rm-btn.ghost { background:transparent; color:var(--text-muted); border:1px solid var(--border-glass); }
    .rm-btn.danger { background:transparent; color:#ef4444; border:1px solid rgba(239,68,68,.4); }
    .rm-item { display:flex; gap:12px; align-items:flex-start; padding:12px; border:1px solid var(--border-glass); border-radius:.85rem; margin-bottom:10px; background:var(--bg-glass-input); }
    .rm-item .meta { flex:1; min-width:0; }
    .rm-item .nm { font-weight:650; color:var(--text-primary); font-size:14.5px; }
    .rm-item .ds { font-size:12.5px; color:var(--text-muted); margin-top:2px; }
    .rm-item .pr { font-size:13px; color:#8b5cf6; font-weight:700; margin-top:4px; }
    .rm-cat { border:1px solid var(--border-glass); border-radius:1rem; padding:16px; margin-bottom:16px; }
    .rm-cat-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .rm-cat-head .ct { font-weight:700; color:var(--text-primary); font-size:16px; }
    .rm-pill { font-size:11px; padding:3px 9px; border-radius:999px; background:rgba(239,68,68,.15); color:#ef4444; font-weight:600; }
    .rm-table-row { display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px dashed var(--border-glass); }
    .rm-mode-toggle { display:flex; gap:8px; }
    .rm-mode-toggle label { flex:1; text-align:center; padding:10px; border:1px solid var(--border-glass); border-radius:.75rem; cursor:pointer; font-size:13px; font-weight:600; color:var(--text-muted); }
    .rm-mode-toggle input { display:none; }
    .rm-mode-toggle input:checked + span { color:#8b5cf6; }
    .rm-modal-bg { position:fixed; inset:0; background:rgba(0,0,0,.5); display:flex; align-items:center; justify-content:center; z-index:60; padding:16px; }
    .rm-modal { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:1rem; padding:22px; width:100%; max-width:480px; max-height:90vh; overflow:auto; }
</style>

<div class="max-w-7xl mx-auto" x-data="restaurantEditor()" x-init="init()">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary)">{{ $link->title ?: $link->alias }}</h1>
            <p class="text-sm" style="color:var(--text-muted)">Restaurant Menu · /{{ $link->alias }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('user.links.restaurant.orders', $link) }}" class="rm-btn ghost">
                <i class="fas fa-receipt"></i> Orders @if($openOrders > 0)<span class="rm-pill">{{ $openOrders }}</span>@endif
            </a>
            <a href="{{ url('/'.$link->alias) }}" target="_blank" class="rm-btn ghost"><i class="fas fa-external-link-alt"></i> View</a>
        </div>
    </div>

    <div class="rm-grid">
        <div>
            <!-- Categories + items -->
            <div class="flex justify-between items-center mb-3">
                <h2 class="font-bold text-lg" style="color:var(--text-primary)">Menu</h2>
                <button class="rm-btn sm" @click="openCategory()"><i class="fas fa-plus"></i> Category</button>
            </div>

            <template x-if="categories.length === 0">
                <div class="rm-card" style="text-align:center;color:var(--text-muted)">
                    No categories yet. Add one to start building your menu.
                </div>
            </template>

            <template x-for="(cat, ci) in categories" :key="cat.id">
                <div class="rm-cat">
                    <div class="rm-cat-head">
                        <div class="ct" x-text="cat.name"></div>
                        <div class="flex gap-2">
                            <button class="rm-btn sm ghost" title="Move up" :disabled="ci===0" @click="moveCategory(cat,-1)"><i class="fas fa-arrow-up"></i></button>
                            <button class="rm-btn sm ghost" title="Move down" :disabled="ci===categories.length-1" @click="moveCategory(cat,1)"><i class="fas fa-arrow-down"></i></button>
                            <button class="rm-btn sm" @click="openItem(cat.id)"><i class="fas fa-plus"></i> Item</button>
                            <button class="rm-btn sm ghost" @click="openCategory(cat)"><i class="fas fa-pen"></i></button>
                            <button class="rm-btn sm danger" @click="deleteCategory(cat)"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <template x-for="(item, ii) in itemsFor(cat.id)" :key="item.id">
                        <div class="rm-item" :style="item.is_sold_out ? 'opacity:.55' : ''">
                            <img x-show="item.photo_url" :src="item.photo_url" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;">
                            <div class="meta">
                                <div class="nm" x-text="item.name"></div>
                                <div class="ds" x-show="item.description" x-text="item.description"></div>
                                <div class="pr"><span x-text="menu.currency"></span> <span x-text="(+item.price).toFixed(2)"></span>
                                    <span x-show="item.is_sold_out" class="rm-pill" style="margin-left:6px">Sold out</span></div>
                            </div>
                            <div class="flex flex-col gap-1">
                                <button class="rm-btn sm ghost" title="Move up" :disabled="ii===0" @click="moveItem(cat.id,item,-1)"><i class="fas fa-arrow-up"></i></button>
                                <button class="rm-btn sm ghost" title="Move down" :disabled="ii===itemsFor(cat.id).length-1" @click="moveItem(cat.id,item,1)"><i class="fas fa-arrow-down"></i></button>
                                <button class="rm-btn sm ghost" @click="openItem(cat.id, item)"><i class="fas fa-pen"></i></button>
                                <button class="rm-btn sm danger" @click="deleteItem(item)"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    </template>
                    <template x-if="itemsFor(cat.id).length === 0">
                        <p class="text-sm" style="color:var(--text-muted)">No items in this category yet.</p>
                    </template>
                </div>
            </template>
        </div>

        <!-- Settings + tables -->
        <div>
            <div class="rm-card">
                <h5>Settings</h5>
                <div class="rm-row">
                    <label class="rm-label">Mode</label>
                    <div class="rm-mode-toggle">
                        <label><input type="radio" value="display" x-model="menu.mode" @change="saveSettings()"><span>Display only</span></label>
                        <label><input type="radio" value="order" x-model="menu.mode" @change="saveSettings()"><span>Order at table</span></label>
                    </div>
                    <p class="text-xs mt-2" style="color:var(--text-muted)">Order mode lets guests build a cart and send orders to you. No online payment — guests pay your staff.</p>
                </div>
                <div class="rm-row">
                    <label class="rm-label">Currency</label>
                    <input class="rm-input" x-model="menu.currency" maxlength="3" @change="saveSettings()" style="text-transform:uppercase">
                </div>
                <div class="rm-row">
                    <label class="rm-label">Accent color</label>
                    <input type="color" class="rm-input" x-model="menu.accent_color" @change="saveSettings()" style="height:42px;padding:4px">
                </div>
                <p class="text-xs" style="color:var(--text-faint)" x-text="savedMsg"></p>
            </div>

            <div class="rm-card" x-show="menu.mode === 'order'">
                <h5 style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                    <span>Tables <button class="rm-btn sm" @click="addTable()"><i class="fas fa-plus"></i></button></span>
                    <a class="rm-btn sm ghost" x-show="tables.length > 0" :href="base + '/tables-qr-sheet'" target="_blank"><i class="fas fa-print"></i> Print all</a>
                </h5>
                <p class="text-xs mb-3" style="color:var(--text-muted)">Each table gets a printable QR that opens this menu pre-tagged to the table.</p>
                <template x-for="t in tables" :key="t.id">
                    <div class="rm-table-row">
                        <span style="color:var(--text-primary)" x-text="t.label"></span>
                        <span class="flex gap-2">
                            <a class="rm-btn sm ghost" :href="qrUrl(t.id)" target="_blank"><i class="fas fa-qrcode"></i> QR</a>
                            <button class="rm-btn sm danger" @click="deleteTable(t)"><i class="fas fa-trash"></i></button>
                        </span>
                    </div>
                </template>
                <template x-if="tables.length === 0">
                    <p class="text-sm" style="color:var(--text-muted)">No tables yet.</p>
                </template>
            </div>
        </div>
    </div>

    <!-- Category modal -->
    <div class="rm-modal-bg" x-show="catModal.open" x-cloak @click.self="catModal.open=false">
        <div class="rm-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px" x-text="catModal.id ? 'Edit category' : 'New category'"></h5>
            <div class="rm-row"><label class="rm-label">Name</label><input class="rm-input" x-model="catModal.name"></div>
            <div class="rm-row"><label class="rm-label">Description</label><textarea class="rm-textarea" x-model="catModal.description"></textarea></div>
            <div class="flex justify-end gap-2">
                <button class="rm-btn ghost" @click="catModal.open=false">Cancel</button>
                <button class="rm-btn" @click="saveCategory()">Save</button>
            </div>
        </div>
    </div>

    <!-- Item modal -->
    <div class="rm-modal-bg" x-show="itemModal.open" x-cloak @click.self="itemModal.open=false">
        <div class="rm-modal">
            <h5 style="color:var(--text-primary);font-weight:700;margin-bottom:14px" x-text="itemModal.id ? 'Edit item' : 'New item'"></h5>
            <div class="rm-row"><label class="rm-label">Name</label><input class="rm-input" x-model="itemModal.name"></div>
            <div class="rm-row"><label class="rm-label">Description</label><textarea class="rm-textarea" x-model="itemModal.description"></textarea></div>
            <div class="rm-row"><label class="rm-label">Price</label><input class="rm-input" type="number" step="0.01" min="0" x-model="itemModal.price"></div>
            <div class="rm-row">
                <label class="rm-label">Photo</label>
                <input class="rm-input" x-model="itemModal.photo_url" placeholder="https://… or upload below">
                <div style="display:flex;gap:8px;align-items:center;margin-top:8px">
                    <button type="button" class="rm-btn sm ghost" @click="$refs.photoInput.click()" :disabled="photoUploading">
                        <i class="fas" :class="photoUploading ? 'fa-spinner fa-spin' : 'fa-cloud-upload-alt'"></i>
                        <span x-text="photoUploading ? ('Uploading… ' + photoProgress + '%') : 'Upload photo'"></span>
                    </button>
                    <button type="button" class="rm-btn sm ghost" x-show="itemModal.photo_url" @click="itemModal.photo_url=''">Remove</button>
                    <input type="file" x-ref="photoInput" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="hidden" @change="uploadPhoto($event)">
                </div>
                <p class="text-xs" style="color:var(--accent-danger,#f87171)" x-show="photoError" x-text="photoError"></p>
                <template x-if="itemModal.photo_url">
                    <img :src="itemModal.photo_url" alt="Preview" style="margin-top:8px;max-height:120px;border-radius:10px;object-fit:contain" x-on:error="$el.style.display='none'" x-on:load="$el.style.display='block'">
                </template>
            </div>
            <div class="rm-row"><label style="display:flex;gap:8px;align-items:center;color:var(--text-primary)"><input type="checkbox" x-model="itemModal.is_sold_out"> Sold out</label></div>
            <div class="flex justify-end gap-2">
                <button class="rm-btn ghost" @click="itemModal.open=false">Cancel</button>
                <button class="rm-btn" @click="saveItem()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
function restaurantEditor() {
    return {
        menu: @json(['mode' => $menu->mode, 'currency' => $menu->currency, 'accent_color' => $menu->accent_color]),
        categories: @json($menu->categories->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'description'=>$c->description])->values()),
        items: @json($menu->items->map(fn($i)=>['id'=>$i->id,'category_id'=>$i->category_id,'name'=>$i->name,'description'=>$i->description,'price'=>$i->price,'photo_url'=>$i->photo_url,'is_sold_out'=>$i->is_sold_out])->values()),
        tables: @json($menu->tables->map(fn($t)=>['id'=>$t->id,'label'=>$t->label,'code'=>$t->code])->values()),
        savedMsg: '',
        catModal: { open:false, id:null, name:'', description:'' },
        itemModal: { open:false, id:null, category_id:null, name:'', description:'', price:'', photo_url:'', is_sold_out:false },
        base: @json(rtrim(url('/user/links/'.$link->id.'/restaurant'), '/')),
        uploadUrl: @json(route('user.files.upload')),
        csrf: @json(csrf_token()),
        photoUploading: false,
        photoProgress: 0,
        photoError: '',
        init(){},
        uploadPhoto(e){
            const file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file) return;
            this.photoUploading = true; this.photoProgress = 0; this.photoError = '';
            const fd = new FormData(); fd.append('file', file);
            const xhr = new XMLHttpRequest(); const self = this;
            xhr.upload.addEventListener('progress', function(ev){ if (ev.lengthComputable) self.photoProgress = Math.round((ev.loaded/ev.total)*100); });
            xhr.addEventListener('load', function(){
                self.photoUploading = false;
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300 && data.success && data.file) { self.itemModal.photo_url = data.file.url; }
                    else { self.photoError = (data.error && data.error.message) || (typeof data.error === 'string' ? data.error : '') || data.message || ('Upload failed (' + xhr.status + ')'); }
                } catch (err) { self.photoError = 'Upload failed (' + xhr.status + ')'; }
            });
            xhr.addEventListener('error', function(){ self.photoUploading = false; self.photoError = 'Network error'; });
            xhr.open('POST', this.uploadUrl);
            xhr.setRequestHeader('X-CSRF-TOKEN', this.csrf);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(fd);
        },
        itemsFor(catId){ return this.items.filter(i => i.category_id === catId); },
        qrUrl(tableId){ return this.base + '/tables/' + tableId + '/qr'; },
        async api(method, path, body){
            const r = await fetch(this.base + path, {
                method, headers:{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf,'X-Requested-With':'XMLHttpRequest'},
                body: body ? JSON.stringify(body) : undefined
            });
            const j = await r.json().catch(()=>({}));
            if (!r.ok) { alert((j.error && j.error.message) || (j.message) || 'Request failed'); throw new Error('fail'); }
            return j.data;
        },
        async saveSettings(){
            await this.api('POST','/settings',{ mode:this.menu.mode, currency:(this.menu.currency||'USD').toUpperCase(), accent_color:this.menu.accent_color });
            this.savedMsg = 'Saved ✓'; setTimeout(()=>this.savedMsg='', 1500);
        },
        openCategory(cat){ this.catModal = cat ? {open:true,id:cat.id,name:cat.name,description:cat.description||''} : {open:true,id:null,name:'',description:''}; },
        async saveCategory(){
            if (!this.catModal.name.trim()) return;
            const payload = { name:this.catModal.name, description:this.catModal.description };
            if (this.catModal.id) { const d = await this.api('PUT','/categories/'+this.catModal.id, payload); const i=this.categories.findIndex(c=>c.id===this.catModal.id); this.categories[i]=d.category; }
            else { const d = await this.api('POST','/categories', payload); this.categories.push(d.category); }
            this.catModal.open = false;
        },
        async deleteCategory(cat){ if(!confirm('Delete "'+cat.name+'" and its items?')) return; await this.api('DELETE','/categories/'+cat.id); this.categories=this.categories.filter(c=>c.id!==cat.id); this.items=this.items.filter(i=>i.category_id!==cat.id); },
        async moveCategory(cat, dir){
            const idx = this.categories.findIndex(c=>c.id===cat.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=this.categories.length) return;
            const arr = this.categories.slice();
            arr.splice(to, 0, arr.splice(idx, 1)[0]);
            this.categories = arr;
            await this.api('POST','/categories/reorder', { order: arr.map(c=>c.id) });
        },
        async moveItem(catId, item, dir){
            const list = this.itemsFor(catId);
            const idx = list.findIndex(i=>i.id===item.id);
            const to = idx + dir;
            if (idx<0 || to<0 || to>=list.length) return;
            list.splice(to, 0, list.splice(idx, 1)[0]);
            const ids = list.map(i=>i.id);
            const others = this.items.filter(i=>i.category_id!==catId);
            this.items = others.concat(list);
            await this.api('POST','/items/reorder', { order: ids });
        },
        openItem(catId, item){ this.itemModal = item ? {open:true,id:item.id,category_id:catId,name:item.name,description:item.description||'',price:item.price,photo_url:item.photo_url||'',is_sold_out:!!item.is_sold_out} : {open:true,id:null,category_id:catId,name:'',description:'',price:'',photo_url:'',is_sold_out:false}; },
        async saveItem(){
            if (!this.itemModal.name.trim()) return;
            const payload = { category_id:this.itemModal.category_id, name:this.itemModal.name, description:this.itemModal.description, price:parseFloat(this.itemModal.price||0), photo_url:this.itemModal.photo_url||null, is_sold_out:this.itemModal.is_sold_out };
            if (this.itemModal.id) { const d = await this.api('PUT','/items/'+this.itemModal.id, payload); const i=this.items.findIndex(x=>x.id===this.itemModal.id); this.items[i]=d.item; }
            else { const d = await this.api('POST','/items', payload); this.items.push(d.item); }
            this.itemModal.open = false;
        },
        async deleteItem(item){ if(!confirm('Delete "'+item.name+'"?')) return; await this.api('DELETE','/items/'+item.id); this.items=this.items.filter(i=>i.id!==item.id); },
        async addTable(){ const label = prompt('Table label (e.g. 1, A2, Patio 3)'); if(!label) return; const d = await this.api('POST','/tables',{ label }); this.tables.push(d.table); },
        async deleteTable(t){ if(!confirm('Delete table "'+t.label+'"?')) return; await this.api('DELETE','/tables/'+t.id); this.tables=this.tables.filter(x=>x.id!==t.id); },
    };
}
</script>
@endsection
