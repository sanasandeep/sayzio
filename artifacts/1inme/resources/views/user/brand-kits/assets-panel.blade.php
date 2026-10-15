                    <div x-data="kitAssets({{ $kit->id }})" x-init="load()" class="pt-2 border-t border-slate-200">
                        <button type="button" @click="toggle()"
                                class="w-full flex items-center justify-between text-left text-xs text-slate-600 hover:text-slate-900 py-1">
                            <span><i class="fas fa-images mr-1.5 text-primary-300"></i>Optional AI generation</span>
                            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>

                        <div x-show="open" x-cloak class="mt-2 space-y-2">
                            <p x-show="loading" class="text-xs text-slate-500">Loading…</p>
                            <p x-show="error" x-text="error" class="text-xs text-red-300"></p>
                            <template x-if="loaded && !allowed">
                                <p class="text-xs text-amber-300">Brand asset generation isn’t included on your plan.
                                    <a href="{{ route('user.upgrade') }}" class="underline">Upgrade</a></p>
                            </template>
                            <template x-if="loaded && allowed && !enabled">
                                <p class="text-xs text-amber-300">AI image generation is currently unavailable.</p>
                            </template>

                            <div class="bk-feedback"><p>Choose assets for a batch. Each selected image is generated separately using the saved kit.</p><div class="bk-actions mt-2"><strong x-text="selected.reduce((sum,key)=>sum+Number(types.find(t=>t.type===key)?.cost||0),0)+' coins estimated'"></strong><button type="button" class="btn-primary" @click="generateBatch()" :disabled="!selected.length||busyType||batchRunning">Generate selected assets</button></div></div>
                            <div class="bk-asset-grid"><template x-for="t in types" :key="t.type">
                                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                    <div class="flex items-start gap-3">
                                        <template x-if="t.asset && t.asset.image_url">
                                            <a :href="t.asset.image_url" target="_blank" class="shrink-0">
                                                <img :src="t.asset.image_url" :alt="t.label" class="w-14 h-14 rounded-lg object-cover border border-slate-200">
                                            </a>
                                        </template>
                                        <template x-if="!t.asset">
                                            <div class="w-14 h-14 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-400 shrink-0"><i class="fas fa-image"></i></div>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <label class="bk-check"><input type="checkbox" :value="t.type" x-model="selected" :disabled="busyType||batchRunning"><span x-text="t.label"></span></label>
                                            <p class="text-xs text-slate-500">
                                                <span x-text="t.cost + ' coins'"></span>
                                                <template x-if="t.asset"><span> · v<span x-text="t.asset.version"></span></span></template>
                                            </p>
                                            <div class="flex flex-wrap gap-1.5 mt-1.5"><template x-if="t.asset?.image_url && slotFor(t.type)"><button type="button" class="btn-ghost text-xs" @click="$dispatch('brand-kit-asset-selected',{field:slotFor(t.type),url:t.asset.image_url})">Use in unsaved kit</button></template>
                                                <template x-if="!t.asset">
                                                    <button type="button" @click="generate(t, 'new')" :disabled="busyType"
                                                            class="px-2 py-1 rounded-md bg-primary-600 hover:bg-primary-500 text-white text-xs disabled:opacity-50">
                                                        <span x-text="busyType === t.type ? 'Generating…' : 'Generate'"></span>
                                                    </button>
                                                </template>
                                                <template x-if="t.asset">
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <button type="button" @click="generate(t, 'variation')" :disabled="busyType"
                                                                title="A fresh creative take on the same brief"
                                                                class="px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs disabled:opacity-50">
                                                            <span x-text="busyType === t.type ? 'Working…' : 'Variation'"></span>
                                                        </button>
                                                        <button type="button" @click="openTweak(t)" :disabled="busyType"
                                                                title="Keep this direction, tell the AI what to change"
                                                                class="px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs disabled:opacity-50">Alter…</button>
                                                        <a :href="t.asset.download_url || t.asset.image_url" target="_blank"
                                                           class="px-2 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs">Download</a>
                                                        <template x-if="t.apply_targets && t.apply_targets.includes('kit_logo')">
                                                            <button type="button" @click="apply(t, 'kit_logo')" :disabled="busyType"
                                                                    class="px-2 py-1 rounded-md bg-emerald-600/80 hover:bg-emerald-500 text-white text-xs disabled:opacity-50">Set as kit logo</button>
                                                        </template>
                                                        <button type="button" @click="destroy(t)" :disabled="busyType"
                                                                class="px-2 py-1 rounded-md text-red-300/80 hover:text-red-300 text-xs">Delete</button>
                                                    </div>
                                                </template>
                                            </div>
                                            {{-- Alteration prompt --}}
                                            <div x-show="tweakType === t.type" x-cloak class="mt-2 flex items-center gap-1.5">
                                                <input type="text" x-model="tweakText" maxlength="500"
                                                       placeholder="e.g. make the background darker, larger logo"
                                                       class="flex-1 rounded-md bg-white border border-slate-200 text-slate-800 text-xs px-2 py-1">
                                                <button type="button" @click="generate(t, 'alteration')" :disabled="busyType"
                                                        class="px-2 py-1 rounded-md bg-primary-600 hover:bg-primary-500 text-white text-xs disabled:opacity-50">Go</button>
                                                <button type="button" @click="tweakType = null" class="text-slate-500 text-xs">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template></div>
                        </div>
                    </div>
