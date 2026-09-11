@extends('public.layouts.site')
@section('title', 'Manage subscriptions')
@section('content')
@include('partials.marketing-system')
@php
    $waChannel = trim((string) ($whatsappChannelUrl ?? ''));
    $waNumber  = trim((string) ($whatsappNumber ?? ''));
    $waStopHref = $waNumber !== ''
        ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $waNumber) . '?text=' . rawurlencode('STOP')
        : '';

    // How many channels actually exist. The page used to render all three
    // whatever the configuration was, so with neither WhatsApp channel set up
    // -- which is how it is live right now -- a visitor came here to
    // unsubscribe and met two dead cards reading "Channel link not
    // configured." and "DM number not configured.".
    //
    // That is an internal admin state on a public page, and it is worse than
    // useless here: it advertises two channels nobody can be subscribed to,
    // on the one page whose entire job is helping somebody leave. A channel
    // that is not set up is a channel you cannot be receiving, so there is
    // nothing to unsubscribe from and nothing to say.
    $channelCount = 1 + ($waChannel !== '' ? 1 : 0) + ($waStopHref !== '' ? 1 : 0);
@endphp

<section class="sy sec-first pt-16 pb-10">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        {{-- Rule 1: was blue text on a blue tint. --}}
        <div class="sy-eyebrow">Subscription centre</div>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight" style="color: var(--sy-ink);">Manage your Sayzio subscriptions</h1>
        <p class="mt-3 text-sm sm:text-base leading-relaxed max-w-2xl mx-auto" style="color: var(--sy-ink-2);">
            @if($channelCount > 1)
                Each channel is opt-in and independent; unsubscribing from one won't touch the others. Pick the one you'd like to stop.
            @else
                Enter the address you subscribed with and we'll email you a one-click unsubscribe link. No login, no password.
            @endif
        </p>
    </div>
</section>

<section class="sy sec-rule pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="sy-grid {{ $channelCount === 1 ? 'sy-solo' : '' }}" style="--sy-min: 260px;">

            {{-- Email newsletter --}}
            <div class="sy-card">
                <div class="sy-head">
                    <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                        <i class="fas fa-envelope-open-text" aria-hidden="true" style="font-size:12px;"></i> Email newsletter
                    </span>
                </div>
                <p class="sy-blurb" style="margin-top:0;">
                    Enter the address you subscribed with. We'll send a one-click unsubscribe link to that inbox.
                </p>

                @if(session('subscriptions_manage_status'))
                    <div class="sy-notice" style="margin-top:18px;" role="status">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <span><b>{{ session('subscriptions_manage_status') }}</b></span>
                    </div>
                @endif

                @error('email')
                    <div class="sy-notice sy-notice--bad" style="margin-top:18px;" role="alert">
                        <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                        <span>{{ $message }}</span>
                    </div>
                @enderror

                <form method="POST" action="{{ route('site.subscriptions.manage.send') }}"
                      class="sy-foot sy-field" novalidate>
                    @csrf
                    {{-- Honeypot. Hidden from people, filled by bots. --}}
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                           class="hidden" aria-hidden="true">
                    <label class="sr-only" for="manage-email">Email address</label>
                    <input type="email" id="manage-email" name="email" required
                           autocomplete="email"
                           placeholder="you@example.com"
                           value="{{ old('email') }}"
                           class="sy-input">
                    <button type="submit" class="sy-cta">
                        Email me an unsubscribe link <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="sy-foot-note">The link works once, and expires.</div>
            </div>

            @if($waChannel !== '')
                {{-- WhatsApp Channel --}}
                <div class="sy-card">
                    <div class="sy-head">
                        <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                            <i class="fab fa-whatsapp" aria-hidden="true" style="font-size:12px;"></i> WhatsApp Channel
                        </span>
                    </div>
                    <p class="sy-blurb" style="margin-top:0;">
                        Channel followers are managed by WhatsApp itself; open the channel and tap <strong style="color: var(--sy-ink);">Unfollow</strong>. We never see your number.
                    </p>
                    <div class="sy-foot">
                        <a href="{{ $waChannel }}" target="_blank" rel="noopener noreferrer" class="sy-cta sy-cta--ghost">
                            Open the channel <i class="fas fa-arrow-up-right-from-square"></i>
                        </a>
                        <div class="sy-foot-note">Takes effect immediately.</div>
                    </div>
                </div>
            @endif

            @if($waStopHref !== '')
                {{-- WhatsApp DM --}}
                <div class="sy-card">
                    <div class="sy-head">
                        <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                            <i class="fas fa-comments" aria-hidden="true" style="font-size:12px;"></i> WhatsApp DM
                        </span>
                    </div>
                    <p class="sy-blurb" style="margin-top:0;">
                        Reply <strong style="color: var(--sy-ink);">STOP</strong> in your conversation with us and the direct messages stop. The button opens that chat with STOP already typed.
                    </p>
                    <div class="sy-foot">
                        <a href="{{ $waStopHref }}" target="_blank" rel="noopener noreferrer" class="sy-cta sy-cta--ghost">
                            Open the chat <i class="fas fa-arrow-up-right-from-square"></i>
                        </a>
                        <div class="sy-foot-note">You still send the message yourself.</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="max-w-3xl mx-auto mt-9 text-center">
            <p class="text-sm" style="color: var(--sy-ink-2);">
                Need help? <a href="{{ route('site.contact') }}" class="sy-link">Contact us</a>,
                or read the <a href="{{ route('site.privacy') }}" class="sy-link">privacy policy</a>.
            </p>
        </div>
    </div>
</section>
@endsection
