@extends('user.layouts.app')
@section('title', 'Welcome to ' . config('app.name'))

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="text-center mb-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-violet-400 mb-2">Step 1 of 2</p>
        <h1 class="text-3xl font-bold text-white mb-2">Welcome{{ auth()->user()->name ? ', ' . explode(' ', auth()->user()->name)[0] : '' }} 👋</h1>
        <p class="text-sm text-white/50">Tell us what you do — we'll line up the templates that fit best.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm text-center">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('user.onboarding.persona.save') }}" x-data="{ picked: '{{ $current ?? '' }}' }">
        @csrf
        <input type="hidden" name="persona" :value="picked">

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
            @foreach($personas as $p)
                <button type="button"
                        @click="picked = '{{ $p['slug'] }}'"
                        :class="picked === '{{ $p['slug'] }}' ? 'border-violet-500 bg-violet-600/15 ring-2 ring-violet-500/40' : 'border-white/10 bg-white/5 hover:border-white/30'"
                        class="text-left rounded-2xl border p-4 transition group">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 bg-violet-500/15 text-violet-300">
                        <i class="fas {{ $p['icon'] }} text-base"></i>
                    </div>
                    <p class="text-sm font-semibold text-white mb-1">{{ $p['label'] }}</p>
                    <p class="text-xs text-white/40 leading-snug">{{ $p['blurb'] }}</p>
                </button>
            @endforeach
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" name="skip" value="1"
                    formnovalidate
                    class="text-sm text-white/40 hover:text-white px-4 py-2 transition">
                Skip for now
            </button>

            <button type="submit"
                    :disabled="!picked"
                    :class="picked ? 'bg-violet-600 hover:bg-violet-700 text-white' : 'bg-white/5 text-white/30 cursor-not-allowed'"
                    class="px-6 py-2.5 rounded-xl text-sm font-semibold transition">
                Continue <i class="fas fa-arrow-right text-xs ml-1"></i>
            </button>
        </div>
    </form>
</div>
@endsection
