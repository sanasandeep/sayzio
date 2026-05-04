{{--
    End-of-page 3-way "Subscribe" block. Inputs:
      $source   (required, string) — page slug used to tag submissions, e.g. 'features'.
                Tagged in DB as `subscribe-block:<source>`. Also used to scope the
                success flash so only the card the visitor just submitted shows the
                confirmation banner.
      $heading  (optional)
      $subtext  (optional)

    Renders three side-by-side channel cards (Email newsletter, WhatsApp Channel,
    WhatsApp DM). WhatsApp cards self-hide when the admin hasn't configured that
    channel yet, so the block degrades to email-only without breaking the layout.
    A small "Manage subscriptions" link below the cards opens the Unsubscribe Center.
--}}
@php
    $__sbSource   = $source ?? 'page';
    $__sbHeading  = $heading ?? 'Stay in the loop with 1INME';
    $__sbSubtext  = $subtext ?? 'Pick the channel that fits you. Product updates, growth playbooks, and the occasional template — no spam, opt out any time.';
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
    $__sbGridCols  = $__sbCardCount === 1
        ? 'md:grid-cols-1'
        : ($__sbCardCount === 2 ? 'md:grid-cols-2' : 'md:grid-cols-3');
@endphp
<section class="pb-24" aria-labelledby="subscribe-block-h-{{ $__sbSource }}">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/15 border border-violet-400/30 text-[11px] font-bold uppercase tracking-wider text-violet-200 mb-3">
                <i class="fas fa-bell"></i> Subscribe
            </div>
            <h2 id="subscribe-block-h-{{ $__sbSource }}" class="text-2xl sm:text-3xl font-bold" style="color: var(--text-primary);">{{ $__sbHeading }}</h2>
            <p class="mt-2 max-w-2xl mx-auto text-sm leading-relaxed" style="color: var(--text-muted);">{{ $__sbSubtext }}</p>
        </div>

        <div class="grid grid-cols-1 {{ $__sbGridCols }} gap-4">
            {{-- Card 1: Email newsletter --}}
            <div class="bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-transparent border border-violet-400/20 rounded-2xl p-6 flex flex-col">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center bg-violet-500/20 text-violet-200">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold" style="color: var(--text-primary);">Email newsletter</h3>
                        <p class="text-[11px]" style="color: var(--text-muted);">Monthly · in your inbox</p>
                    </div>
                </div>
                <p class="text-xs leading-relaxed mb-4" style="color: var(--text-muted);">Long-form notes, playbooks, and templates. Once a month, easy to skim.</p>

                @if(session($__sbFlashKey))
                    <div class="mb-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-xs">
                        <i class="fas fa-circle-check"></i> {{ session($__sbFlashKey) }}
                    </div>
                @endif

                <form method="POST" action="{{ route('site.newsletter.subscribe') }}"
                      class="mt-auto flex flex-col gap-2"
                      novalidate>
                    @csrf
                    <input type="hidden" name="source" value="{{ $__sbSubmitSource }}">
                    <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                           class="hidden" aria-hidden="true">
                    <label class="sr-only" for="subscribe-email-{{ $__sbSource }}">Email address</label>
                    <input type="email" id="subscribe-email-{{ $__sbSource }}" name="email" required
                           placeholder="you@example.com"
                           value="{{ old('source') === $__sbSubmitSource ? old('email') : '' }}"
                           class="theme-input px-4 py-2.5 rounded-full text-sm focus:outline-none focus:border-violet-400/60">
                    <button type="submit"
                            class="px-5 py-2.5 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane text-xs"></i> Subscribe
                    </button>
                </form>
                @if(old('source') === $__sbSubmitSource)
                    @error('email')
                        <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
                    @enderror
                @endif
            </div>

            @if($__sbHasWaChan)
                {{-- Card 2: WhatsApp Channel --}}
                <div class="bg-gradient-to-br from-emerald-500/15 via-teal-500/10 to-transparent border border-emerald-400/20 rounded-2xl p-6 flex flex-col">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-200">
                            <i class="fab fa-whatsapp"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold" style="color: var(--text-primary);">WhatsApp Channel</h3>
                            <p class="text-[11px]" style="color: var(--text-muted);">Broadcast · read-only</p>
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed mb-4" style="color: var(--text-muted);">Quick announcements, drops and tips, straight to your WhatsApp. One tap to follow.</p>
                    <a href="{{ $__sbWaUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       data-source="{{ $__sbSubmitSource }}"
                       class="mt-auto px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                        <i class="fab fa-whatsapp"></i> Follow channel
                    </a>
                </div>
            @endif

            @if($__sbHasWaDm)
                {{-- Card 3: WhatsApp DM --}}
                <div class="bg-gradient-to-br from-emerald-500/15 via-lime-500/10 to-transparent border border-emerald-400/20 rounded-2xl p-6 flex flex-col">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-emerald-500/20 text-emerald-200">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold" style="color: var(--text-primary);">Chat on WhatsApp</h3>
                            <p class="text-[11px]" style="color: var(--text-muted);">1:1 · talk to a human</p>
                        </div>
                    </div>
                    <p class="text-xs leading-relaxed mb-4" style="color: var(--text-muted);">Questions, demo requests, partnership ideas — DM us and we'll get back fast.</p>
                    <a href="{{ $__sbWaDmHref }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       data-source="{{ $__sbSubmitSource }}"
                       class="mt-auto px-5 py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane text-xs"></i> Start chat
                    </a>
                </div>
            @endif
        </div>

        <div class="text-center mt-5">
            <a href="{{ route('site.subscriptions.manage') }}"
               class="inline-flex items-center gap-1.5 text-[12px] hover:text-violet-300 underline-offset-2 hover:underline"
               style="color: var(--text-muted);">
                <i class="fas fa-sliders text-[10px]"></i> Already subscribed? Manage subscriptions
            </a>
        </div>
    </div>
</section>
