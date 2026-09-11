{{--
    End-of-page "Subscribe" block. Inputs:
      $source   (required, string) — page slug used to tag submissions, e.g. 'features'.
                Tagged in DB as `subscribe-block:<source>`. Also used to scope the
                success flash so only the card the visitor just submitted shows the
                confirmation banner.
      $heading  (optional)
      $subtext  (optional)

    Renders one channel card per configured channel (Email newsletter, WhatsApp
    Channel, WhatsApp DM). The WhatsApp cards self-hide when the admin has not
    configured that channel, so the block degrades to email-only rather than
    advertising a channel that does not exist. A "Manage subscriptions" link
    below opens the Unsubscribe Center.

    Rebuilt on the marketing system. What it was: a blue-tinted panel with a
    blue icon disc, a blue eyebrow pill, an input with a 5%-white fill and a
    15%-white border -- which on a light page is an invisible box -- and a
    saturated blue button running the full width of the section. Five pieces of
    colour for one email field.
--}}
@include('partials.marketing-system')
@php
    $__sbSource   = $source ?? 'page';
    $__sbHeading  = $heading ?? 'Stay in the loop with Sayzio';
    $__sbSubtext  = $subtext ?? 'Pick the channel that fits you. Product updates, growth playbooks, and the occasional template. No spam, opt out any time.';
    $__sbSubmitSource = 'subscribe-block:' . $__sbSource;
    $__sbFlashKey = 'newsletter_success_' . $__sbSource;
    $__sbWaUrl    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_channel_url', ''));
    $__sbWaNum    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_number', ''));
    $__sbWaMsg    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_message', ''));
    $__sbWaDmHref = $__sbWaNum !== ''
        ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $__sbWaNum) . ($__sbWaMsg !== '' ? ('?text=' . rawurlencode($__sbWaMsg)) : '')
        : '';
    $__sbHasWaChan = $__sbWaUrl !== '';
    $__sbHasWaDm   = $__sbWaDmHref !== '';
    $__sbCardCount = 1 + ($__sbHasWaChan ? 1 : 0) + ($__sbHasWaDm ? 1 : 0);

    // Do not promise a channel that is not set up.
    //
    // Twelve pages pass their own subtext and almost all of them say some
    // version of "pick email, WhatsApp Channel, or DM" -- copy written when
    // all three were expected to exist. Neither WhatsApp channel is
    // configured today, so every one of those pages offers the visitor a
    // choice of three and then shows them one.
    //
    // Fixed here rather than in the twelve callers, because the callers are
    // not wrong about anything they own: they describe what the newsletter
    // CONTAINS, and the channel sentence rides along with it. This drops
    // that sentence only while the channels are missing, so the day Sana
    // configures WhatsApp the original copy becomes true again on its own.
    if (! $__sbHasWaChan && ! $__sbHasWaDm && preg_match('/whatsapp/i', $__sbSubtext)) {
        // Keep whole sentences that say nothing about channels; drop the
        // ones that do.
        $__sbKept = array_values(array_filter(
            preg_split('/(?<=[.!?])\s+/', $__sbSubtext) ?: [],
            fn ($__s) => ! preg_match('/whatsapp/i', $__s)
        ));
        $__sbSubtext = trim(implode(' ', $__sbKept));
        if ($__sbSubtext === '') {
            $__sbSubtext = 'Once a month, in your inbox. No spam, and you can opt out in one click.';
        }
    }
