@extends('user.layouts.app')
@section('title', 'Brand Kit')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6"
     x-data="brandKits()">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white"><i class="fas fa-palette text-primary-300 mr-2"></i>AI Brand Kit</h1>
            <p class="text-sm text-white/50 mt-1">Generate a cohesive brand identity — palette, fonts, voice, taglines and a recommended theme — then apply it to your Link in Bio pages and QR codes.</p>
            <p class="text-[11px] text-white/40 mt-1">
                {{ $count }} of {{ $cap == -1 ? '∞' : $cap }} brand kits used
                @if($aiEnabled) &middot; {{ number_format($balance) }} AI credits @endif
            </p>
        </div>
    </div>

    @if($consistency && $consistency['links_total'] > 0)
        @php
            $sc = $consistency['score'];
            $ring = $sc >= 90 ? 'text-emerald-300' : ($sc >= 75 ? 'text-lime-300' : ($sc >= 50 ? 'text-amber-300' : 'text-red-300'));
        @endphp
        <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-violet-500/[0.07] to-fuchsia-500/[0.04] p-6">
            <div class="flex items-start gap-5 flex-wrap">
                <div class="flex items-center gap-4">
                    <div class="relative w-20 h-20 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90">
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3"></circle>
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"
                                    class="{{ $ring }}"
                                    stroke-dasharray="{{ round($sc / 100 * 97.4, 1) }} 97.4"></circle>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-lg font-bold text-white">{{ $sc }}</span>
                    </div>
                    <div>
                        <h2 class="text-white font-semibold">Brand Consistency Score</h2>
                        <p class="text-sm {{ $ring }} font-medium">{{ $consistency['label'] }} <span class="text-white/40 font-normal">· grade {{ $consistency['grade'] }}</span></p>
                        <p class="text-[11px] text-white/40 mt-0.5">
                            {{ $consistency['links_on_brand'] }} of {{ $consistency['links_total'] }} pages on-brand against “{{ $consistency['kit_name'] }}”
                        </p>
                    </div>
                </div>
                @if(empty($consistency['findings']))
                    <p class="text-sm text-emerald-300 self-center"><i class="fas fa-circle-check mr-1"></i>Every Link in Bio matches your Brand Kit. Nice and tidy.</p>
                @endif
            </div>

            @if(!empty($consistency['findings']))
                <div class="mt-5 space-y-2">
                    @foreach($consistency['findings'] as $f)
                        @php
                            $tone = match($f['severity']) {
                                'critical' => 'border-red-500/25 bg-red-500/[0.06]',
                                'warning'  => 'border-amber-500/25 bg-amber-500/[0.06]',
                                default    => 'border-white/10 bg-white/[0.03]',
                            };
                        @endphp
                        <div class="rounded-xl border {{ $tone }} p-4 flex items-start justify-between gap-4 flex-wrap">
                            <div class="min-w-0">
                                <p class="text-sm text-white font-medium">{{ $f['headline'] }}</p>
                                <p class="text-xs text-white/55 mt-0.5">{{ $f['reason'] }}</p>
                                @if(!empty($f['mismatches']))
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        @foreach($f['mismatches'] as $m)
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/5 border border-white/10 text-white/60">
                                                {{ $m['label'] }}: <span class="text-white/40">{{ $m['current'] ?? '—' }}</span> → <span class="text-white/80">{{ $m['expected'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <form method="POST" action="{{ $f['apply_url'] }}" class="shrink-0">
                                @csrf
                                <button class="px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white text-xs font-medium whitespace-nowrap">
                                    <i class="fas fa-wand-magic-sparkles mr-1"></i>Apply fix
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if(!$aiEnabled)
        <div class="rounded-2xl border border-amber-500/20 bg-amber-500/[0.06] p-6 text-amber-200">
            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-2"></i>AI Engine is currently disabled.</p>
            <p class="text-sm mt-1 text-amber-200/80">The AI Brand Kit generator is unavailable right now. Your saved kits are still listed below and can be applied.</p>
        </div>
    @elseif(!$canCreate)
        {{-- Plan cap reached / plan-less → upgrade prompt --}}
        <div class="rounded-2xl border border-primary-500/20 bg-gradient-to-br from-primary-500/[0.08] to-primary-400/[0.05] p-8 text-center">
            <i class="fas fa-wand-magic-sparkles text-4xl text-primary-300"></i>
            <p class="mt-3 text-white font-semibold text-lg">
                @if($cap == 0)
                    AI Brand Kits aren’t included on your current plan
                @else
                    You’ve reached your brand-kit limit ({{ $cap }})
                @endif
            </p>
            <p class="text-sm text-white/60 mt-1 max-w-lg mx-auto">
                Upgrade to generate AI brand kits — a full palette, font pairing, voice/tone, taglines and bio you can apply across your links and QR codes in one click.
            </p>
            <a href="{{ route('user.upgrade') }}"
               class="inline-block mt-4 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium">
                <i class="fas fa-arrow-up mr-1"></i>
                @if($upgradePlan) Upgrade to {{ $upgradePlan->name }} @else See upgrade options @endif
            </a>
        </div>
    @else
        {{-- Generate form --}}
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 space-y-4">
            <h2 class="text-white font-semibold">Generate a new brand kit</h2>
            <div>
                <label class="block text-xs text-white/50 mb-1">Describe your brand</label>
                <textarea x-model="form.prompt" rows="3"
                          class="w-full rounded-xl bg-black/30 border border-white/10 text-white text-sm px-3 py-2 focus:border-primary-400 focus:outline-none"
                          placeholder="e.g. A calm, modern wellness studio for busy professionals — earthy but premium."></textarea>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/50 mb-1">Website URL <span class="text-white/30">(optional)</span></label>
                    <input x-model="form.website_url" type="url"
                           class="w-full rounded-xl bg-black/30 border border-white/10 text-white text-sm px-3 py-2 focus:border-primary-400 focus:outline-none"
                           placeholder="https://yourbrand.com">
                </div>
                <div>
                    <label class="block text-xs text-white/50 mb-1">Logo URL <span class="text-white/30">(optional)</span></label>
                    <input x-model="form.logo_url" type="url"
                           class="w-full rounded-xl bg-black/30 border border-white/10 text-white text-sm px-3 py-2 focus:border-primary-400 focus:outline-none"
                           placeholder="https://.../logo.png">
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <p class="text-[11px] text-white/40" x-text="estimateText"></p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="estimate()" :disabled="busy"
                            class="px-3 py-2 rounded-xl border border-white/10 text-white/80 text-sm hover:bg-white/5 disabled:opacity-50">
                        Estimate cost
                    </button>
                    <button type="button" @click="generate()" :disabled="busy"
                            class="px-4 py-2 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-sm disabled:opacity-50">
                        <i class="fas fa-wand-magic-sparkles mr-1"></i>
                        <span x-text="busy ? 'Generating…' : 'Generate brand kit'"></span>
                    </button>
                </div>
            </div>
            <p x-show="error" x-text="error" class="text-sm text-red-300"></p>
        </div>
    @endif

    {{-- Saved kits --}}
    @if($kits->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-swatchbook text-4xl text-primary-400/70"></i>
            <p class="mt-3 text-white font-semibold">No brand kits yet.</p>
            <p class="text-sm text-white/50 mt-1">Generate your first brand kit above to lock in a consistent look.</p>
        </div>
    @else
        <div class="grid md:grid-cols-2 gap-4">
            @foreach($kits as $kit)
                @php
                    $cfg = is_array($kit->config) ? $kit->config : [];
                    $palette = $cfg['palette'] ?? [];
                    $fonts = $cfg['fonts'] ?? [];
                    $swatches = array_filter(array_merge(
                        [$palette['primary'] ?? null, $palette['secondary'] ?? null, $palette['accent'] ?? null],
                        (array)($palette['neutrals'] ?? [])
                    ));
                @endphp
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="text-white font-semibold">{{ $kit->name }}</h3>
                            <p class="text-[11px] text-white/40">Created {{ $kit->created_at?->diffForHumans() }}</p>
                        </div>
                        <form method="POST" action="{{ route('user.brand-kits.destroy', $kit) }}"
                              onsubmit="return confirm('Delete this brand kit?');">
                            @csrf @method('DELETE')
                            <button class="text-white/40 hover:text-red-300 text-sm" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        @foreach($swatches as $c)
                            <span class="w-7 h-7 rounded-lg border border-white/10" style="background: {{ $c }};" title="{{ $c }}"></span>
                        @endforeach
                    </div>

                    @if(!empty($fonts['heading']) || !empty($fonts['body']))
                        <p class="text-xs text-white/60"><i class="fas fa-font mr-1 text-white/40"></i>{{ $fonts['heading'] ?? '—' }} <span class="text-white/30">/</span> {{ $fonts['body'] ?? '—' }}</p>
                    @endif
                    @if(!empty($cfg['voice']['tone']))
                        <p class="text-xs text-white/50"><i class="fas fa-comment mr-1 text-white/40"></i>{{ $cfg['voice']['tone'] }}</p>
                    @endif
                    @if(!empty($cfg['taglines']))
                        <p class="text-xs text-white/50 italic">“{{ $cfg['taglines'][0] }}”</p>
                    @endif

                    {{-- Apply to a biolink --}}
                    @if($biolinks->isNotEmpty())
                        <form method="POST" class="flex items-center gap-2" x-data="{l:''}"
                              :action="l ? '{{ url('user/brand-kits/'.$kit->id.'/apply/biolink') }}/' + l : '#'"
                              @submit="if(!l){$event.preventDefault();}">
                            @csrf
                            <select x-model="l" class="flex-1 rounded-lg bg-black/30 border border-white/10 text-white text-xs px-2 py-1.5">
                                <option value="">Apply to a Link in Bio…</option>
                                @foreach($biolinks as $b)
                                    <option value="{{ $b->id }}">{{ $b->title ?: $b->alias }}</option>
                                @endforeach
                            </select>
                            <button class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs">Apply</button>
                        </form>
                    @endif

                    {{-- Apply to a QR code --}}
                    @if($qrCodes->isNotEmpty())
                        <form method="POST" class="flex items-center gap-2" x-data="{q:''}"
                              :action="q ? '{{ url('user/brand-kits/'.$kit->id.'/apply/qr') }}/' + q : '#'"
                              @submit="if(!q){$event.preventDefault();}">
                            @csrf
                            <select x-model="q" class="flex-1 rounded-lg bg-black/30 border border-white/10 text-white text-xs px-2 py-1.5">
                                <option value="">Apply palette to a QR code…</option>
                                @foreach($qrCodes as $q)
                                    <option value="{{ $q->id }}">{{ $q->name ?: ('QR #'.$q->id) }}</option>
                                @endforeach
                            </select>
                            <button class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs">Apply</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function brandKits() {
    return {
        form: { prompt: '', website_url: '', logo_url: '' },
        busy: false,
        error: '',
        estimateText: '',
        _csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },
        async estimate() {
            this.error = '';
            this.estimateText = 'Estimating…';
            try {
                const res = await fetch('{{ route('user.brand-kits.estimate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) { this.estimateText = ''; this.error = data.message || 'Could not estimate cost.'; return; }
                this.estimateText = `≈ ${data.estimated_credits} credits · balance ${data.balance}`;
            } catch (e) { this.estimateText = ''; this.error = 'Network error.'; }
        },
        async generate() {
            this.error = '';
            this.busy = true;
            try {
                const res = await fetch('{{ route('user.brand-kits.generate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) { this.busy = false; this.error = data.message || 'Generation failed.'; return; }
                window.location = data.redirect || '{{ route('user.brand-kits.index') }}';
            } catch (e) { this.busy = false; this.error = 'Network error.'; }
        },
    };
}
</script>
@endsection
