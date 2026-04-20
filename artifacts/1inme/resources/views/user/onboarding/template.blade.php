@extends('user.layouts.app')
@section('title', 'Pick a starting template')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="text-center mb-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-violet-400 mb-2">Step 2 of 2</p>
        <h1 class="text-3xl font-bold text-white mb-2">Pick a starting template</h1>
        <p class="text-sm text-white/50">
            @if($personaLabel)
                Hand-picked for {{ $personaLabel }}s — or browse all and skip if you'd rather start blank.
            @else
                Pick something that's close to what you want. You can edit anything afterwards.
            @endif
        </p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm text-center">{{ session('error') }}</div>
    @endif

    @if($recommended->isEmpty() && $others->isEmpty())
        <div class="glass rounded-2xl border border-white/10 p-12 text-center">
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
                <h2 class="text-sm font-semibold text-white">Recommended for you</h2>
            </div>
            @include('user.onboarding._template_grid', ['items' => $recommended])
        @endif

        @if($others->isNotEmpty())
            <div class="mt-10 mb-3 flex items-center gap-2">
                <h2 class="text-sm font-semibold text-white/70">{{ $recommended->isEmpty() ? 'All templates' : 'More templates' }}</h2>
            </div>
            @include('user.onboarding._template_grid', ['items' => $others])
        @endif

        <div class="mt-10 flex items-center justify-between border-t border-white/5 pt-6">
            <a href="{{ route('user.onboarding.persona') }}" class="text-sm text-white/40 hover:text-white">
                <i class="fas fa-arrow-left text-xs mr-1"></i> Change persona
            </a>
            <form method="POST" action="{{ route('user.onboarding.template.apply') }}">
                @csrf
                <button type="submit" name="skip" value="1" class="text-sm text-white/40 hover:text-white px-4 py-2">
                    Skip — start blank
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
