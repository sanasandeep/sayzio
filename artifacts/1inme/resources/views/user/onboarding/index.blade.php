@extends('user.layouts.app')
@section('title', 'Welcome to ' . config('app.name'))

@push('styles')
<style>[x-cloak]{display:none !important;}</style>
@endpush

@php
    // Precompute search haystacks once on the server, keyed by group name,
    // so Alpine can do simple .some()/.includes() without per-element JSON.parse.
    $groupHaystackMap = [];
    foreach ($grouped as $gName => $gItems) {
        $groupHaystackMap[$gName] = collect($gItems)
            ->map(fn($p) => strtolower($p['label'].' '.($p['blurb'] ?? '').' '.$p['slug']))
            ->values()->all();
    }
    // Bundle all Alpine init data here. We render it as a JSON script
    // block instead of inline in the x-data attribute, because @json
    // emits unescaped double quotes that would otherwise truncate the
    // attribute at the first " and break the whole Alpine component.
    $onboardingConfig = [
        'initialPersona'     => $current ?? '',
        'initialStep'        => $initialStep ?? 'welcome',
        'templatesUrl'       => route('user.onboarding.templates.list'),
        'savePersonaUrl'     => route('user.onboarding.persona.save'),
        'rememberPreviewUrl' => route('user.onboarding.preview.remember'),
        'dismissPreviewUrl'  => route('user.onboarding.preview.dismiss'),
        'csrf'               => csrf_token(),
        'personas'           => collect($personas)->map(fn($p) => ['slug' => $p['slug'], 'label' => $p['label']])->values()->all(),
        'haystacks'          => $groupHaystackMap,
        'resume'             => $resume ?? null,
    ];
    $firstName = auth()->user()->name ? explode(' ', auth()->user()->name)[0] : '';
