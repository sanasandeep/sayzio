@extends('user.layouts.app')
@section('title', 'AI Brand Studio')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6" x-data="brandStudio()">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white"><i class="fas fa-wand-magic-sparkles text-primary-300 mr-2"></i>AI Brand Studio</h1>
            <p class="text-sm text-white/50 mt-1">Describe what you need in plain language and get a whole on-brand asset kit - a Link in Bio page, short links, QR codes, a form and a digital card - planned by AI and reviewed by you before anything is created.</p>
            @if($aiEnabled)<p class="text-[11px] text-white/40 mt-1">{{ number_format($balance) }} coins</p>@endif
        </div>
    </div>

    @if(!$allowed)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center space-y-3">
            <i class="fas fa-lock text-3xl text-white/30"></i>
            <h2 class="text-white font-semibold">AI Brand Studio isn't included in your plan</h2>
            <p class="text-sm text-white/50 max-w-md mx-auto">Upgrade to turn one brief into a complete set of on-brand links, pages, QR codes and forms - created together in a single run.</p>
            <a href="{{ route('user.upgrade') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary-500 hover:bg-primary-400 text-white text-sm font-medium"><i class="fas fa-arrow-up"></i> See upgrade options</a>
        </div>
    @elseif(!$aiEnabled)
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center text-sm text-white/50">The AI engine is currently disabled. Please check back later.</div>
    @else
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <label class="block text-sm text-white/70 font-medium">Brand context</label>
                    <select x-model="brandKitId" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5">
                        <option value="">No saved brand kit - describe the brand below</option>
                        @foreach($brandKits as $bk)
                            <option value="{{ $bk->id }}">{{ $bk->name }}</option>
                        @endforeach
                    </select>
                    <template x-if="!brandKitId">
                        <div class="space-y-2">
                            <input type="text" x-model="brandName" maxlength="160" placeholder="Brand name" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <input type="text" x-model="brandColors" maxlength="300" placeholder="Brand colors (e.g. #0f172a and gold)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <input type="text" x-model="brandVoice" maxlength="500" placeholder="Voice &amp; tone (e.g. playful, expert, minimal)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30">
                            <textarea x-model="brandDescription" maxlength="1000" rows="2" placeholder="What the brand does (optional)" class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30"></textarea>
                        </div>
                    </template>
                </div>
                <div class="space-y-3">
                    <label class="block text-sm text-white/70 font-medium">What do you want to create?</label>
                    <textarea x-model="brief" maxlength="4000" rows="5" placeholder="e.g. Launching our summer sale - I need a landing bio page, short links for the sale and our socials, QR codes for posters, and a lead form." class="w-full rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2.5 placeholder-white/30"></textarea>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="inline-flex rounded-xl border border-white/10 overflow-hidden text-sm">
                            <button type="button" @click="mode='kit'" :class="mode==='kit' ? 'bg-primary-500 text-white' : 'bg-white/[0.04] text-white/60'" class="px-3 py-2">Full kit</button>
                            <button type="button" @click="mode='bulk'" :class="mode==='bulk' ? 'bg-primary-500 text-white' : 'bg-white/[0.04] text-white/60'" class="px-3 py-2">Bulk variations</button>
                        </div>
                        <template x-if="mode==='bulk'">
                            <div class="flex items-center gap-2">
                                <select x-model="bulkKind" class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                    <option value="short_link">Short links</option>
                                    <option value="qr_code">QR codes</option>
                                    <option value="biolink">Link in Bio pages</option>
                                    <option value="form">Forms</option>
                                    <option value="vcard">Digital cards</option>
                                </select>
                                <input type="number" x-model.number="bulkCount" min="1" max="{{ $bulkCap }}" class="w-20 rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                <span class="text-[11px] text-white/40">max {{ $bulkCap == -1 ? '∞' : $bulkCap }} / run</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <template x-if="mode==='kit'">
                <div class="space-y-3 border-t border-white/10 pt-4">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <label class="block text-sm text-white/70 font-medium">Pick exactly what to create <span class="text-white/35 font-normal">(optional)</span></label>
                            <p class="text-[11px] text-white/40 mt-0.5">Leave empty to let the AI decide from your brief, or lock in an exact composition below.</p>
                        </div>
                        <button type="button" x-show="composition.length" @click="composition = []" class="text-[11px] text-white/40 hover:text-white/70 underline decoration-white/20">Clear composition</button>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <template x-for="p in presets" :key="p.label">
                            <button type="button" @click="applyPreset(p)"
                                    class="px-3 py-1.5 rounded-full text-[12px] border border-white/10 bg-white/[0.04] text-white/60 hover:border-primary-500/50 hover:text-white">
                                <i class="fas fa-layer-group mr-1 text-white/30"></i><span x-text="p.label"></span>
                            </button>
                        </template>
                        <template x-for="p in savedPresets" :key="p.id">
                            <span class="inline-flex items-center rounded-full border border-primary-500/30 bg-primary-500/[0.08] overflow-hidden">
                                <template x-if="renamingId !== p.id">
                                    <span class="inline-flex items-center">
                                        <button type="button" @click="applyPreset(p)"
                                                class="pl-3 pr-1.5 py-1.5 text-[12px] text-white/70 hover:text-white">
                                            <i class="fas fa-bookmark mr-1 text-primary-300/70"></i><span x-text="p.label"></span>
                                        </button>
                                        <button type="button" @click="startRename(p)" :title="`Rename “${p.label}”`"
                                                class="px-1 py-1.5 text-white/35 hover:text-white text-[11px]"><i class="fas fa-pen"></i></button>
                                        <button type="button" @click="deletePreset(p)" :title="`Delete “${p.label}”`"
                                                class="pr-2.5 pl-1 py-1.5 text-white/35 hover:text-red-300 text-[11px]"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                                <template x-if="renamingId === p.id">
                                    <span class="inline-flex items-center gap-1 pl-2 pr-1.5 py-1">
                                        <input type="text" x-model="renameName" maxlength="60" x-init="$el.focus(); $el.select()"
                                               @keydown.enter.prevent="renamePreset(p)" @keydown.escape.prevent="cancelRename()"
                                               class="w-40 rounded-lg bg-white/[0.08] border border-white/15 text-white text-[12px] px-2 py-1 placeholder-white/30"
                                               placeholder="Combo name">
                                        <button type="button" @click="renamePreset(p)" :disabled="renameBusy || !renameName.trim()"
                                                class="px-1.5 py-1 text-primary-300 hover:text-primary-200 disabled:opacity-50 text-[11px]" title="Save name">
                                            <i class="fas" :class="renameBusy ? 'fa-circle-notch fa-spin' : 'fa-check'"></i>
                                        </button>
                                        <button type="button" @click="cancelRename()" class="px-1 py-1 text-white/35 hover:text-white text-[11px]" title="Cancel"><i class="fas fa-times"></i></button>
                                    </span>
                                </template>
                            </span>
                        </template>
                    </div>
                    <p class="text-sm text-red-300" x-show="renameError" x-cloak x-text="renameError"></p>

                    <template x-for="(row, i) in composition" :key="i">
                        <div class="flex items-center gap-2 flex-wrap">
                            <select x-model="row.kind" class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2">
                                <template x-for="(label, kind) in kindLabels" :key="kind">
                                    <option :value="kind" x-text="label" :selected="row.kind === kind"></option>
                                </template>
                            </select>
                            <div class="inline-flex items-center rounded-xl border border-white/10 overflow-hidden">
                                <button type="button" @click="row.count = Math.max(1, row.count - 1)" class="px-2.5 py-2 bg-white/[0.04] text-white/60 hover:text-white text-sm">−</button>
                                <span class="px-3 py-2 text-white text-sm tabular-nums" x-text="row.count"></span>
                                <button type="button" @click="row.count = Math.min(kitCaps[row.kind] || 1, row.count + 1)" class="px-2.5 py-2 bg-white/[0.04] text-white/60 hover:text-white text-sm">+</button>
                            </div>
                            <input type="text" x-model="row.purpose" maxlength="120" placeholder="Purpose (e.g. for the product page)"
                                   class="flex-1 min-w-[180px] rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2 placeholder-white/30">
                            <button type="button" @click="composition.splice(i, 1)" class="px-2.5 py-2 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-300 text-sm"><i class="fas fa-times"></i></button>
                        </div>
                    </template>

                    <div class="flex items-center gap-3 flex-wrap">
                        <button type="button" @click="addRow()" class="text-[12px] text-primary-300 hover:text-primary-200"><i class="fas fa-plus mr-1"></i>Add asset</button>
                        <button type="button" x-show="composition.length && !compositionError()" @click="savingPreset = !savingPreset; presetError = ''"
                                class="text-[12px] text-white/50 hover:text-white"><i class="fas fa-bookmark mr-1"></i>Save this combo</button>
                        <span class="text-sm text-amber-300" x-show="compositionError()" x-text="compositionError()"></span>
                    </div>

                    <div x-show="savingPreset" x-cloak class="flex items-center gap-2 flex-wrap">
                        <input type="text" x-model="presetName" maxlength="60" placeholder="Combo name (e.g. Event kit)"
                               @keydown.enter.prevent="savePreset()"
                               class="rounded-xl bg-white/[0.05] border border-white/10 text-white text-sm px-3 py-2 placeholder-white/30 min-w-[220px]">
                        <button type="button" @click="savePreset()" :disabled="presetBusy || !presetName.trim()"
                                class="px-3 py-2 rounded-xl bg-primary-500 hover:bg-primary-400 disabled:opacity-50 text-white text-[12px] font-medium">
                            <span x-text="presetBusy ? 'Saving…' : 'Save combo'"></span>
                        </button>
                        <button type="button" @click="savingPreset = false; presetError = ''" class="text-[12px] text-white/40 hover:text-white/70">Cancel</button>
                        <span class="text-sm text-red-300" x-show="presetError" x-text="presetError"></span>
                    </div>
                </div>
            </template>

            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" @click="plan()" :disabled="busy || !canGenerate()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-400 disabled:opacity-50 text-white text-sm font-medium">
                    <i class="fas" :class="busy ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>
                    <span x-text="busy ? 'Planning your kit…' : 'Generate plan'"></span>
                </button>
                <button type="button" @click="estimate()" :disabled="busy || !canGenerate()"
                        class="text-sm text-white/60 hover:text-white underline decoration-white/20">Estimate cost</button>
                <span class="text-[11px] text-white/40" x-show="estBusy">Estimating cost…</span>
                <span class="text-[11px] text-white/40" x-show="!estBusy && estimateText" x-text="estimateText"></span>
                <span class="text-sm text-red-300" x-show="error" x-text="error"></span>
            </div>
            <template x-if="!estBusy && estCredits !== null && mode === 'bulk'">
                <p class="text-[11px] text-white/40">
                    <i class="fas fa-layer-group mr-1 text-white/30"></i>
                    <span x-text="`${bulkVariants()} variant${bulkVariants() === 1 ? '' : 's'} × ~${perVariantCredits()} coins each ≈ ${estCredits} coins total`"></span>
                </p>
            </template>
            <div x-show="!estBusy && lowBalance()" x-cloak
                 class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm flex items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <span x-text="`This run needs about ${estCredits} coins but you only have ${estBalance}. Top up your coins before generating, or reduce the scope.`"></span>
            </div>
            <p class="text-[11px] text-white/35">You'll review the full plan before anything is created. Planning uses coins; a failed run is automatically refunded.</p>
        </div>

        <div class="space-y-3">
            <h2 class="text-white font-semibold">Your kits</h2>
            @forelse($kits as $k)
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <a href="{{ route('user.brand-studio.show', $k) }}" class="text-white font-medium hover:underline">{{ $k->name }}</a>
                        <p class="text-[11px] text-white/40 mt-0.5">
                            {{ $k->mode === 'bulk' ? 'Bulk variations' : 'Full kit' }} ·
                            {{ count($k->isCreated() ? $k->createdAssets() : $k->proposedAssets()) }} asset(s) ·
                            {{ $k->created_at->diffForHumans() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($k->isCreated())
                            <span class="px-2.5 py-1 rounded-full text-[11px] bg-emerald-500/10 border border-emerald-500/20 text-emerald-300">Created</span>
                        @else
                            <span class="px-2.5 py-1 rounded-full text-[11px] bg-amber-500/10 border border-amber-500/20 text-amber-300">Awaiting review</span>
                        @endif
                        <a href="{{ route('user.brand-studio.show', $k) }}" class="px-3 py-1.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-white/80 text-sm">{{ $k->isCreated() ? 'View results' : 'Review' }}</a>
                        <form method="POST" action="{{ route('user.brand-studio.destroy', $k) }}" onsubmit="return confirm('Delete this kit record? Created assets are kept.');">
                            @csrf @method('DELETE')
                            <button class="px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-300 text-sm"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-white/40">No kits yet - describe what you need above and generate your first plan.</p>
            @endforelse
        </div>
    @endif
</div>

<script>
function brandStudio() {
    return {
        brandKitId: '', brandName: '', brandColors: '', brandVoice: '', brandDescription: '',
        brief: '', mode: 'kit', bulkKind: 'short_link', bulkCount: 5,
        busy: false, error: '', estimateText: '',
        estCredits: null, estBalance: {{ (int) $balance }}, estBusy: false,
        _estTimer: null, _estSeq: 0,
        composition: [],
        savedPresets: @js($savedPresets),
        savingPreset: false, presetName: '', presetBusy: false, presetError: '',
        renamingId: null, renameName: '', renameBusy: false, renameError: '',
        kitCaps: @js($kitCaps),
        kindLabels: { biolink: 'Link in Bio page', short_link: 'Short link', qr_code: 'QR code', form: 'Form', vcard: 'Digital card' },
        presets: [
            { label: 'Product + sales + card', rows: [
                { kind: 'biolink', count: 1, purpose: 'Product page' },
                { kind: 'biolink', count: 1, purpose: 'Sales offer page' },
                { kind: 'vcard', count: 1, purpose: 'Digital business card' },
            ] },
            { label: 'Launch pack', rows: [
                { kind: 'biolink', count: 1, purpose: 'Launch landing page' },
                { kind: 'short_link', count: 3, purpose: 'Campaign links' },
                { kind: 'qr_code', count: 2, purpose: 'Poster QR codes' },
            ] },
            { label: 'Lead-gen pack', rows: [
                { kind: 'biolink', count: 1, purpose: 'Lead capture page' },
                { kind: 'form', count: 1, purpose: 'Lead form' },
            ] },
            { label: 'Personal brand', rows: [
                { kind: 'biolink', count: 1, purpose: 'Personal bio page' },
                { kind: 'vcard', count: 1, purpose: 'Digital card' },
                { kind: 'qr_code', count: 1, purpose: 'Share-me QR code' },
            ] },
        ],
        init() {
            ['brief', 'mode', 'bulkKind', 'bulkCount', 'brandKitId', 'brandName', 'brandColors', 'brandVoice', 'brandDescription', 'composition']
                .forEach((k) => this.$watch(k, () => this.scheduleEstimate()));
        },
        applyPreset(p) {
            this.composition = p.rows.map((r) => ({ ...r }));
        },
        async savePreset() {
            const name = this.presetName.trim();
            if (!name || this.presetBusy || !this.composition.length) return;
            this.presetBusy = true; this.presetError = '';
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.store')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({
                        name,
                        composition: this.composition.map((r) => ({ kind: r.kind, count: Math.max(1, parseInt(r.count, 10) || 1), purpose: (r.purpose || '').trim() })),
                    }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Could not save this combo. Please try again.');
                this.savedPresets = [json.preset, ...this.savedPresets.filter((p) => p.id !== json.preset.id)];
                this.savingPreset = false; this.presetName = '';
            } catch (e) {
                this.presetError = e.message;
            } finally {
                this.presetBusy = false;
            }
        },
        startRename(p) {
            this.renamingId = p.id;
            this.renameName = p.label;
            this.renameError = '';
        },
        cancelRename() {
            this.renamingId = null;
            this.renameName = '';
            this.renameError = '';
        },
        async renamePreset(p) {
            const name = this.renameName.trim();
            if (!name || this.renameBusy) return;
            if (name === p.label) { this.cancelRename(); return; }
            this.renameBusy = true; this.renameError = '';
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.rename', ':id')).replace(':id', p.id), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ name }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(json.message || 'Could not rename this combo. Please try again.');
                this.savedPresets = this.savedPresets.map((x) => x.id === json.preset.id ? json.preset : x);
                this.cancelRename();
            } catch (e) {
                this.renameError = e.message;
            } finally {
                this.renameBusy = false;
            }
        },
        async deletePreset(p) {
            if (!confirm(`Delete the saved combo “${p.label}”?`)) return;
            try {
                const res = await fetch(@js(route('user.brand-studio.presets.destroy', ':id')).replace(':id', p.id), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                if (!res.ok) throw new Error('Could not delete this combo. Please try again.');
                this.savedPresets = this.savedPresets.filter((x) => x.id !== p.id);
            } catch (e) {
                this.presetError = e.message;
                this.savingPreset = true;
            }
        },
        addRow() {
            this.composition.push({ kind: 'biolink', count: 1, purpose: '' });
        },
        compositionError() {
            const sums = {};
            for (const r of this.composition) {
                sums[r.kind] = (sums[r.kind] || 0) + Math.max(1, parseInt(r.count, 10) || 1);
                const cap = this.kitCaps[r.kind] || 0;
                if (sums[r.kind] > cap) {
                    return `Too many ${this.kindLabels[r.kind] || r.kind}s: max ${cap} per kit.`;
                }
            }
            return '';
        },
        canGenerate() {
            if (this.mode === 'kit' && this.composition.length) return !this.compositionError();
            return !!this.brief.trim();
        },
        bulkVariants() {
            const cap = {{ (int) $bulkCap }};
            let n = Math.max(1, parseInt(this.bulkCount, 10) || 1);
            if (cap > 0) n = Math.min(n, cap);
            return n;
        },
        perVariantCredits() {
            if (this.estCredits === null) return 0;
            return Math.max(1, Math.round(this.estCredits / this.bulkVariants()));
        },
        lowBalance() {
            return this.estCredits !== null && this.estCredits > this.estBalance;
        },
        scheduleEstimate() {
            clearTimeout(this._estTimer);
            this.estCredits = null; this.estimateText = '';
            if (!this.canGenerate()) { this.estBusy = false; return; }
            this.estBusy = true;
            this._estTimer = setTimeout(() => this.estimate(true), 600);
        },
        payload() {
            return {
                request: this.brief,
                mode: this.mode,
                composition: this.mode === 'kit' && this.composition.length
                    ? this.composition.map((r) => ({ kind: r.kind, count: Math.max(1, parseInt(r.count, 10) || 1), purpose: (r.purpose || '').trim() }))
                    : null,
                bulk_kind: this.mode === 'bulk' ? this.bulkKind : null,
                bulk_count: this.mode === 'bulk' ? this.bulkCount : null,
                brand_kit_id: this.brandKitId || null,
                brand_name: this.brandName, brand_colors: this.brandColors,
                brand_voice: this.brandVoice, brand_description: this.brandDescription,
            };
        },
        async post(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify(this.payload()),
            });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(json.message || 'Something went wrong. Please try again.');
            return json;
        },
        async estimate(auto = false) {
            if (!auto) { this.error = ''; }
            this.estimateText = '';
            const seq = ++this._estSeq;
            this.estBusy = true;
            try {
                const j = await this.post(@js(route('user.brand-studio.estimate')));
                if (seq !== this._estSeq) return;
                this.estCredits = j.estimated_credits;
                this.estBalance = j.balance;
                this.estimateText = `≈ ${j.estimated_credits} coins (you have ${j.balance})`;
            } catch (e) {
                if (seq !== this._estSeq) return;
                this.estCredits = null;
                if (!auto) this.error = e.message;
            } finally {
                if (seq === this._estSeq) this.estBusy = false;
            }
        },
        async plan() {
            this.error = ''; this.busy = true;
            try {
                const j = await this.post(@js(route('user.brand-studio.plan')));
                window.location.href = j.redirect;
            } catch (e) { this.error = e.message; this.busy = false; }
        },
    };
}
</script>
@endsection