<script>
function kitAssets(kitId) {
    const base = '{{ url('user/brand-kits') }}/' + kitId + '/assets';
    return {
        open: true, selected: [], batchRunning:false, loaded: false, loading: false,
        enabled: false, allowed: false, balance: 0,
        types: [], error: '',
        busyType: null, tweakType: null, tweakText: '',
        _csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },
        _headers() { return { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }; },
        toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded && !this.loading) this.load();
        },
        async load() {
            this.loading = true; this.error = '';
            try {
                const res = await fetch(base, { headers: this._headers() });
                const data = await res.json();
                if (!res.ok) { this.error = data.message || 'Could not load assets.'; return; }
                this.enabled = !!data.enabled;
                this.allowed = !!data.allowed;
                this.balance = data.balance || 0;
                this.types = data.types || [];
                this.loaded = true;
            } catch (e) { this.error = 'Network error.'; }
            finally { this.loading = false; }
        },
        slotFor(type){return ({logo:'logo',favicon:'favicon',logo_secondary:'logo_secondary',logo_light:'logo_light',logo_dark:'logo_dark',logo_monochrome:'logo_monochrome',brand_icon:'brand_icon',pattern:'pattern',background:'background',letterhead:'letterhead',business_card:'business_card',email_banner:'email_banner',social_banner:'social_banner',social_post:'social_post',ppt_cover:'presentation_cover'})[type]||null},
        openTweak(t) { this.tweakType = t.type; this.tweakText = ''; },
        async generateBatch() {
            const items=this.types.filter(t=>this.selected.includes(t.type));
            const cost=items.reduce((sum,t)=>sum+Number(t.cost),0);
            if(!confirm('Generate '+items.length+' images for approximately '+cost+' coins? Existing selected images will be replaced. Each successful image is charged separately.'))return;
            this.batchRunning=true;
            try {for(const item of items){await this.generate(item,'new',true);if(this.error)break;}} finally {this.batchRunning=false;}
        },
        async generate(t, mode, batch=false) {
            if(!batch&&!window.confirm('Generate '+t.label+' for approximately '+t.cost+' coins?')) return;
            this.error = ''; this.busyType = t.type;
            const body = { mode };
            if (mode === 'alteration' && this.tweakText.trim()) body.instructions = this.tweakText.trim();
            try {
                const res = await fetch(base + '/' + t.type + '/generate', { method: 'POST', headers: this._headers(), body: JSON.stringify(body) });
                const data = await res.json();
                if (!res.ok) { this.error = data.message || 'Generation failed.'; return; }
                t.asset = data.asset;
                this.balance = data.balance ?? this.balance;
                this.tweakType = null;
            } catch (e) { this.error = 'Network error.'; }
            finally { this.busyType = null; }
        },
        async apply(t, target) {
            this.error = ''; this.busyType = t.type;
            try {
                const res = await fetch(base + '/' + t.type + '/apply', { method: 'POST', headers: this._headers(), body: JSON.stringify({ target }) });
                const data = await res.json();
                if (!res.ok) { this.error = data.message || 'Apply failed.'; return; }
                if(target==='kit_logo') this.$dispatch('brand-kit-logo-updated',{url:t.asset.image_url});
            } catch (e) { this.error = 'Network error.'; }
            finally { this.busyType = null; }
        },
        async destroy(t) {
            if (!window.confirm('Delete this generated asset? Its stored image is removed too.')) return;
            this.error = ''; this.busyType = t.type;
            try {
                const res = await fetch(base + '/' + t.type, { method: 'DELETE', headers: this._headers() });
                if (!res.ok) { const data = await res.json().catch(() => ({})); this.error = data.message || 'Delete failed.'; return; }
                t.asset = null;
            } catch (e) { this.error = 'Network error.'; }
            finally { this.busyType = null; }
        },
    };
}

</script>