@endphp
@section('content')
<script type="application/json" id="onboarding-config">@json($onboardingConfig)</script>
<div class="max-w-[1400px] mx-auto"
     x-data="onboarding(JSON.parse(document.getElementById('onboarding-config').textContent))">

    {{-- Persistent header: skip is available at every stage --}}
    <div class="flex items-start justify-between gap-4 mb-5">
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">
                Let's set up your page{{ $firstName ? ', ' . $firstName : '' }} 👋
            </h1>
            <p class="text-sm text-white/50">A few quick steps — nothing gets created until you preview and confirm.</p>
        </div>
        <form method="POST" action="{{ route('user.onboarding.go-to-dashboard') }}" class="shrink-0">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white/80 hover:text-white rounded-xl text-xs font-semibold transition">
                <i class="fas fa-th-large text-xs"></i> Skip setup
            </button>
        </form>
    </div>

    {{-- Visible progress indicator --}}
    @include('user.onboarding._stepper', ['steps' => $steps])

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm text-center">{{ session('error') }}</div>
    @endif

    {{-- ============================ STEP 1: WELCOME ============================ --}}
    <section x-show="stepKey === 'welcome'" x-cloak>
        <div class="glass rounded-2xl border border-white/10 p-6 sm:p-10 max-w-2xl mx-auto text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-blue-600/40 to-fuchsia-600/30 flex items-center justify-center mb-5">
                <i class="fas fa-wand-magic-sparkles text-2xl text-white"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">Welcome to {{ config('app.name') }}</h2>
            <p class="text-sm text-white/60 max-w-md mx-auto mb-6">
                We'll get your Link in Bio ready in three quick steps.
            </p>
            <ol class="text-left max-w-sm mx-auto space-y-3 mb-8">
                <li class="flex items-start gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-white/10 text-white/70 text-xs font-bold flex items-center justify-center">1</span>
                    <span class="text-sm text-white/70"><span class="text-white font-semibold">Pick your persona</span> — so we can suggest templates that fit you.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-white/10 text-white/70 text-xs font-bold flex items-center justify-center">2</span>
                    <span class="text-sm text-white/70"><span class="text-white font-semibold">Choose a template</span> — preview it live, then make it yours.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="shrink-0 w-6 h-6 rounded-full bg-white/10 text-white/70 text-xs font-bold flex items-center justify-center">3</span>
                    <span class="text-sm text-white/70"><span class="text-white font-semibold">Connect WhatsApp</span> <span class="text-white/40">(optional)</span> — sign in faster and stay reachable.</span>
                </li>
            </ol>
            <button type="button" @click="goStep('persona')"
                    class="w-full sm:w-auto px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition inline-flex items-center justify-center gap-2">
                Let's go <i class="fas fa-arrow-right text-xs"></i>
            </button>
        </div>
    </section>

    {{-- ============================ STEP 2: PERSONA ============================ --}}
    <section x-show="stepKey === 'persona'" x-cloak>
        <div class="glass rounded-2xl border border-white/10 p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-bold text-white">Who are you?</h2>
                    <p class="text-xs text-white/50">Pick the closest fit — we'll surface matching templates. You can change this later.</p>
                </div>
                <div class="relative sm:w-64 shrink-0">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/30"></i>
                    <input type="text" x-model="q" placeholder="Search personas…"
                           class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-sm text-white placeholder:text-white/30">
                </div>
            </div>

            @foreach($grouped as $groupName => $items)
                <div x-show="groupVisible(@js($groupName))" class="mb-4">
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-white/40 px-1 pt-1 pb-2">{{ $groupName }}</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                        @foreach($items as $p)
                            @php $haystack = strtolower($p['label'] . ' ' . ($p['blurb'] ?? '') . ' ' . $p['slug']); @endphp
                            <button type="button"
                                    data-persona-card
                                    x-show="q === '' || @js($haystack).includes(q.toLowerCase())"
                                    @click="selectPersona(@js($p['slug']))"
                                    :class="picked === @js($p['slug']) ? 'bg-blue-600/20 border-blue-500/60 ring-1 ring-blue-500/40' : 'border-white/10 hover:bg-white/5'"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition">
                                <span class="shrink-0 w-9 h-9 rounded-lg bg-gradient-to-br from-blue-600/40 to-fuchsia-600/30 flex items-center justify-center overflow-hidden">
                                    @if(!empty($p['image']))
                                        <img src="{{ $p['image'] }}" alt="" loading="lazy"
                                             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-block';"
                                             class="w-full h-full object-cover">
                                        <i class="fas {{ $p['icon'] }} text-xs text-white/70" style="display:none;"></i>
                                    @else
                                        <i class="fas {{ $p['icon'] }} text-xs text-white/70"></i>
                                    @endif
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-white truncate">{{ $p['label'] }}</span>
                                    <span class="block text-[11px] text-white/45 truncate">{{ $p['blurb'] }}</span>
                                </span>
                                <i class="fas fa-check text-[10px] text-blue-300" x-show="picked === @js($p['slug'])" x-cloak></i>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div x-show="q !== '' && noPersonaMatches()" x-cloak class="text-center text-xs text-white/30 py-3 px-2">
                No personas match "<span x-text="q"></span>".
            </div>

            <div class="flex items-center justify-between gap-3 pt-4 mt-2 border-t border-white/10">
                <button type="button" @click="goStep('welcome')"
                        class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white/70 hover:text-white text-xs font-semibold transition inline-flex items-center gap-2">
                    <i class="fas fa-arrow-left text-[10px]"></i> Back
                </button>
                <button type="button" @click="goStep('template')"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold transition inline-flex items-center gap-2">
                    <span x-text="picked ? 'Next: choose a template' : 'Skip — show all templates'"></span>
                    <i class="fas fa-arrow-right text-[10px]"></i>
                </button>
            </div>
        </div>
    </section>

    {{-- ============================ STEP 3: TEMPLATE ============================ --}}
    <section x-show="stepKey === 'template'" x-cloak>
        {{-- Resume hint: shown if the user previewed a template last time but
             didn't apply or skip. Disappears as soon as they act on it. --}}
        <div x-show="resume" x-cloak
             class="mb-3 flex items-center gap-3 px-3 py-2.5 rounded-xl bg-blue-500/10 border border-blue-500/30">
            <i class="fas fa-clock-rotate-left text-blue-300 text-xs shrink-0"></i>
            <p class="flex-1 text-xs text-white/80 min-w-0 truncate">
                Pick up where you left off — you were checking out
                <span class="font-semibold text-white" x-text="'&quot;' + (resume?.name || '') + '&quot;'"></span>
            </p>
            <button type="button" @click="resumePreview()"
                    class="shrink-0 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold inline-flex items-center gap-1.5">
                <i class="fas fa-eye"></i> Open again
            </button>
            <button type="button" @click="dismissResume()"
                    class="shrink-0 w-7 h-7 rounded-lg hover:bg-white/10 text-white/50 hover:text-white inline-flex items-center justify-center"
                    aria-label="Dismiss">
                <i class="fas fa-times text-[11px]"></i>
            </button>
        </div>

        <div class="flex items-center justify-between gap-3 mb-3 px-1">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" @click="goStep('persona')"
                        class="shrink-0 px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-white/70 hover:text-white text-[11px] font-semibold transition inline-flex items-center gap-1.5">
                    <i class="fas fa-arrow-left text-[10px]"></i> Back
                </button>
                <p class="text-xs text-white/50 truncate" x-show="!picked" x-cloak>Browse all templates below.</p>
                <p class="text-xs text-white/50 truncate" x-show="picked" x-cloak>
                    Showing templates for <span class="text-white font-semibold" x-text="pickedLabel()"></span>
                </p>
            </div>
            <button type="button" @click="clearPersona()" x-show="picked" x-cloak
                    class="shrink-0 text-[11px] text-white/40 hover:text-white/80 underline-offset-2 hover:underline">
                Show all templates
            </button>
        </div>

        <div class="relative" :class="loading ? 'opacity-50 pointer-events-none' : ''">
            <div x-ref="grid" id="onboarding-template-grid">
                {!! $initialGrid !!}
            </div>
            <div x-show="loading" x-cloak class="absolute inset-0 flex items-start justify-center pt-10 pointer-events-none">
                <div class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-xs text-white/70 backdrop-blur">
                    <i class="fas fa-spinner fa-spin mr-1"></i> Loading templates…
                </div>
            </div>
        </div>
    </section>

    {{-- LIVE PREVIEW MODAL --}}
    <div x-show="previewOpen" x-cloak
         class="fixed inset-0 z-[1000] bg-black/80 backdrop-blur-sm flex items-stretch md:items-center justify-center p-0 md:p-6"
         @keydown.escape.window="closePreview()">
        <div class="relative w-full md:max-w-[1100px] md:h-[88vh] h-full bg-slate-950 md:rounded-2xl border border-white/10 overflow-hidden flex flex-col"
             @click.outside="closePreview()">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-white/10 bg-white/[0.03]">
                <div class="flex items-center gap-2 min-w-0">
                    <button @click="closePreview()" class="shrink-0 w-8 h-8 rounded-lg hover:bg-white/10 text-white/70 hover:text-white inline-flex items-center justify-center" aria-label="Back">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-white/40">Live preview</p>
                        <p class="text-sm font-semibold text-white truncate" x-text="selectedTemplate?.name || ''"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <template x-if="selectedTemplate && selectedTemplate.locked">
                        <a :href="selectedTemplate.upgradeUrl" class="px-4 py-2 text-xs font-semibold rounded-xl bg-amber-500/15 text-amber-300 border border-amber-500/30 hover:bg-amber-500/25 transition inline-flex items-center gap-1.5">
                            <i class="fas fa-lock"></i>
                            <span>Upgrade to "<span x-text="selectedTemplate.tier"></span>" to use</span>
                        </a>
                    </template>
                    <template x-if="selectedTemplate && !selectedTemplate.locked">
                        <form method="POST" action="{{ route('user.onboarding.template.apply') }}">
                            @csrf
                            <input type="hidden" name="template_id" :value="selectedTemplate.id">
                            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-blue-600 hover:bg-blue-700 text-white transition inline-flex items-center gap-1.5">
                                <i class="fas fa-check"></i> Use this template
                            </button>
                        </form>
                    </template>
                </div>
            </div>

            {{-- Phone-frame iframe --}}
            <div class="flex-1 overflow-hidden bg-slate-900 flex items-center justify-center p-4">
                <div class="relative bg-black rounded-[36px] border-[10px] border-slate-800 shadow-2xl overflow-hidden w-full max-w-[420px] h-full max-h-[760px]">
                    <template x-if="previewOpen && selectedTemplate">
                        <iframe :src="selectedTemplate.previewUrl"
                                class="w-full h-full bg-white"
                                title="Template preview"
                                referrerpolicy="no-referrer"
                                sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function onboarding({ initialPersona, initialStep, templatesUrl, savePersonaUrl, rememberPreviewUrl, dismissPreviewUrl, csrf, personas, haystacks, resume }) {
    return {
        picked: initialPersona || '',
        personas: personas || [],
        haystacks: haystacks || {},
        q: '',
        loading: false,
        previewOpen: false,
        selectedTemplate: null,
        resume: resume || null,
        stepKey: initialStep || 'welcome',

        init() {
            // Server-rendered grid lives in $refs.grid — no init needed.
        },

        // Numeric position of the active client-side stage, consumed by the
        // shared _stepper partial. welcome=0, persona=1, template=2.
        get stepIndex() {
            return ({ welcome: 0, persona: 1, template: 2 })[this.stepKey] ?? 0;
        },

        goStep(key) {
            this.stepKey = key;
            try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0, 0); }
        },

        async selectPersona(slug) {
            const next = this.picked === slug ? '' : slug;
            this.picked = next;
            await this.refreshTemplates();
            // Persist persona choice in the background — failures are
            // non-fatal (the user can still proceed and template apply
            // doesn't depend on the persona being saved).
            try {
                await fetch(savePersonaUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: 'persona=' + encodeURIComponent(next || ''),
                });
            } catch (e) { /* ignore */ }
            // Picking a persona advances straight to the template stage so
            // the flow stays quick and discrete.
            if (next) this.goStep('template');
        },

        async clearPersona() {
            this.picked = '';
            await this.refreshTemplates();
        },

        async refreshTemplates() {
            this.loading = true;
            try {
                const url = templatesUrl + (this.picked ? '?persona=' + encodeURIComponent(this.picked) : '');
                const resp = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                });
                if (resp.ok && this.$refs.grid) {
                    this.$refs.grid.innerHTML = await resp.text();
                    // Re-scan injected DOM so @click handlers on the new
                    // template cards are wired up by Alpine.
                    if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                        window.Alpine.initTree(this.$refs.grid);
                    }
                }
            } catch (e) {
                // Leave existing grid as-is on failure.
            } finally {
                this.loading = false;
            }
        },

        pickedLabel() {
            const p = this.personas.find(p => p.slug === this.picked);
            return p ? p.label : '';
        },

        groupVisible(name) {
            if (this.q === '') return true;
            const q = this.q.toLowerCase();
            const list = this.haystacks[name] || [];
            for (let i = 0; i < list.length; i++) {
                if (list[i].includes(q)) return true;
            }
            return false;
        },

        noPersonaMatches() {
            if (this.q === '') return false;
            const q = this.q.toLowerCase();
            for (const name in this.haystacks) {
                const list = this.haystacks[name] || [];
                for (let i = 0; i < list.length; i++) {
                    if (list[i].includes(q)) return false;
                }
            }
            return true;
        },

        openPreview(tpl) {
            this.selectedTemplate = tpl;
            this.previewOpen = true;
            document.body.style.overflow = 'hidden';
            // Remember this preview server-side so a tab close mid-flow
            // can be resumed on the next visit. Fire-and-forget.
            this.rememberPreview(tpl?.id);
            // Hide the resume hint once they've engaged with a preview
            // (whether it's the same one or a different one).
            this.resume = null;
        },

        closePreview() {
            this.previewOpen = false;
            this.selectedTemplate = null;
            document.body.style.overflow = '';
        },

        async rememberPreview(templateId) {
            if (!templateId || !rememberPreviewUrl) return;
            try {
                await fetch(rememberPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: 'template_id=' + encodeURIComponent(templateId),
                });
            } catch (e) { /* non-fatal */ }
        },

        resumePreview() {
            if (!this.resume) return;
            this.openPreview(this.resume);
        },

        async dismissResume() {
            this.resume = null;
            if (!dismissPreviewUrl) return;
            try {
                await fetch(dismissPreviewUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
            } catch (e) { /* non-fatal */ }
        },
    };
}
</script>
@endsection
