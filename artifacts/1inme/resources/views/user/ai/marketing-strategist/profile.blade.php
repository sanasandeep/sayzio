@extends('user.layouts.app')
@section('title', 'Marketing Profile')

@section('content')
@php
    $toText = function ($bag) {
        if (!is_array($bag)) return trim((string) $bag);
        return implode("\n", array_map(fn ($v) => is_array($v) ? implode(', ', $v) : (string) $v, $bag));
    };
@endphp
<div class="max-w-3xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI',
        'title'    => 'Marketing profile',
        'subtitle' => 'Set this once. Every new strategy reuses it to ground the diagnosis, forecast and plan in what matters to you.',
        'balance'  => $balance,
    ])

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('user.ai.marketing-strategist.index') }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-arrow-left mr-1"></i> All strategies
        </a>
    </div>

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/[0.08] text-emerald-200 text-sm px-4 py-3 mb-4"><i class="fas fa-check-circle mr-1.5"></i>{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-red-500/25 bg-red-500/[0.08] text-red-200 text-sm px-4 py-3 mb-4">
            <i class="fas fa-triangle-exclamation mr-1.5"></i>{{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.ai.marketing-strategist.profile.save') }}"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-5">
        @csrf

        <div>
            <label for="target_audience" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-users text-sky-300 mr-1"></i> Who are you trying to reach?
            </label>
            <p class="text-xs text-white/45 mb-2">Your audience, niche, or ideal customer. One per line.</p>
            <textarea id="target_audience" name="target_audience" rows="5" maxlength="4000"
                      placeholder="e.g. Indie fitness coaches&#10;Women 25–40 in metro cities&#10;Small D2C skincare brands"
                      class="w-full rounded-xl bg-white/[0.04] border border-white/10 text-sm text-white placeholder-white/30 px-3 py-2.5 focus:outline-none focus:border-blue-500/50">{{ old('target_audience', $profile ? $toText($profile->target_audience) : '') }}</textarea>
        </div>

        <div>
            <label for="expectations" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-bullseye text-emerald-300 mr-1"></i> What do you want to achieve?
            </label>
            <p class="text-xs text-white/45 mb-2">Goals and outcomes you care about. One per line.</p>
            <textarea id="expectations" name="expectations" rows="5" maxlength="4000"
                      placeholder="e.g. Grow email subscribers to 5,000&#10;More bookings from my biolink&#10;Launch a paid community"
                      class="w-full rounded-xl bg-white/[0.04] border border-white/10 text-sm text-white placeholder-white/30 px-3 py-2.5 focus:outline-none focus:border-blue-500/50">{{ old('expectations', $profile ? $toText($profile->expectations) : '') }}</textarea>
        </div>

        <div>
            <label for="constraints" class="block text-sm font-medium text-white mb-1">
                <i class="fas fa-hand text-amber-300 mr-1"></i> Any constraints?
            </label>
            <p class="text-xs text-white/45 mb-2">Budget limits, platforms to avoid, brand rules. One per line.</p>
            <textarea id="constraints" name="constraints" rows="5" maxlength="4000"
                      placeholder="e.g. No paid ads for now&#10;Keep it Instagram + WhatsApp only&#10;Under ₹10k/month"
                      class="w-full rounded-xl bg-white/[0.04] border border-white/10 text-sm text-white placeholder-white/30 px-3 py-2.5 focus:outline-none focus:border-blue-500/50">{{ old('constraints', $profile ? $toText($profile->constraints) : '') }}</textarea>
        </div>

        <div class="flex items-center justify-end gap-2 pt-1">
            <a href="{{ route('user.ai.marketing-strategist.create') }}"
               class="px-4 py-2 rounded-xl bg-white/5 text-white/70 text-sm hover:bg-white/10">Skip for now</a>
            <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                <i class="fas fa-floppy-disk mr-1"></i> Save profile
            </button>
        </div>
    </form>
</div>
@endsection
