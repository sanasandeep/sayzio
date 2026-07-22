@extends('user.layouts.app')
@section('title', 'Competitor Biolink Teardown')

@section('content')
<div class="max-w-3xl mx-auto" x-data="teardownBuild()">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.teardown.create') }}" class="text-white/30 hover:text-white transition-colors" title="Run another teardown"><i class="fas fa-arrow-left"></i></a>
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <i class="fas fa-magnifying-glass-chart text-blue-400"></i> Teardown Results
            </h1>
            <p class="text-xs text-white/40 mt-0.5 truncate">{{ $teardown->competitor_url }}</p>
        </div>
    </div>

    @if(session('error'))
        <div class="glass rounded-xl p-4 mb-5 border border-red-500/20 text-sm text-red-300">{{ session('error') }}</div>
    @endif
    @if(session('success'))
        <div class="glass rounded-xl p-4 mb-5 border border-emerald-500/20 text-sm text-emerald-300">{{ session('success') }}</div>
    @endif

    @if($teardown->status === 'processing')
        <div class="glass rounded-2xl p-8 text-center">
            <i class="fas fa-spinner fa-spin text-2xl text-blue-300 mb-3"></i>
            <p class="text-white/60 text-sm">Still analyzing this page, refresh in a moment.</p>
        </div>
    @elseif($teardown->status === 'failed')
        <div class="glass rounded-2xl p-8 text-center">
            <i class="fas fa-triangle-exclamation text-2xl text-red-400 mb-3"></i>
            <p class="text-white/60 text-sm">{{ $teardown->error ?: "We couldn't analyze this page." }}</p>
            <a href="{{ route('user.links.teardown.create') }}" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-medium transition-all">Try another URL</a>
        </div>
    @else
        {{-- Overall score --}}
        <div class="glass rounded-2xl p-6 mb-5 flex items-center gap-6">
            <div class="shrink-0 w-20 h-20 rounded-full flex items-center justify-center border-4"
                 style="border-color: {{ ($analysis['overall_score'] ?? 0) >= 70 ? 'rgba(52,211,153,0.5)' : (($analysis['overall_score'] ?? 0) >= 40 ? 'rgba(251,191,36,0.5)' : 'rgba(248,113,113,0.5)') }}">
                <span class="text-2xl font-bold text-white">{{ $analysis['overall_score'] ?? 0 }}</span>
            </div>
            <div>
                <p class="text-sm text-white/40 mb-1">Overall effectiveness</p>
                <p class="text-white/80 text-sm">{{ $analysis['summary'] ?? '' }}</p>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-5 mb-5">
            {{-- Strengths --}}
            <div class="glass rounded-2xl p-5">
                <h3 class="text-sm font-semibold text-emerald-300 mb-3"><i class="fas fa-circle-check mr-1.5"></i> Strengths</h3>
                @forelse($analysis['strengths'] ?? [] as $item)
                    <p class="text-xs text-white/60 mb-2 flex gap-2"><span class="text-emerald-400/60">•</span> {{ $item }}</p>
                @empty
                    <p class="text-xs text-white/30">None identified.</p>
                @endforelse
            </div>

            {{-- Weaknesses --}}
            <div class="glass rounded-2xl p-5">
                <h3 class="text-sm font-semibold text-red-300 mb-3"><i class="fas fa-circle-xmark mr-1.5"></i> Weaknesses</h3>
                @forelse($analysis['weaknesses'] ?? [] as $item)
                    <p class="text-xs text-white/60 mb-2 flex gap-2"><span class="text-red-400/60">•</span> {{ $item }}</p>
                @empty
                    <p class="text-xs text-white/30">None identified.</p>
                @endforelse
            </div>
        </div>

        {{-- Missing elements --}}
        <div class="glass rounded-2xl p-5 mb-5">
            <h3 class="text-sm font-semibold text-amber-300 mb-3"><i class="fas fa-list-check mr-1.5"></i> Missing Elements</h3>
            @forelse($analysis['missing_elements'] ?? [] as $item)
                <p class="text-xs text-white/60 mb-2 flex gap-2"><span class="text-amber-400/60">•</span> {{ $item }}</p>
            @empty
                <p class="text-xs text-white/30">Nothing obviously missing.</p>
            @endforelse
        </div>

        {{-- CTA quality --}}
        <div class="glass rounded-2xl p-5 mb-5">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-white/80"><i class="fas fa-bullseye text-blue-300 mr-1.5"></i> Call-to-Action Quality</h3>
                <span class="text-sm font-semibold text-white">{{ $analysis['cta']['quality_score'] ?? 0 }}/100</span>
            </div>
            <p class="text-xs text-white/50">
                {{ ($analysis['cta']['present'] ?? false) ? '' : 'No clear CTA detected. ' }}{{ $analysis['cta']['feedback'] ?? '' }}
            </p>
        </div>

        {{-- Recommendations --}}
        @if(!empty($analysis['recommendations']))
        <div class="glass rounded-2xl p-5 mb-5">
            <h3 class="text-sm font-semibold text-blue-300 mb-3"><i class="fas fa-lightbulb mr-1.5"></i> Recommendations</h3>
            <ol class="space-y-2">
                @foreach($analysis['recommendations'] as $i => $item)
                    <li class="text-xs text-white/60 flex gap-2"><span class="text-blue-400/70 font-medium">{{ $i + 1 }}.</span> {{ $item }}</li>
                @endforeach
            </ol>
        </div>
        @endif

        {{-- Build a better version --}}
        <div class="glass rounded-2xl p-6 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-white font-medium"><i class="fas fa-wand-magic-sparkles text-blue-400 mr-1.5"></i> Ready to beat this page?</p>
                <p class="text-xs text-white/40 mt-1">Coin balance: <span class="text-white/70">{{ $balance }}</span></p>
            </div>
            <form method="POST" action="{{ route('user.links.teardown.build', $teardown) }}" @submit="building = true">
                @csrf
                <button type="submit" :disabled="building"
                        class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-blue-500/20 whitespace-nowrap">
                    <i class="fas fa-spinner fa-spin mr-1.5" x-show="building"></i>
                    <i class="fas fa-wand-magic-sparkles mr-1.5 text-xs" x-show="!building"></i>
                    <span x-text="building ? 'Building…' : 'Build me a better version'"></span>
                </button>
            </form>
        </div>
    @endif
</div>

<script>
function teardownBuild() {
    return { building: false };
}
</script>
@endsection
