{{--
    Right-panel content for the single-page onboarding flow.
    Cards are click-targets that open the live preview drawer (handled
    by the surrounding Alpine `onboarding()` component) — no biolink
    is created until the user confirms inside the preview.
--}}
@if($recommended->isEmpty() && $others->isEmpty())
    <div class="glass rounded-2xl border border-white/10 p-10 text-center">
        <i class="fas fa-layer-group text-3xl text-violet-400 mb-3"></i>
        <h3 class="text-base font-semibold text-white mb-1">No templates available yet</h3>
        <p class="text-sm text-white/50 mb-4">Start with a blank page — you can apply a template anytime later.</p>
        <form method="POST" action="{{ route('user.onboarding.template.apply') }}">
            @csrf
            <button type="submit" name="skip" value="1" class="inline-block px-5 py-2 bg-violet-600 hover:bg-violet-700 text-white rounded-xl text-sm font-semibold">Continue to dashboard</button>
        </form>
    </div>
@else
    @if($recommended->isNotEmpty())
        <div class="mb-3 flex items-center gap-2">
            <i class="fas fa-sparkles text-violet-400 text-xs"></i>
            <h2 class="text-sm font-semibold text-white">
                Recommended @if($personaLabel) for {{ $personaLabel }} @endif
            </h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 mb-6">
            @foreach($recommended as $tpl)
                @include('user.onboarding._template_card', ['tpl' => $tpl, 'lockedFn' => $lockedFn])
            @endforeach
        </div>
    @endif

    @if($others->isNotEmpty())
        <div class="{{ $recommended->isNotEmpty() ? 'mt-6' : '' }} mb-3 flex items-center gap-2">
            <h2 class="text-sm font-semibold text-white/70">{{ $recommended->isEmpty() ? 'All templates' : 'Browse all templates' }}</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
            @foreach($others as $tpl)
                @include('user.onboarding._template_card', ['tpl' => $tpl, 'lockedFn' => $lockedFn])
            @endforeach
        </div>
    @endif
@endif
