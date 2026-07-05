@extends('user.layouts.app')
@section('title', 'Contact privacy')

@section('content')
@php
    $fields = [
        'share_phone'    => ['label' => 'Phone number', 'desc' => 'Your number, plus call / text / WhatsApp-by-number / FaceTime shortcuts.'],
        'share_email'    => ['label' => 'Email address', 'desc' => 'Your email, when available on a lookup.'],
        'share_location' => ['label' => 'Exact location', 'desc' => 'Precise map location(s) you\'ve shared on your biolink.'],
        'share_socials'  => ['label' => 'Socials & other channels', 'desc' => 'Instagram, WhatsApp, Telegram and other links pulled from your biolink.'],
    ];
@endphp
<div class="max-w-xl mx-auto px-4 py-8 sm:py-12"
     x-data="{ stepIndex: {{ (int) ($activeIndex ?? 0) }} }">

    {{-- Visible progress indicator (shared with the onboarding wizard) --}}
    @includeWhen(!empty($steps), 'user.onboarding._stepper', ['steps' => $steps ?? []])

    <div class="glass rounded-3xl border border-white/10 overflow-hidden">
        {{-- Header --}}
        <div class="p-6 sm:p-8 bg-gradient-to-br from-blue-600/15 to-indigo-500/5 border-b border-white/10 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-blue-500/15 flex items-center justify-center mb-4">
                <i class="fas fa-user-shield text-blue-300 text-3xl"></i>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-white">Who can see your contact info?</h1>
            <p class="text-sm text-white/60 mt-2 max-w-md mx-auto">
                By default, everything stays visible when a stranger looks you up via caller-ID or search.
                You can hide any of these — people who've already saved you as a contact (and you yourself)
                always see everything, no matter what you pick.
            </p>
        </div>

        <div class="p-6 sm:p-8 space-y-5">
            @if($errors->any())
                <div class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-400/30 text-red-200 text-sm">
                    @foreach($errors->all() as $error) <p>{{ $error }}</p> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('user.onboarding.privacy.save') }}" class="space-y-5">
                @csrf
                <div class="divide-y divide-white/10">
                    @foreach($fields as $key => $meta)
                        @php $current = $prefs[$key] ?? null; @endphp
                        <div class="py-4 first:pt-0">
                            <div class="text-sm font-semibold text-white">{{ $meta['label'] }}</div>
                            <div class="text-xs mt-0.5 mb-2 text-white/50">{{ $meta['desc'] }}</div>
                            <div class="flex flex-wrap items-center gap-4">
                                <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer text-white/60">
                                    <input type="radio" name="{{ $key }}" value="" class="accent-blue-500" @checked($current === null)>
                                    Shown <span class="opacity-60">(default)</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer text-white/60">
                                    <input type="radio" name="{{ $key }}" value="1" class="accent-blue-500" @checked($current === true)>
                                    Always shown
                                </label>
                                <label class="inline-flex items-center gap-1.5 text-xs cursor-pointer text-white/60">
                                    <input type="radio" name="{{ $key }}" value="0" class="accent-blue-500" @checked($current === false)>
                                    Hidden from strangers
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="submit"
                        class="w-full px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition">
                    <i class="fas fa-check mr-1.5"></i> Save preferences
                </button>
            </form>

            <p class="text-[11px] text-white/40 text-center">
                You can fine-tune individual channels (e.g. hide just one social link) anytime from
                Settings &gt; Contact Privacy.
            </p>
        </div>

        {{-- Skip --}}
        <div class="px-6 sm:px-8 py-4 border-t border-white/10 flex justify-center">
            <form method="POST" action="{{ route('user.onboarding.privacy.skip') }}">
                @csrf
                <button type="submit" class="text-sm text-white/50 hover:text-white/80 transition">
                    Skip for now <i class="fas fa-arrow-right text-xs ml-1"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
