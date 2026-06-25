@extends('public.layouts.site')
@section('title', 'Manage subscriptions')
@section('content')
@php
    $waChannel = trim((string) ($whatsappChannelUrl ?? ''));
    $waNumber  = trim((string) ($whatsappNumber ?? ''));
    $waStopHref = $waNumber !== ''
        ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $waNumber) . '?text=' . rawurlencode('STOP')
        : '';
@endphp
<section class="pt-16 pb-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/15 border border-violet-400/30 text-[11px] font-bold uppercase tracking-wider text-violet-200 mb-3">
            <i class="fas fa-sliders"></i> Subscription Center
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold text-white">Manage your Sayzio subscriptions</h1>
        <p class="mt-3 text-gray-400 text-sm sm:text-base leading-relaxed max-w-2xl mx-auto">
            Each channel is opt-in and independent — unsubscribing from one won't touch the others. Pick the channel you'd like to stop and follow the steps below.
        </p>
    </div>
</section>

<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-4 md:grid-cols-3">
        {{-- Email newsletter --}}
        <div class="bg-violet-500/10 border border-violet-400/20 rounded-2xl p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-violet-500/20 text-violet-200">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">Email newsletter</h2>
                    <p class="text-[11px] text-gray-400">One-click opt-out</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 leading-relaxed mb-4">
                Enter the email address you subscribed with. We'll send a one-click unsubscribe link to that inbox.
            </p>

            @if(session('subscriptions_manage_status'))
                <div class="mb-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-xs">
                    <i class="fas fa-circle-check"></i> {{ session('subscriptions_manage_status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('site.subscriptions.manage.send') }}"
                  class="mt-auto flex flex-col gap-2"
                  novalidate>
                @csrf
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                       class="hidden" aria-hidden="true">
                <label class="sr-only" for="manage-email">Email address</label>
                <input type="email" id="manage-email" name="email" required
                       placeholder="you@example.com"
                       value="{{ old('email') }}"
                       class="px-4 py-2.5 rounded-full bg-white/5 border border-white/15 text-white placeholder-white/40 text-sm focus:outline-none focus:border-violet-400/60">
                <button type="submit"
                        class="px-5 py-2.5 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane text-xs"></i> Email me an unsubscribe link
                </button>
            </form>
            @error('email')
                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
            @enderror
        </div>

        {{-- WhatsApp Channel --}}
        <div class="bg-emerald-500/10 border border-emerald-400/20 rounded-2xl p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-200">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">WhatsApp Channel</h2>
                    <p class="text-[11px] text-gray-400">Unfollow from inside WhatsApp</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 leading-relaxed mb-4">
                Channel followers are managed by WhatsApp directly — open the channel and tap <strong class="text-white">Unfollow</strong>. We never see your number.
            </p>
            @if($waChannel !== '')
                <a href="{{ $waChannel }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="mt-auto px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fab fa-whatsapp"></i> Open channel
                </a>
            @else
                <p class="mt-auto text-xs text-gray-500 italic">Channel link not configured.</p>
            @endif
        </div>

        {{-- WhatsApp DM --}}
        <div class="bg-emerald-500/10 border border-emerald-400/20 rounded-2xl p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-200">
                    <i class="fas fa-comments"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white">WhatsApp DM</h2>
                    <p class="text-[11px] text-gray-400">Reply STOP to the chat</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 leading-relaxed mb-4">
                Reply <strong class="text-white">STOP</strong> in your conversation with us and we'll stop sending direct messages. Tap below to open the chat with STOP pre-filled.
            </p>
            @if($waStopHref !== '')
                <a href="{{ $waStopHref }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="mt-auto px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane text-xs"></i> Open chat with STOP
                </a>
            @else
                <p class="mt-auto text-xs text-gray-500 italic">DM number not configured.</p>
            @endif
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 mt-10 text-center">
        <p class="text-xs text-gray-500">
            Need help? <a href="{{ route('site.contact') }}" class="text-violet-300 hover:underline">Contact us</a>
            or read our <a href="{{ route('site.privacy') }}" class="text-violet-300 hover:underline">privacy policy</a>.
        </p>
    </div>
</section>
@endsection
