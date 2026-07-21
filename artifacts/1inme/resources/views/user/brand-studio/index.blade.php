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
            @if($aiEnabled)<p class="text-[11px] text-white/40 mt-1">{{ number_format($balance) }} AI credits</p>@endif
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

            <div class="flex items-center gap-3 flex-wrap">
                <button type="button" @click="plan()" :disabled="busy || !brief.trim()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-500 hover:bg-primary-400 disabled:opacity-50 text-white text-sm font-medium">
                    <i class="fas" :class="busy ? 'fa-circle-notch fa-spin' : 'fa-wand-magic-sparkles'"></i>
                    <span x-text="busy ? 'Planning your kit…' : 'Generate plan'"></span>
                </button>
                <button type="button" @click="estimate()" :disabled="busy || !brief.trim()"
                        class="text-sm text-white/60 hover:text-white underline decoration-white/20">Estimate cost</button>
                <span class="text-[11px] text-white/40" x-show="estBusy">Estimating cost…</span>
                <span class="text-[11px] text-white/40" x-show="!estBusy && estimateText" x-text="estimateText"></span>
                <span class="text-sm text-red-300" x-show="error" x-text="error"></span>
            </div>
            <template x-if="!estBusy && estCredits !== null && mode === 'bulk'">
                <p class="text-[11px] text-white/40">
                    <i class="fas fa-layer-group mr-1 text-white/30"></i>
                    <span x-text="`${bulkVariants()} variant${bulkVariants() === 1 ? '' : 's'} × ~${perVariantCredits()} credits each ≈ ${estCredits} credits total`"></span>
                </p>
            </template>
            <div x-show="!estBusy && lowBalance()" x-cloak
                 class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm flex items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5"></i>
                <span x-text="`This run needs about ${estCredits} AI credits but you only have ${estBalance}. Top up your credits before generating, or reduce the scope.`"></span>
            </div>
            <p class="text-[11px] text-white/35">You'll review the full plan before anything is created. Planning uses AI credits; a failed run is automatically refunded.</p>
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
        init() {
            ['brief', 'mode', 'bulkKind', 'bulkCount', 'brandKitId', 'brandName', 'brandColors', 'brandVoice', 'brandDescription']
                .forEach((k) => this.$watch(k, () => this.scheduleEstimate()));
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
            if (!this.brief.trim()) { this.estBusy = false; return; }
            this.estBusy = true;
            this._estTimer = setTimeout(() => this.estimate(true), 600);
        },
        payload() {
            return {
                request: this.brief,
                mode: this.mode,
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
                this.estimateText = `≈ ${j.estimated_credits} credits (you have ${j.balance})`;
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
