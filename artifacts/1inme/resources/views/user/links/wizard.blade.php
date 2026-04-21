@extends('user.layouts.app')
@section('title', 'Create your Link in Bio')

@php
    /** @var \App\Modules\User\Models\BiolinkWizardDraft|null $draft */
    $step       = (int) ($step ?? 0);
    $categories = $categories ?? [];
    $pageTypes  = $pageTypes  ?? [];
    $industries = $industries ?? [];
    $questions  = $questions  ?? [];
    $answers    = $draft?->answers ?? [];

    $totalSteps  = 4;
    $progressPct = (int) round((($step + 1) / $totalSteps) * 100);

    $currentCategory = collect($categories)->firstWhere('slug', $draft?->category);
    $currentPageType = collect($pageTypes)->firstWhere('slug', $draft?->page_type);
    $currentIndustry = collect($industries)->firstWhere('slug', $draft?->industry);
@endphp

@section('content')
<div class="max-w-3xl mx-auto pb-20">

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
        <form method="POST" action="{{ route('user.links.wizard.save') }}">
            @csrf
            <input type="hidden" name="_action" value="pick_category">

            <h2 class="text-xl font-semibold text-white mb-1">What kind of page is this?</h2>
            <p class="text-sm text-white/50 mb-6">Pick the closest match — you can change anything later.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($categories as $cat)
                    <button type="submit" name="category" value="{{ $cat['slug'] }}"
                        class="group glass rounded-2xl p-5 text-left transition-all hover:bg-white/[0.06] hover:border-violet-500/40 border border-white/5
                               {{ $draft?->category === $cat['slug'] ? 'ring-2 ring-violet-500/50' : '' }}">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl bg-violet-500/15 text-violet-300 flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $cat['icon'] }} text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium">{{ $cat['label'] }}</div>
                                <div class="text-xs text-white/40 mt-1">{{ $cat['blurb'] }}</div>
                            </div>
                            <i class="fas fa-arrow-right text-white/20 group-hover:text-violet-400 transition-colors mt-2"></i>
                        </div>
                    </button>
                @endforeach
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 1 · Page type                                               --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step === 1)
        <form method="POST" action="{{ route('user.links.wizard.save') }}">
            @csrf
            <input type="hidden" name="_action" value="pick_page_type">

            <h2 class="text-xl font-semibold text-white mb-1">More specifically — what fits best?</h2>
            <p class="text-sm text-white/50 mb-6">We'll tailor the questions and the layout to this choice.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($pageTypes as $pt)
                    <button type="submit" name="page_type" value="{{ $pt['slug'] }}"
                        class="group glass rounded-2xl p-5 text-left transition-all hover:bg-white/[0.06] hover:border-violet-500/40 border border-white/5
                               {{ $draft?->page_type === $pt['slug'] ? 'ring-2 ring-violet-500/50' : '' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium">{{ $pt['label'] }}</div>
                                <div class="text-xs text-white/40 mt-1">{{ $pt['blurb'] }}</div>
                            </div>
                            <i class="fas fa-arrow-right text-white/20 group-hover:text-violet-400 transition-colors"></i>
                        </div>
                    </button>
                @endforeach
            </div>

            <div class="mt-6 flex items-center gap-3">
                @csrf
                <button type="submit" formmethod="POST" formaction="{{ route('user.links.wizard.save') }}"
                        name="_action" value="back" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                    <i class="fas fa-arrow-left mr-1.5 text-xs"></i> Back
                </button>
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 2 · Industry (optional)                                     --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step === 2)
        <form method="POST" action="{{ route('user.links.wizard.save') }}">
            @csrf
            <input type="hidden" name="_action" value="pick_industry">

            <h2 class="text-xl font-semibold text-white mb-1">What's your industry?</h2>
            <p class="text-sm text-white/50 mb-6">Just for picking the right placeholder image and accent — totally optional.</p>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($industries as $ind)
                    <button type="submit" name="industry" value="{{ $ind['slug'] }}"
                        class="glass rounded-xl px-4 py-3 text-sm text-white/80 hover:text-white hover:bg-white/[0.06] hover:border-violet-500/40 border border-white/5 text-center transition-all
                               {{ $draft?->industry === $ind['slug'] ? 'ring-2 ring-violet-500/50' : '' }}">
                        {{ $ind['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="mt-6 flex items-center justify-between gap-3">
                <button type="submit" name="_action" value="back" class="px-5 py-2.5 text-sm text-white/40 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                    <i class="fas fa-arrow-left mr-1.5 text-xs"></i> Back
                </button>
                <button type="submit" name="industry" value="" class="text-sm text-violet-300 hover:text-violet-200 underline-offset-2 hover:underline">
                    Skip this step
                </button>
            </div>
        </form>
    @endif

    {{-- ─────────────────────────────────────────────────────────────── --}}
    {{-- Step 3 · Detailed questions                                      --}}
    {{-- ─────────────────────────────────────────────────────────────── --}}
    @if($step >= 3)
        <form method="POST" action="{{ route('user.links.wizard.finish') }}" enctype="multipart/form-data" id="wizardFinishForm">
            @csrf

            <h2 class="text-xl font-semibold text-white mb-1">Tell us about your page</h2>
            <p class="text-sm text-white/50 mb-6">
                Skip what doesn't apply — we'll only add the blocks for what you fill in.
                You can polish anything in the editor afterwards.
            </p>

            <div class="glass rounded-2xl p-6 space-y-5">
                @foreach($questions as $q)
                    @php
                        $key   = $q['key'];
                        $type  = $q['type'] ?? 'text';
                        $label = $q['label'];
                        $val   = $answers[$key] ?? '';
                        $req   = !empty($q['required']);
                        $name  = "a[{$key}]";
                        $id    = 'fld_' . $key;
                    @endphp

                    <div>
                        <label for="{{ $id }}" class="block text-sm font-medium text-white/80 mb-1.5">
                            {{ $label }}
                            @if($req) <span class="text-violet-400">*</span> @endif
                        </label>

                        @if($type === 'textarea')
                            <textarea id="{{ $id }}" name="{{ $name }}" rows="3"
                                placeholder="{{ $q['placeholder'] ?? '' }}"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">{{ $val }}</textarea>

                        @elseif($type === 'select')
                            <select id="{{ $id }}" name="{{ $name }}"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">
                                <option value="" class="bg-[#0d0818]">— pick one —</option>
                                @foreach(($q['options'] ?? []) as $opt)
                                    <option value="{{ $opt['v'] }}" class="bg-[#0d0818]" @selected($val === $opt['v'])>{{ $opt['l'] }}</option>
                                @endforeach
                            </select>

                        @elseif($type === 'color')
                            <div class="flex items-center gap-3">
                                <input id="{{ $id }}" name="{{ $name }}" type="color"
                                    value="{{ $val ?: \App\Modules\User\Services\BiolinkWizardQuestions::defaultBrandColor($draft->category) }}"
                                    class="w-12 h-10 rounded-lg bg-white/5 border border-white/10 cursor-pointer">
                                <span class="text-xs text-white/40">Used for buttons & accents</span>
                            </div>

                        @elseif($type === 'image')
                            <div class="flex items-center gap-3">
                                <input id="{{ $id }}" name="a_files[{{ $key }}]" type="file" accept="image/*"
                                    class="block text-xs text-white/60 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-violet-600 file:text-white file:cursor-pointer hover:file:bg-violet-700">
                                @if(!empty($val) && is_string($val))
                                    <img src="{{ $val }}" class="w-10 h-10 rounded-lg object-cover border border-white/10" alt="">
                                @endif
                            </div>

                        @elseif(in_array($type, ['url','email','phone'], true))
                            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type === 'phone' ? 'tel' : $type }}"
                                value="{{ $val }}" placeholder="{{ $q['placeholder'] ?? '' }}"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">

                        @else
                            <input id="{{ $id }}" name="{{ $name }}" type="text" value="{{ $val }}"
                                placeholder="{{ $q['placeholder'] ?? '' }}"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none transition-all">
                        @endif

                        @if(!empty($q['help']))
                            <p class="text-xs text-white/30 mt-1">{{ $q['help'] }}</p>
                        @endif
                    </div>
                @endforeach
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
                    if (!el.name || !el.name.startsWith('a[')) return;
                    if (el.type === 'file') return;
                    const clone = document.createElement('input');
                    clone.type = 'hidden';
                    clone.name = el.name;
                    clone.value = el.value || '';
                    target.appendChild(clone);
                });
            }
        </script>
    @endif

</div>
@endsection
