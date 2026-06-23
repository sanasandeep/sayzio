@extends('user.layouts.app')
@section('title', 'Create your Link in Bio')

@php
    /** @var \App\Modules\User\Models\BiolinkWizardDraft|null $draft */
    $step             = (int) ($step ?? 0);
    $categories       = $categories ?? [];
    $pageTypes        = $pageTypes  ?? [];
    $industriesByType = $industriesByType ?? [];
    $basics           = $basics     ?? [];
    $additional       = $additional ?? [];
    $answers          = $draft?->answers ?? [];

    $totalSteps  = 4;
    $progressPct = (int) round((($step + 1) / $totalSteps) * 100);

    $currentCategory = collect($categories)->firstWhere('slug', $draft?->category);
    $currentPageType = collect($pageTypes)->firstWhere('slug', $draft?->page_type);
    // The chosen niche label, if any, looked up in the current page type's
    // refinement list (the old standalone industry step, folded into step 2).
    $currentIndustry = $draft?->page_type
        ? collect($industriesByType[$draft->page_type] ?? [])->firstWhere('slug', $draft?->industry)
        : null;
@endphp

@section('content')
<div class="max-w-3xl mx-auto pb-20">

    {{-- Shared reveal animation — matches the categorized picker on the
         Create Link page so the guided wizard's cards feel consistent.
         Disabled under prefers-reduced-motion. --}}
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .lt-card-reveal { opacity: 0; transform: translateY(12px); animation: ltCardReveal .5s cubic-bezier(.21,.6,.35,1) forwards; }
            @keyframes ltCardReveal { to { opacity: 1; transform: none; } }
        }
    </style>


    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') }}" class="text-white/30 hover:text-white transition-colors" title="Skip the wizard">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-white">Build your Link in Bio</h1>
            <p class="text-xs text-white/40 mt-0.5">
                Answer a few questions and we'll generate a ready-to-use page —
                tweak any block afterwards.
                <a href="{{ route('user.links.biolink.create') }}" class="text-violet-400 hover:underline ml-1">Skip wizard, start blank</a>
            </p>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="glass rounded-2xl p-4 mb-6">
        <div class="flex items-center justify-between text-xs text-white/50 mb-2">
            <span>Step {{ $step + 1 }} of {{ $totalSteps }}</span>
            <span class="hidden sm:inline">
                @if($currentCategory)<span class="text-white/70">{{ $currentCategory['label'] }}</span>@endif
                @if($currentPageType) · <span class="text-white/70">{{ $currentPageType['label'] }}</span>@endif
                @if($currentIndustry) · <span class="text-white/70">{{ $currentIndustry['label'] }}</span>@endif
            </span>
        </div>
        <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
            <div class="h-full bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-full transition-all" style="width: {{ $progressPct }}%"></div>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 0 · Category                                                --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step === 0)
        {{-- Card scanner shortcut: lets the user kick off the whole wizard
             from a snapshot of a business card / brochure. The scanner
             seeds a draft and redirects back to this wizard. --}}
        <a href="{{ route('user.contacts.scan.create', ['from' => 'wizard']) }}"
           class="block mb-6 p-4 rounded-2xl border transition-all hover:bg-white/[0.06]"
           style="background:linear-gradient(135deg,rgba(124,58,237,.10),rgba(236,72,153,.08));border-color:rgba(124,58,237,.30);">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:linear-gradient(135deg,#7c3aed,#ec4899);color:white;">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-white font-medium">Have a business card or brochure?
                        <span class="ml-1 text-[10px] uppercase tracking-wider text-fuchsia-300">AI</span>
                    </div>
                    <div class="text-xs text-white/50 mt-0.5">Snap a photo and we'll prefill the wizard with the contact details, links and logo.</div>
                </div>
                <i class="fas fa-arrow-right text-white/30"></i>
            </div>
        </a>

        <form method="POST" action="{{ route('user.links.wizard.save') }}">
            @csrf
            <input type="hidden" name="_action" value="pick_category">

            <h2 class="text-xl font-semibold text-white mb-1">What kind of page is this?</h2>
            <p class="text-sm text-white/50 mb-6">Pick the closest match — you can change anything later.</p>

            @php $catIndex = 0; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($categories as $cat)
                    <button type="submit" name="category" value="{{ $cat['slug'] }}"
                        class="lt-card-reveal group text-left h-full rounded-2xl border p-4 flex flex-col gap-3 transition-all duration-200 motion-safe:hover:-translate-y-1
                               {{ $draft?->category === $cat['slug']
                                    ? 'border-violet-500 bg-violet-500/10 ring-2 ring-violet-500/30 shadow-lg shadow-violet-500/10'
                                    : 'border-white/10 hover:border-white/20 hover:bg-white/[0.04] hover:shadow-lg hover:shadow-black/20' }}"
                        style="animation-delay: {{ min($catIndex++ * 45, 540) }}ms">
                        <div class="relative rounded-xl overflow-hidden border border-white/5 bg-white/[0.02] aspect-[5/3]">
                            <img src="{{ url('/wizard-placeholders/' . $cat['slug'] . '.svg') }}"
                                 alt="{{ $cat['label'] }} preview" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-[1.06]"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="absolute inset-0 hidden items-center justify-center bg-violet-500/15 text-violet-300">
                                <i class="fas {{ $cat['icon'] }} text-3xl"></i>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-violet-500/15 text-violet-300 flex items-center justify-center flex-shrink-0">
                                    <i class="fas {{ $cat['icon'] }}"></i>
                                </div>
                                <div class="text-sm font-semibold text-white truncate">{{ $cat['label'] }}</div>
                            </div>
                            <i class="fas fa-arrow-right text-white/20 group-hover:text-violet-400 transition-colors flex-shrink-0"></i>
                        </div>
                        <div class="text-xs text-white/50 leading-relaxed">{{ $cat['blurb'] }}</div>
                    </button>
                @endforeach
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 1 · Page type                                               --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Profile type + optional niche refinement (folded in). The page-type
         cards now select-then-Continue (Alpine) so we can show an inline,
         optional niche picker for combos that have a specific industries()
         list before advancing. --}}
    @if($step === 1)
        <form method="POST" action="{{ route('user.links.wizard.save') }}"
              x-data="{ pt: @js($draft?->page_type ?? ''), ind: @js($draft?->industry ?? '') }">
            @csrf
            <input type="hidden" name="page_type" :value="pt">
            <input type="hidden" name="industry" :value="ind">

            <h2 class="text-xl font-semibold text-white mb-1">More specifically — what fits best?</h2>
            <p class="text-sm text-white/50 mb-6">We'll tailor the questions and the layout to this choice.</p>

            @php $ptIndex = 0; @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($pageTypes as $pt)
                    @php $ptIcon = \App\Modules\User\Services\BiolinkWizardQuestions::pageTypeIcon($draft->category, $pt['slug']); @endphp
                    <button type="button" @click="pt = @js($pt['slug']); ind = ''"
                        class="lt-card-reveal group relative overflow-hidden rounded-2xl p-5 text-left border transition-all duration-200 motion-safe:hover:-translate-y-1"
                        :class="pt === @js($pt['slug'])
                                    ? 'border-violet-500 bg-violet-500/10 ring-2 ring-violet-500/30 shadow-lg shadow-violet-500/10'
                                    : 'border-white/10 bg-white/[0.03] hover:border-violet-500/40 hover:bg-white/[0.06] hover:shadow-lg hover:shadow-black/20'"
                        style="animation-delay: {{ min($ptIndex++ * 45, 540) }}ms">
                        {{-- accent glow corner --}}
                        <div class="pointer-events-none absolute -top-10 -right-10 w-28 h-28 rounded-full bg-gradient-to-br from-violet-500/20 to-fuchsia-500/10 blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="relative flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 text-lg transition-all duration-200"
                                 :class="pt === @js($pt['slug'])
                                            ? 'bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/30'
                                            : 'bg-violet-500/15 text-violet-300 group-hover:bg-violet-500/25 motion-safe:group-hover:scale-105'">
                                <i class="fas {{ $ptIcon }}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-semibold">{{ $pt['label'] }}</div>
                                <div class="text-xs text-white/50 mt-1 leading-relaxed">{{ $pt['blurb'] }}</div>
                            </div>
                            <i class="fas fa-check text-violet-400 flex-shrink-0 mt-1" x-show="pt === @js($pt['slug'])" x-cloak></i>
                        </div>
                    </button>
                @endforeach
            </div>

            {{-- Optional niche refinement — only rendered for the selected page
                 type when it has a specific industries() list. Drives placeholder
                 imagery/accent; entirely skippable (leave none selected). --}}
            @foreach($industriesByType as $ptSlug => $inds)
                <div x-show="pt === @js($ptSlug)" x-cloak class="mt-7">
                    <div class="flex items-center gap-2 mb-1">
                        <h3 class="text-sm font-semibold text-white">Refine your niche</h3>
                        <span class="text-[10px] uppercase tracking-wider text-white/30">optional</span>
                    </div>
                    <p class="text-xs text-white/40 mb-3">Just for picking the right placeholder image and accent — skip if none fit.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($inds as $ind)
                            <button type="button" @click="ind = (ind === @js($ind['slug']) ? '' : @js($ind['slug']))"
                                class="group relative overflow-hidden rounded-2xl px-4 py-4 border text-center transition-all duration-200 motion-safe:hover:-translate-y-0.5 flex flex-col items-center gap-2"
                                :class="ind === @js($ind['slug'])
                                            ? 'border-violet-500 bg-violet-500/10 ring-2 ring-violet-500/30 shadow-lg shadow-violet-500/10'
                                            : 'border-white/10 bg-white/[0.03] hover:border-violet-500/40 hover:bg-white/[0.06]'">
                                <span class="w-10 h-10 rounded-xl flex items-center justify-center text-base transition-all duration-200"
                                      :class="ind === @js($ind['slug'])
                                                ? 'bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg shadow-violet-500/30'
                                                : 'bg-violet-500/15 text-violet-300 group-hover:bg-violet-500/25 motion-safe:group-hover:scale-105'">
                                    <i class="fas {{ $ind['icon'] }}"></i>
                                </span>
                                <span class="text-xs font-medium leading-tight"
                                      :class="ind === @js($ind['slug']) ? 'text-white' : 'text-white/80 group-hover:text-white'">{{ $ind['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="mt-7 flex items-center justify-between gap-3">
                <button type="submit" name="_action" value="back" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                    <i class="fas fa-arrow-left mr-1.5 text-xs"></i> Back
                </button>
                <button type="submit" name="_action" value="pick_page_type" x-bind:disabled="!pt"
                        class="bg-violet-600 hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                    Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                </button>
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 2 · Basic profile & branding                                --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step === 2)
        <form method="POST" action="{{ route('user.links.wizard.save') }}" enctype="multipart/form-data" id="wizardBasicsForm">
            @csrf
            <input type="hidden" name="_action" value="save_basics">

            <h2 class="text-xl font-semibold text-white mb-1">Set up your profile &amp; branding</h2>
            <p class="text-sm text-white/50 mb-6">
                The essentials visitors see first — your name, a line about you, a photo and your accent colour.
            </p>

            <div class="space-y-5">
                <section class="lt-card-reveal glass rounded-2xl overflow-hidden">
                    <header class="flex items-center gap-3 px-6 py-4 border-b border-white/5 bg-white/[0.02]">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500/25 to-fuchsia-500/15 text-violet-300 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-id-card-clip"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-white">Basic profile &amp; branding</div>
                            <div class="text-xs text-white/40">Who the page is for.</div>
                        </div>
                    </header>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-5">
                        @forelse($basics as $q)
                            @include('user.links.partials.wizard-field', ['q' => $q, 'answers' => $answers, 'draft' => $draft])
                        @empty
                            <p class="text-sm text-white/40 sm:col-span-2">Nothing to set up here — continue to add your content.</p>
                        @endforelse
                    </div>
                </section>

                @include('user.links.partials.wizard-resources')
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="submit" name="_action" value="back"
                            class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        <i class="fas fa-arrow-left mr-1.5 text-xs"></i> Back
                    </button>
                    <button type="submit" name="_action" value="restart"
                            class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        Start over
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" name="_action" value="save_and_exit"
                            class="px-5 py-2.5 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        Save &amp; exit
                    </button>
                    <button type="submit" name="_action" value="save_basics"
                            class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                        Continue <i class="fas fa-arrow-right ml-1.5 text-xs"></i>
                    </button>
                </div>
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 3 · Additional content                                      --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step >= 3)
        <form method="POST" action="{{ route('user.links.wizard.finish') }}" enctype="multipart/form-data" id="wizardFinishForm">
            @csrf

            <h2 class="text-xl font-semibold text-white mb-1">Add your content</h2>
            <p class="text-sm text-white/50 mb-6">
                Skip what doesn't apply — we'll only add the blocks for what you fill in.
                You can polish anything in the editor afterwards.
            </p>

            @php
                // Split the flat question set into friendly sections so the form
                // scans as a few short groups instead of one long list. The
                // identity fields (always merged in first by baseIdentity) form
                // "The basics"; everything else is "Links & details".
                $identityKeys = ['display_name', 'headline', 'bio', 'avatar', 'brand_color'];
                $basics = $details = [];
                foreach ($questions as $q) {
                    if (in_array($q['key'], $identityKeys, true)) { $basics[] = $q; } else { $details[] = $q; }
                }
                $groups = [];
                if (!empty($basics))  { $groups[] = ['title' => 'The basics',      'icon' => 'fa-id-card-clip', 'desc' => 'Who the page is for.',          'items' => $basics]; }
                if (!empty($details)) { $groups[] = ['title' => 'Links & details',  'icon' => 'fa-sliders',      'desc' => 'Add what applies — skip the rest.', 'items' => $details]; }

                // Leading icon per field — by input type, with a couple of
                // key-aware overrides for nicer affordances.
                $fieldIcon = function (array $q): string {
                    $byKey = [
                        'instagram' => 'fa-hashtag', 'tiktok' => 'fa-hashtag', 'twitter' => 'fa-at',
                        'whatsapp' => 'fa-comment-dots', 'phone' => 'fa-phone', 'address' => 'fa-location-dot',
                        'hours' => 'fa-clock', 'discount_code' => 'fa-ticket',
                    ];
                    if (isset($byKey[$q['key']])) { return $byKey[$q['key']]; }
                    return match ($q['type'] ?? 'text') {
                        'textarea' => 'fa-align-left',
                        'select'   => 'fa-list-ul',
                        'color'    => 'fa-palette',
                        'image'    => 'fa-image',
                        'url'      => 'fa-link',
                        'email'    => 'fa-envelope',
                        'phone'    => 'fa-phone',
                        default    => 'fa-pen',
                    };
                };
            @endphp

            <div class="space-y-5">
                @foreach($groups as $gi => $group)
                    <section class="lt-card-reveal glass rounded-2xl overflow-hidden" style="animation-delay: {{ min($gi * 80, 240) }}ms">
                        <header class="flex items-center gap-3 px-6 py-4 border-b border-white/5 bg-white/[0.02]">
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500/25 to-fuchsia-500/15 text-violet-300 flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $group['icon'] }}"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-white">{{ $group['title'] }}</div>
                                <div class="text-xs text-white/40">{{ $group['desc'] }}</div>
                            </div>
                        </header>

                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-5">
                            @foreach($group['items'] as $q)
                                @php
                                    $key   = $q['key'];
                                    $type  = $q['type'] ?? 'text';
                                    $label = $q['label'];
                                    $val   = $answers[$key] ?? '';
                                    $req   = !empty($q['required']);
                                    $name  = "a[{$key}]";
                                    $id    = 'fld_' . $key;
                                    $icon  = $fieldIcon($q);
                                    // Long fields span the full row for breathing room.
                                    $wide  = in_array($type, ['textarea'], true);
                                @endphp

                                <div class="{{ $wide ? 'sm:col-span-2' : '' }}">
                                    <label for="{{ $id }}" class="flex items-center gap-2 text-sm font-medium text-white/80 mb-1.5">
                                        <i class="fas {{ $icon }} text-violet-300/70 text-xs w-4 text-center"></i>
                                        <span>{{ $label }}</span>
                                        @if($req) <span class="text-violet-400">*</span> @endif
                                    </label>

                                    @if($type === 'textarea')
                                        <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
                                            placeholder="{{ $q['placeholder'] ?? '' }}"
                                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">{{ $val }}</textarea>

                                    @elseif($type === 'select')
                                        <select id="{{ $id }}" name="{{ $name }}"
                                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                                            <option value="" class="bg-[#0d0818]">— pick one —</option>
                                            @foreach(($q['options'] ?? []) as $opt)
                                                <option value="{{ $opt['v'] }}" class="bg-[#0d0818]" @selected($val === $opt['v'])>{{ $opt['l'] }}</option>
                                            @endforeach
                                        </select>

                                    @elseif($type === 'color')
                                        <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-3 py-2">
                                            <input id="{{ $id }}" name="{{ $name }}" type="color"
                                                value="{{ $val ?: \App\Modules\User\Services\BiolinkWizardQuestions::defaultBrandColor($draft->category) }}"
                                                class="w-10 h-9 rounded-lg bg-transparent border-0 cursor-pointer">
                                            <span class="text-xs text-white/40">Used for buttons &amp; accents</span>
                                        </div>

                                    @elseif($type === 'image')
                                        <div class="flex items-center gap-3 bg-white/5 border border-white/10 rounded-xl px-3 py-2">
                                            @if(!empty($val) && is_string($val))
                                                <img src="{{ $val }}" class="w-10 h-10 rounded-lg object-cover border border-white/10 flex-shrink-0" alt="">
                                            @else
                                                <span class="w-10 h-10 rounded-lg bg-violet-500/15 text-violet-300 flex items-center justify-center flex-shrink-0"><i class="fas fa-image"></i></span>
                                            @endif
                                            <input id="{{ $id }}" name="a_files[{{ $key }}]" type="file" accept="image/*"
                                                class="block text-xs text-white/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-violet-600 file:text-white file:cursor-pointer hover:file:bg-violet-700">
                                        </div>

                                    @elseif(in_array($type, ['url','email','phone'], true))
                                        <div class="relative">
                                            <i class="fas {{ $icon }} absolute left-3.5 top-1/2 -translate-y-1/2 text-white/25 text-xs pointer-events-none"></i>
                                            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'phone' ? 'tel' : $type }}"
                                                value="{{ $val }}" placeholder="{{ $q['placeholder'] ?? '' }}"
                                                class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                                        </div>

                                    @else
                                        <input id="{{ $id }}" name="{{ $name }}" type="text" value="{{ $val }}"
                                            placeholder="{{ $q['placeholder'] ?? '' }}"
                                            class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40 outline-none transition-all">
                                    @endif

                                    @if(!empty($q['help']))
                                        <p class="text-xs text-white/30 mt-1">{{ $q['help'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                @include('user.links.partials.wizard-resources')
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    {{-- Sibling form for back/save-and-exit so we don't submit the finish form. --}}
                    <button type="button" onclick="document.getElementById('wizardSideForm').elements['_action'].value='back'; document.getElementById('wizardSideForm').submit();"
                            class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        <i class="fas fa-arrow-left mr-1.5 text-xs"></i> Back
                    </button>
                    <button type="button" onclick="document.getElementById('wizardSideForm').elements['_action'].value='restart'; document.getElementById('wizardSideForm').submit();"
                            class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        Start over
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" onclick="document.getElementById('wizardSideForm').elements['_action'].value='save_and_exit'; mergeAnswersInto(document.getElementById('wizardSideForm')); document.getElementById('wizardSideForm').submit();"
                            class="px-5 py-2.5 text-sm text-white/60 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                        Save & exit
                    </button>
                    @if(!empty($aiEnabled))
                        {{-- Auto-draft with AI: same form, different action route. --}}
                        <button type="submit" formaction="{{ route('user.links.wizard.ai-draft') }}"
                                class="inline-flex items-center gap-1.5 border border-fuchsia-500/40 bg-fuchsia-500/10 hover:bg-fuchsia-500/20 text-fuchsia-200 px-5 py-2.5 rounded-xl text-sm font-medium transition-all">
                            <i class="fas fa-wand-magic-sparkles text-xs"></i> Auto-draft with AI
                        </button>
                    @endif
                    <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20">
                        Generate my page <i class="fas fa-magic ml-1.5 text-xs"></i>
                    </button>
                </div>
            </div>
        </form>

        {{-- Sibling form for non-finish actions (back/restart/save-and-exit). --}}
        <form id="wizardSideForm" method="POST" action="{{ route('user.links.wizard.save') }}" class="hidden">
            @csrf
            <input type="hidden" name="_action" value="back">
        </form>

        <script>
            // For "Save & exit" we need to copy the user's currently-typed answers
            // (text/select/color/textarea) into the side form so the server persists
            // them. File inputs aren't included — they only flow on Generate.
            function mergeAnswersInto(target) {
                const main = document.getElementById('wizardFinishForm');
                if (!main) return;
                main.querySelectorAll('input, textarea, select').forEach(el => {
                    if (!el.name) return;
                    if (el.type === 'file') return;
                    // Typed answer fields.
                    if (el.name.startsWith('a[')) {
                        const clone = document.createElement('input');
                        clone.type = 'hidden';
                        clone.name = el.name;
                        clone.value = el.value || '';
                        target.appendChild(clone);
                        return;
                    }
                    // AI auto-draft resource selections — copy checked brains/files
                    // and the platform-knowledge flag so Save & exit persists them.
                    if (el.name === 'ai_mind_ids[]' || el.name === 'file_ids[]') {
                        if (el.checked) {
                            const clone = document.createElement('input');
                            clone.type = 'hidden';
                            clone.name = el.name;
                            clone.value = el.value;
                            target.appendChild(clone);
                        }
                        return;
                    }
                    if (el.name === 'include_platform_mind') {
                        const clone = document.createElement('input');
                        clone.type = 'hidden';
                        clone.name = el.name;
                        clone.value = el.checked ? '1' : '0';
                        target.appendChild(clone);
                    }
                });
                // Sentinel so the controller treats the copied selections as
                // authoritative (lets a fully-cleared picker actually clear).
                const sentinel = document.createElement('input');
                sentinel.type = 'hidden';
                sentinel.name = '_resources_present';
                sentinel.value = '1';
                target.appendChild(sentinel);
            }
        </script>
    @endif

    {{-- ─── Autosave (browser + server) ──────────────────────────────────
         Shared by both content steps (basics + additional). Persists answers
         locally on every change so refreshes / accidental tab closes don't
         lose work, and pushes the same payload to the server every 5s when
         there are unsaved edits. The local cache is MERGED across steps so
         step 2 (basics) and step 3 (additional) never clobber each other. --}}
    @if($step >= 2)
        <script>
            (function () {
                const main = document.getElementById('wizardFinishForm')
                          || document.getElementById('wizardBasicsForm');
                if (!main) return;

                const STORAGE_KEY = 'biolink-wizard-draft:v1';
                const DRAFT_URL   = @json(route('user.links.wizard.draft'));
                const CSRF        = @json(csrf_token());

                function readCache() {
                    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); }
                    catch (e) { return {}; }
                }

                // Only the scalar (a[key]) fields present in THIS step's form.
                function readForm() {
                    const out = {};
                    main.querySelectorAll('input, textarea, select').forEach(el => {
                        if (!el.name || !el.name.startsWith('a[')) return;
                        if (el.type === 'file') return;
                        const m = el.name.match(/^a\[([^\]]+)\](?:\[|$)/);
                        if (!m) return;
                        const k = m[1];
                        if (el.name === `a[${k}]`) {
                            out[k] = el.value || '';
                        }
                    });
                    return out;
                }

                // Merge this step's fields onto the existing cross-step cache so
                // we don't drop answers captured on the other content step.
                function persistLocal() {
                    try {
                        const merged = Object.assign(readCache(), readForm());
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(merged));
                    } catch (e) { /* quota or private mode */ }
                }

                // Restore from localStorage if the server didn't already persist
                // these fields (the server's saved value wins).
                try {
                    Object.entries(readCache()).forEach(([k, v]) => {
                        const el = main.querySelector(`[name="a[${k}]"]`);
                        if (el && el.type !== 'file' && !el.value) el.value = v;
                    });
                } catch (e) { /* ignore corrupt cache */ }

                // Read the AI auto-draft resource selections so they autosave
                // alongside the answers and survive a refresh / resume.
                function readResources() {
                    const minds = [];
                    main.querySelectorAll('input[name="ai_mind_ids[]"]').forEach(el => {
                        if (el.checked) minds.push(el.value);
                    });
                    const files = [];
                    main.querySelectorAll('input[name="file_ids[]"]').forEach(el => {
                        if (el.checked) files.push(el.value);
                    });
                    const plat = main.querySelector('input[name="include_platform_mind"]');
                    return {
                        ai_mind_ids: minds,
                        file_ids: files,
                        include_platform_mind: plat && plat.checked ? 1 : 0,
                        _resources_present: 1,
                    };
                }

                let dirty = false;
                main.addEventListener('input', () => { dirty = true; persistLocal(); });
                // Checkboxes (resource picker) fire change; flag dirty so the
                // selection flushes on the next autosave tick.
                main.addEventListener('change', () => { dirty = true; });

                // Periodic server autosave — only fires when there's something
                // new since the last successful flush.
                setInterval(() => {
                    if (!dirty) return;
                    const answers = readForm();
                    dirty = false;
                    fetch(DRAFT_URL, {
                        method: 'PATCH',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(Object.assign({ answers }, readResources())),
                    }).catch(() => { dirty = true; /* retry next tick */ });
                }, 5000);

                // Clear cache once we've successfully generated.
                if (main.id === 'wizardFinishForm') {
                    main.addEventListener('submit', () => {
                        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
                    });
                }
            })();
        </script>
    @endif

</div>
@endsection