@endphp
<section class="sy sec-rule pb-24" aria-labelledby="subscribe-block-h-{{ $__sbSource }}">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-9">
            {{-- Rule 1: the eyebrow was blue text on a blue tint. --}}
            <div class="sy-eyebrow">Subscribe</div>
            <h2 id="subscribe-block-h-{{ $__sbSource }}" class="text-2xl sm:text-3xl font-bold tracking-tight" style="color: var(--sy-ink);">{{ $__sbHeading }}</h2>
            <p class="mt-2.5 max-w-xl mx-auto text-sm leading-relaxed" style="color: var(--sy-ink-2);">{{ $__sbSubtext }}</p>
        </div>

        {{-- One channel means one card, and a single card stretched across
             the section is a banner rather than a card. --}}
        <div class="sy-grid {{ $__sbCardCount === 1 ? 'sy-solo' : '' }}" style="--sy-min: 260px;">

            {{-- Email newsletter --}}
            <div class="sy-card">
                <div class="sy-head">
                    <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                        <i class="fas fa-envelope-open-text" aria-hidden="true" style="font-size:12px;"></i> Email newsletter
                    </span>
                </div>
                <p class="sy-blurb" style="margin-top:0;">Long-form notes, playbooks, and templates. Once a month, easy to skim.</p>

                @if(session($__sbFlashKey))
                    <div class="sy-notice" style="margin-top:18px;" role="status">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <span><b>{{ session($__sbFlashKey) }}</b></span>
                    </div>
                @endif

                @if(old('source') === $__sbSubmitSource)
                    @error('email')
                        <div class="sy-notice sy-notice--bad" style="margin-top:18px;" role="alert">
                            <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                            <span>{{ $message }}</span>
                        </div>
                    @enderror
                @endif

                <form method="POST" action="{{ route('site.newsletter.subscribe') }}"
                      class="sy-foot sy-field" novalidate>
                    @csrf
                    <input type="hidden" name="source" value="{{ $__sbSubmitSource }}">
                    {{-- Honeypot. Hidden from people, filled by bots. --}}
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                           class="hidden" aria-hidden="true">
                    <label class="sr-only" for="subscribe-email-{{ $__sbSource }}">Email address</label>
                    <input type="email" id="subscribe-email-{{ $__sbSource }}" name="email" required
                           autocomplete="email"
                           placeholder="you@example.com"
                           value="{{ old('source') === $__sbSubmitSource ? old('email') : '' }}"
                           class="sy-input">
                    <button type="submit" class="sy-cta">
                        Subscribe <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
                <div class="sy-foot-note">One email a month. Unsubscribe in one click.</div>
            </div>

            @if($__sbHasWaChan)
                {{-- WhatsApp Channel --}}
                <div class="sy-card">
                    <div class="sy-head">
                        <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                            <i class="fab fa-whatsapp" aria-hidden="true" style="font-size:12px;"></i> WhatsApp Channel
                        </span>
                    </div>
                    <p class="sy-blurb" style="margin-top:0;">Quick announcements, drops and tips, straight to your WhatsApp. One tap to follow, and we never see your number.</p>
                    <div class="sy-foot">
                        <a href="{{ $__sbWaUrl }}" target="_blank" rel="noopener noreferrer"
                           data-source="{{ $__sbSubmitSource }}" class="sy-cta sy-cta--ghost">
                            Follow channel <i class="fas fa-arrow-up-right-from-square"></i>
                        </a>
                        <div class="sy-foot-note">Broadcast only. You cannot reply.</div>
                    </div>
                </div>
            @endif

            @if($__sbHasWaDm)
                {{-- WhatsApp DM --}}
                <div class="sy-card">
                    <div class="sy-head">
                        <span class="sy-eyebrow" style="display:inline-flex; align-items:center; gap:9px;">
                            <i class="fas fa-comments" aria-hidden="true" style="font-size:12px;"></i> Chat on WhatsApp
                        </span>
                    </div>
                    <p class="sy-blurb" style="margin-top:0;">Questions, demo requests, partnership ideas? Message us and a person will get back to you.</p>
                    <div class="sy-foot">
                        <a href="{{ $__sbWaDmHref }}" target="_blank" rel="noopener noreferrer"
                           data-source="{{ $__sbSubmitSource }}" class="sy-cta sy-cta--ghost">
                            Start a chat <i class="fas fa-arrow-up-right-from-square"></i>
                        </a>
                        <div class="sy-foot-note">One to one, with a human.</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="text-center mt-7">
            <a href="{{ route('site.subscriptions.manage') }}" class="sy-link" style="font-size:13px;">
                Already subscribed? Manage subscriptions
            </a>
        </div>
    </div>
</section>
