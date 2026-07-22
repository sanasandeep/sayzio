@extends('user.layouts.app')
@section('title', 'AI Brand Kit')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6"
     x-data="brandKits()">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white"><i class="fas fa-palette text-primary-300 mr-2"></i>AI Brand Kit</h1>
            <p class="text-sm text-white/50 mt-1">Generate a cohesive brand identity (palette, fonts, voice, taglines and a recommended theme) then apply it to your Link in Bio pages and QR codes.</p>
            <p class="text-[11px] text-white/40 mt-1">
                {{ $count }} of {{ $cap == -1 ? '∞' : $cap }} brand kits used
                @if($aiEnabled) &middot; {{ number_format($balance) }} coins @endif
            </p>
        </div>
    </div>

    @if($consistency && $consistency['links_total'] > 0)
        @php
            $sc = $consistency['score'];
            $ring = $sc >= 90 ? 'text-emerald-300' : ($sc >= 75 ? 'text-lime-300' : ($sc >= 50 ? 'text-amber-300' : 'text-red-300'));
        @endphp
        <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-primary-500/[0.07] to-primary-400/[0.04] p-6">
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
                    <p class="text-sm text-emerald-300 self-center"><i class="fas fa-circle-check mr-1"></i>Every Link in Bio matches your AI Brand Kit. Nice and tidy.</p>
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
                                <button class="px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-500 text-white text-xs font-medium whitespace-nowrap">
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
                Upgrade to generate AI brand kits, a full palette, font pairing, voice/tone, taglines and bio you can apply across your links and QR codes in one click.
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
                          placeholder="e.g. A calm, modern wellness studio for busy professionals, earthy but premium."></textarea>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-white/50 mb-1">Website URL <span class="text-white/30">(optional)</span></label>
                    <input x-model="form.website_url" type="url"
                           class="w-full rounded-xl bg-black/30 border border-white/10 text-white text-sm px-3 py-2 focus:border-primary-400 focus:outline-none"
                           placeholder="https://yourbrand.com">
                </div>
                <div @file-url-change.stop="form.logo_url = $event.detail.url">
                    <label class="block text-xs text-white/50 mb-1.5">Logo <span class="text-white/30">(optional)</span></label>
                    @include('user.links.partials.file-upload-field', [
                        'fieldName'    => '_brand_kit_logo',
                        'currentValue' => '',
                        'acceptTypes'  => 'image',
                        'labelText'    => '',
                        'labelClass'   => 'hidden',
                        'inputClass'   => 'w-full rounded-xl bg-black/30 border border-white/10 text-white text-sm px-3 py-2 focus:border-primary-400 focus:outline-none',
                    ])
                </div>
            </div>

            {{-- What to include in the generated kit (Palette is always on). --}}
            <div>
                <label class="block text-xs text-white/50 mb-1.5">What to include</label>
                <div class="flex flex-wrap gap-2">
                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-primary-400/40 bg-primary-500/10 text-xs text-white/80 cursor-not-allowed select-none"
                           title="Every brand kit needs a color palette">
                        <input type="checkbox" checked disabled class="rounded border-white/20 bg-black/30 text-primary-500">
                        Color palette
                    </label>
                    @foreach([
                        'fonts'       => 'Font pairing',
                        'voice'       => 'Voice & tone',
                        'taglines'    => 'Taglines',
                        'bio'         => 'About / bio',
                        'block_theme' => 'Link in Bio block theme',
                    ] as $ckey => $clabel)
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs cursor-pointer select-none transition-colors"
                               :class="components.{{ $ckey }} ? 'border-primary-400/40 bg-primary-500/10 text-white/90' : 'border-white/10 bg-black/20 text-white/50 hover:text-white/70'">
                            <input type="checkbox" x-model="components.{{ $ckey }}"
                                   class="rounded border-white/20 bg-black/30 text-primary-500 focus:ring-primary-400">
                            {{ $clabel }}
                        </label>
                    @endforeach
                </div>
                <p class="text-[11px] text-white/35 mt-1">Untick anything you don't need: the kit will only generate what's selected.</p>

                @if (!empty($assetTypes))
                    {{-- AI-generated brand images (Task #5612 asset engine surfaced
                         at generate time). Each is a separate flat coin charge, so
                         they default to off; the estimate includes ticked ones. --}}
                    <label class="block text-xs text-white/50 mt-3 mb-1.5">Brand images <span class="text-white/30">(optional, extra coins each)</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($assetTypes as $at)
                            <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs cursor-pointer select-none transition-colors"
                                   :class="assetTypes.{{ $at['type'] }} ? 'border-primary-400/40 bg-primary-500/10 text-white/90' : 'border-white/10 bg-black/20 text-white/50 hover:text-white/70'">
                                <input type="checkbox" x-model="assetTypes.{{ $at['type'] }}"
                                       class="rounded border-white/20 bg-black/30 text-primary-500 focus:ring-primary-400">
                                {{ $at['label'] }} <span class="text-white/35">· {{ number_format($at['cost']) }} {{ $at['cost'] === 1 ? 'coin' : 'coins' }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-white/35 mt-1">Ticked images are generated with the kit and appear in its Visual assets panel. You can also generate or redo any of them later from the kit.</p>
                @endif
            </div>

            {{-- Knowledge base picker. Its own <form> so the save/clear
                 default buttons round-trip server-side; the generate /
                 estimate AJAX calls read the checked inputs straight
                 from the DOM (see brandKits() below). --}}
            <form method="POST" action="{{ route('user.brand-kits.defaults.save') }}" data-kb-picker>
                @csrf
                @include('user.ai._partials.mind-picker', [
                    'mineMinds'     => $mineMinds,
                    'platformMind'  => $platformMind,
                    'selectedIds'   => old('mind_ids', $input['mind_ids'] ?? []),
                    'platformOptIn' => old('include_platform', $input['include_platform'] ?? false),
                    'hasDefault'    => $hasDefault,
                    'defaultFeature'=> $defaultFeature,
                    'defaultRoute'  => 'user.brand-kits',
                ])
            </form>

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

                    {{-- AI visual assets (Task #5612): lazy-loaded per-kit panel --}}
                    <div x-data="kitAssets({{ $kit->id }})" class="pt-2 border-t border-white/10">
                        <button type="button" @click="toggle()"
                                class="w-full flex items-center justify-between text-left text-xs text-white/70 hover:text-white py-1">
                            <span><i class="fas fa-images mr-1.5 text-primary-300"></i>AI visual assets</span>
                            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                        </button>

                        <div x-show="open" x-cloak class="mt-2 space-y-2">
                            <p x-show="loading" class="text-[11px] text-white/40">Loading…</p>
                            <p x-show="error" x-text="error" class="text-[11px] text-red-300"></p>
                            <template x-if="loaded && !allowed">
                                <p class="text-[11px] text-amber-300">Brand asset generation isn’t included on your plan.
                                    <a href="{{ route('user.upgrade') }}" class="underline">Upgrade</a></p>
                            </template>
                            <template x-if="loaded && allowed && !enabled">
                                <p class="text-[11px] text-amber-300">AI image generation is currently unavailable.</p>
                            </template>

                            <template x-for="t in types" :key="t.type">
                                <div class="rounded-xl border border-white/10 bg-black/20 p-3">
                                    <div class="flex items-start gap-3">
                                        <template x-if="t.asset && t.asset.image_url">
                                            <a :href="t.asset.image_url" target="_blank" class="shrink-0">
                                                <img :src="t.asset.image_url" :alt="t.label" class="w-14 h-14 rounded-lg object-cover border border-white/10">
                                            </a>
                                        </template>
                                        <template x-if="!t.asset">
                                            <div class="w-14 h-14 rounded-lg border border-dashed border-white/15 flex items-center justify-center text-white/25 shrink-0"><i class="fas fa-image"></i></div>
                                        </template>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs text-white font-medium" x-text="t.label"></p>
                                            <p class="text-[10px] text-white/40">
                                                <span x-text="t.cost + ' coins'"></span>
                                                <template x-if="t.asset"><span> · v<span x-text="t.asset.version"></span></span></template>
                                            </p>
                                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                <template x-if="!t.asset">
                                                    <button type="button" @click="generate(t, 'new')" :disabled="busyType"
                                                            class="px-2 py-1 rounded-md bg-primary-600 hover:bg-primary-500 text-white text-[10px] disabled:opacity-50">
                                                        <span x-text="busyType === t.type ? 'Generating…' : 'Generate'"></span>
                                                    </button>
                                                </template>
                                                <template x-if="t.asset">
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <button type="button" @click="generate(t, 'variation')" :disabled="busyType"
                                                                title="A fresh creative take on the same brief"
                                                                class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-white text-[10px] disabled:opacity-50">
                                                            <span x-text="busyType === t.type ? 'Working…' : 'Variation'"></span>
                                                        </button>
                                                        <button type="button" @click="openTweak(t)" :disabled="busyType"
                                                                title="Keep this direction, tell the AI what to change"
                                                                class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-white text-[10px] disabled:opacity-50">Alter…</button>
                                                        <a :href="t.asset.download_url || t.asset.image_url" target="_blank"
                                                           class="px-2 py-1 rounded-md bg-white/10 hover:bg-white/20 text-white text-[10px]">Download</a>
                                                        <template x-if="t.apply_targets && t.apply_targets.includes('kit_logo')">
                                                            <button type="button" @click="apply(t, 'kit_logo')" :disabled="busyType"
                                                                    class="px-2 py-1 rounded-md bg-emerald-600/80 hover:bg-emerald-500 text-white text-[10px] disabled:opacity-50">Set as kit logo</button>
                                                        </template>
                                                        <button type="button" @click="destroy(t)" :disabled="busyType"
                                                                class="px-2 py-1 rounded-md text-red-300/80 hover:text-red-300 text-[10px]">Delete</button>
                                                    </div>
                                                </template>
                                            </div>
                                            {{-- Alteration prompt --}}
                                            <div x-show="tweakType === t.type" x-cloak class="mt-2 flex items-center gap-1.5">
                                                <input type="text" x-model="tweakText" maxlength="500"
                                                       placeholder="e.g. make the background darker, larger logo"
                                                       class="flex-1 rounded-md bg-black/30 border border-white/10 text-white text-[11px] px-2 py-1">
                                                <button type="button" @click="generate(t, 'alteration')" :disabled="busyType"
                                                        class="px-2 py-1 rounded-md bg-primary-600 hover:bg-primary-500 text-white text-[10px] disabled:opacity-50">Go</button>
                                                <button type="button" @click="tweakType = null" class="text-white/40 text-[10px]">Cancel</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
function brandKits() {
    return {
        form: { prompt: '', website_url: '', logo_url: '' },
        // Output selection. Palette is always generated server-side; the
        // rest map to the "What to include" checkboxes above.
        components: { fonts: true, voice: true, taglines: true, bio: true, block_theme: true },
        // Optional AI brand images (all off by default — each costs coins).
        assetTypes: { @foreach ($assetTypes as $at){{ $at['type'] }}: false, @endforeach },
        busy: false,
        error: '',
        estimateText: '',
        _csrf() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''; },
        // Read the picked AI Minds straight from the picker
        // <form> so the AJAX body mirrors what a normal POST would send.
        _payload() {
            const root = document.querySelector('[data-kb-picker]');
            const mind_ids = root
                ? Array.from(root.querySelectorAll('input[name="mind_ids[]"]:checked')).map(el => parseInt(el.value, 10))
                : [];
            const cb = root ? root.querySelector('input[type="checkbox"][name="include_platform"]') : null;
            const include_platform = cb ? cb.checked : false;
            const components = ['palette'].concat(
                Object.keys(this.components).filter(k => this.components[k])
            );
            const asset_types = Object.keys(this.assetTypes).filter(k => this.assetTypes[k]);
            return { ...this.form, mind_ids, include_platform, components, asset_types };
        },
        async estimate() {
            this.error = '';
            this.estimateText = 'Estimating…';
            try {
                const res = await fetch('{{ route('user.brand-kits.estimate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(this._payload()),
                });
                const data = await res.json();
                if (!res.ok) { this.estimateText = ''; this.error = data.message || 'Could not estimate cost.'; return; }
                this.estimateText = `≈ ${data.estimated_credits} coins · balance ${data.balance}`;
            } catch (e) { this.estimateText = ''; this.error = 'Network error.'; }
        },
        async generate() {
            this.error = '';
            this.busy = true;
            try {
                const res = await fetch('{{ route('user.brand-kits.generate') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this._csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(this._payload()),
                });
                const data = await res.json();
                if (!res.ok) { this.busy = false; this.error = data.message || 'Generation failed.'; return; }
                window.location = data.redirect || '{{ route('user.brand-kits.index') }}';
            } catch (e) { this.busy = false; this.error = 'Network error.'; }
        },
    };
}

// Per-kit AI visual assets panel (Task #5612). Lazy-loads the catalog on
// first open; generate/variation/alteration are coin-charged server-side
// with auto-refund on failure.
function kitAssets(kitId) {
    const base = '{{ url('user/brand-kits') }}/' + kitId + '/assets';
    return {
        open: false, loaded: false, loading: false,
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
        openTweak(t) { this.tweakType = t.type; this.tweakText = ''; },
        async generate(t, mode) {
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
@endsection
