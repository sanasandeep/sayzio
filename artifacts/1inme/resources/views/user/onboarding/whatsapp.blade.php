@extends('user.layouts.app')
@section('title', 'Connect your WhatsApp')

@push('styles')
<style>[x-cloak]{display:none !important;}</style>
@endpush

@section('content')
@php $pendingNumber = $pending ?? null; @endphp
<div class="max-w-xl mx-auto px-4 py-8 sm:py-12"
     x-data="{ phase: '{{ $pendingNumber ? 'code' : 'number' }}' }">

    <div class="glass rounded-3xl border border-white/10 overflow-hidden">
        {{-- Header --}}
        <div class="p-6 sm:p-8 bg-gradient-to-br from-emerald-600/15 to-green-500/5 border-b border-white/10 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-500/15 flex items-center justify-center mb-4">
                <i class="fab fa-whatsapp text-emerald-300 text-3xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white">Add your WhatsApp number</h1>
            <p class="text-sm text-white/60 mt-2 max-w-md mx-auto">
                Verify a WhatsApp number to sign in faster with a one-time code — no password needed — and stay reachable.
                It only takes a moment, and you can skip for now.
            </p>
        </div>

        <div class="p-6 sm:p-8 space-y-5">
            {{-- Flash messages --}}
            @if(session('status'))
                <div class="px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('otp_demo_reveal'))
                <div class="px-3 py-2 rounded-lg bg-amber-500/10 border border-amber-400/30 text-amber-200 text-xs">
                    <i class="fas fa-flask text-[10px] mr-1"></i> {{ session('otp_demo_reveal') }}
                </div>
            @endif
            @if(session('error'))
                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            {{-- Phase 1: enter number --}}
            <form method="POST" action="{{ route('user.onboarding.whatsapp.send') }}" x-show="phase === 'number'" x-cloak class="space-y-3">
                @csrf
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60">WhatsApp number</label>
                <input type="tel" name="mobile" value="{{ old('mobile', $pendingNumber) }}" required
                       placeholder="+1 555 123 4567" autocomplete="tel"
                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white focus:border-emerald-400/50 focus:outline-none">
                <p class="text-[11px] text-white/40">Include your country code. We'll send a 6-digit code to this number on WhatsApp.</p>
                <button type="submit"
                        class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition">
                    <i class="fab fa-whatsapp mr-1.5"></i> Send verification code
                </button>
            </form>

            {{-- Phase 2: enter code --}}
            <form method="POST" action="{{ route('user.onboarding.whatsapp.verify') }}" x-show="phase === 'code'" x-cloak class="space-y-3">
                @csrf
                @if($pendingNumber)
                    <p class="text-xs text-white/50">Enter the 6-digit code we sent to <span class="font-semibold text-white">{{ $pendingNumber }}</span>.</p>
                @endif
                <label class="block text-xs font-semibold uppercase tracking-wider text-white/60">Verification code</label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required
                       placeholder="123456" autocomplete="one-time-code"
                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-white tracking-[0.4em] text-center font-mono focus:border-emerald-400/50 focus:outline-none">
                <button type="submit"
                        class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold transition">
                    <i class="fas fa-check mr-1.5"></i> Verify &amp; connect
                </button>
                <button type="button" @click="phase = 'number'"
                        class="w-full text-xs text-white/40 hover:text-white/70 transition">Use a different number</button>
            </form>

            {{-- Follow our channel --}}
            @if($channelUrl !== '')
                <div class="pt-5 border-t border-white/10">
                    <p class="text-sm font-semibold text-white">Follow our WhatsApp channel</p>
                    <p class="text-xs text-white/50 mt-0.5 mb-3">Get product updates, tips and announcements straight to WhatsApp.</p>
                    <a href="{{ $channelUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-emerald-400/30 text-emerald-200 text-sm font-semibold transition">
                        <i class="fab fa-whatsapp"></i> Follow our channel
                    </a>
                </div>
            @endif
        </div>

        {{-- Skip --}}
        <div class="px-6 sm:px-8 py-4 border-t border-white/10 flex justify-center">
            <form method="POST" action="{{ route('user.onboarding.whatsapp.skip') }}">
                @csrf
                <button type="submit" class="text-sm text-white/50 hover:text-white/80 transition">
                    Skip for now <i class="fas fa-arrow-right text-xs ml-1"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
