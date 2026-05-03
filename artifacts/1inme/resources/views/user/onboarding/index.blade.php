@extends('user.layouts.app')
@section('title', 'Welcome to ' . config('app.name'))

@push('styles')
<style>[x-cloak]{display:none !important;}</style>
@endpush

@section('content')
<div class="max-w-[1400px] mx-auto"
     x-data="onboarding({
        initialPersona: @json($current ?? ''),
        templatesUrl: @json(route('user.onboarding.templates.list')),
        savePersonaUrl: @json(route('user.onboarding.persona.save')),
        rememberPreviewUrl: @json(route('user.onboarding.preview.remember')),
        dismissPreviewUrl: @json(route('user.onboarding.preview.dismiss')),
        csrf: @json(csrf_token()),
        personas: @json(collect($personas)->map(fn($p) => ['slug' => $p['slug'], 'label' => $p['label']])->values()),
        resume: @json($resume ?? null),
     })">

    {{-- Header (no STEP X OF Y — it's one page now) --}}
    <div class="flex items-start justify-between gap-4 mb-5">
        <div class="flex-1">
            <h1 class="text-2xl sm:text-3xl font-bold text-white mb-1">
                Welcome{{ auth()->user()->name ? ', ' . explode(' ', auth()->user()->name)[0] : '' }} 👋
            </h1>
            <p class="text-sm text-white/50">Pick what fits — we'll surface matching templates. Nothing gets created until you preview and confirm.</p>
        </div>
        <form method="POST" action="{{ route('user.onboarding.go-to-dashboard') }}" class="shrink-0">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-white/20 text-white/80 hover:text-white rounded-xl text-xs font-semibold transition">
                <i class="fas fa-th-large text-xs"></i> Skip for now
            </button>
        </form>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm text-center">{{ session('error') }}</div>
    @endif

    {{-- Split layout: stacks on small screens, side-by-side from md up --}}
    <div class="grid grid-cols-1 lg:grid-cols-[340px,1fr] gap-5">

        {{-- LEFT: persona column --}}
        <aside class="glass rounded-2xl border border-white/10 p-3 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
            <div class="px-1.5 pb-2">
                <p class="text-[11px] font-bold uppercase tracking-wider text-white/40 mb-2">Who are you?</p>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-white/30"></i>
                    <input type="text" x-model="q" placeholder="Search personas…"
                           class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-sm text-white placeholder:text-white/30">
                </div>
            </div>

            @foreach($grouped as $groupName => $items)
                <div x-show="visibleInGroup($el) > 0">
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-white/40 px-1.5 pt-3 pb-1.5">{{ $groupName }}</h2>
                    <div class="space-y-1">
                        @foreach($items as $p)
                            @php $haystack = strtolower($p['label'] . ' ' . ($p['blurb'] ?? '') . ' ' . $p['slug']); @endphp
                            <button type="button"
                                    data-persona-card
                                    x-show="q === '' || @js($haystack).includes(q.toLowerCase())"
                                    @click="selectPersona(@js($p['slug']))"
                                    :class="picked === @js($p['slug']) ? 'bg-violet-600/20 border-violet-500/60 ring-1 ring-violet-500/40' : 'border-transparent hover:bg-white/5'"
                                    class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-lg border text-left transition">
                                <span class="shrink-0 w-8 h-8 rounded-lg bg-gradient-to-br from-violet-600/40 to-fuchsia-600/30 flex items-center justify-center overflow-hidden">
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
                                    <span class="block text-[12.5px] font-semibold text-white truncate">{{ $p['label'] }}</span>
                                    <span class="block text-[10.5px] text-white/45 truncate">{{ $p['blurb'] }}</span>
                                </span>
                                <i class="fas fa-check text-[10px] text-violet-300" x-show="picked === @js($p['slug'])" x-cloak></i>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div x-show="q !== '' && noPersonaMatches()" x-cloak class="text-center text-xs text-white/30 py-3 px-2">
                No personas match "<span x-text="q"></span>".
            </div>
        </aside>

        {{-- RIGHT: templates panel --}}
        <section class="min-w-0">
            {{-- Resume hint: shown if the user previewed a template last
                 time but didn't apply or skip. Disappears as soon as they
                 act on it (or when they apply / skip elsewhere). --}}
            <div x-show="resume" x-cloak
                 class="mb-3 flex items-center gap-3 px-3 py-2.5 rounded-xl bg-violet-500/10 border border-violet-500/30">
                <i class="fas fa-clock-rotate-left text-violet-300 text-xs shrink-0"></i>
                <p class="flex-1 text-xs text-white/80 min-w-0 truncate">
                    Pick up where you left off — you were checking out
                    <span class="font-semibold text-white" x-text="'&quot;' + (resume?.name || '') + '&quot;'"></span>
                </p>
                <button type="button" @click="resumePreview()"
                        class="shrink-0 px-3 py-1.5 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-[11px] font-semibold inline-flex items-center gap-1.5">
                    <i class="fas fa-eye"></i> Open again
                </button>
                <button type="button" @click="dismissResume()"
                        class="shrink-0 w-7 h-7 rounded-lg hover:bg-white/10 text-white/50 hover:text-white inline-flex items-center justify-center"
                        aria-label="Dismiss">
                    <i class="fas fa-times text-[11px]"></i>
                </button>
            </div>

            <div class="flex items-center justify-between mb-3 px-1">
                <p class="text-xs text-white/50" x-show="!picked" x-cloak>
                    <i class="fas fa-arrow-left mr-1 text-white/30"></i>
                    Pick a persona to see matching templates — or browse them all below.
                </p>
                <p class="text-xs text-white/50" x-show="picked" x-cloak>
                    Showing templates for <span class="text-white font-semibold" x-text="pickedLabel()"></span>
                </p>
                <button type="button" @click="clearPersona()" x-show="picked" x-cloak
                        class="text-[11px] text-white/40 hover:text-white/80 underline-offset-2 hover:underline">
                    Show all templates
                </button>
            </div>

            <div class="relative" :class="loading ? 'opacity-50 pointer-events-none' : ''">
                <div x-html="gridHtml" id="onboarding-template-grid">
                    {!! $initialGrid !!}
                </div>
                <div x-show="loading" x-cloak class="absolute inset-0 flex items-start justify-center pt-10 pointer-events-none">
                    <div class="px-3 py-2 rounded-xl bg-white/10 border border-white/10 text-xs text-white/70 backdrop-blur">
                        <i class="fas fa-spinner fa-spin mr-1"></i> Loading templates…
                    </div>
                </div>
            </div>
        </section>
    </div>

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
                            <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-violet-600 hover:bg-violet-700 text-white transition inline-flex items-center gap-1.5">
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
function onboarding({ initialPersona, templatesUrl, savePersonaUrl, rememberPreviewUrl, dismissPreviewUrl, csrf, personas, resume }) {
    return {
        picked: initialPersona || '',
        personas: personas || [],
        q: '',
        loading: false,
        gridHtml: '',
        previewOpen: false,
        selectedTemplate: null,
        resume: resume || null,

        init() {
            // gridHtml is populated server-side on first paint via x-html
            // initial value; clear so Alpine doesn't overwrite on render.
            this.gridHtml = document.getElementById('onboarding-template-grid')?.innerHTML || '';
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
                if (resp.ok) {
                    this.gridHtml = await resp.text();
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

        visibleInGroup(el) {
            return el.querySelectorAll('[data-persona-card]:not([style*="display: none"])').length;
        },

        noPersonaMatches() {
            return document.querySelectorAll('[data-persona-card]:not([style*="display: none"])').length === 0;
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
