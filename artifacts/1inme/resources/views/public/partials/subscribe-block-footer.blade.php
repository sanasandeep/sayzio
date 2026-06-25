{{--
    Compact 3-way "Subscribe" strip used inside the global site footer.
    Mirrors the public.partials.subscribe-block partial's three channels
    (Email, WhatsApp Channel, WhatsApp DM) but in a row-friendly layout.
    WhatsApp cards self-hide when the channel/number is unset.
--}}
@php
    $__sbfSource   = 'footer';
    $__sbfSubmit   = 'subscribe-block:' . $__sbfSource;
    $__sbfFlashKey = 'newsletter_success_' . $__sbfSource;
    $__sbfWaUrl    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_channel_url', ''));
    $__sbfWaNum    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_number', ''));
    $__sbfWaMsg    = trim((string) \App\Modules\Admin\Models\AppSetting::get('marketing_whatsapp_message', ''));
    $__sbfWaDmHref = $__sbfWaNum !== ''
        ? 'https://wa.me/' . preg_replace('/[^0-9]/', '', $__sbfWaNum) . ($__sbfWaMsg !== '' ? ('?text=' . rawurlencode($__sbfWaMsg)) : '')
        : '';
@endphp
<div class="border-b border-white/5">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-[.2em] text-blue-300 mb-1">Subscribe</div>
                <h3 class="text-lg sm:text-xl font-bold text-white">Stay in the loop with Sayzio.</h3>
                <p class="mt-1 text-xs text-gray-400">Pick the channel that suits you — email, WhatsApp Channel, or DM.</p>
            </div>
            <a href="{{ route('site.subscriptions.manage') }}"
               class="text-[11px] text-gray-500 hover:text-blue-300 underline-offset-2 hover:underline inline-flex items-center gap-1.5 self-start sm:self-auto">
                <i class="fas fa-sliders text-[10px]"></i> Manage subscriptions
            </a>
        </div>

        @if(session($__sbfFlashKey))
            <div class="mb-3 inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-xs">
                <i class="fas fa-circle-check"></i> {{ session($__sbfFlashKey) }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            {{-- Email --}}
            <form method="POST" action="{{ route('site.newsletter.subscribe') }}"
                  class="flex flex-col sm:flex-row items-stretch gap-2"
                  novalidate>
                @csrf
                <input type="hidden" name="source" value="{{ $__sbfSubmit }}">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                       class="hidden" aria-hidden="true">
                <label class="sr-only" for="footer-subscribe-email">Email address</label>
                <input type="email" id="footer-subscribe-email" name="email" required
                       placeholder="you@example.com"
                       value="{{ old('source') === $__sbfSubmit ? old('email') : '' }}"
                       class="flex-1 min-w-0 px-4 py-2.5 rounded-full bg-white/5 border border-white/15 text-white placeholder-white/40 text-sm focus:outline-none focus:border-blue-400/60">
                <button type="submit"
                        class="shrink-0 px-4 py-2.5 rounded-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-envelope-open-text text-xs"></i> Email
                </button>
            </form>

            {{-- WhatsApp Channel --}}
            @if($__sbfWaUrl !== '')
                <a href="{{ $__sbfWaUrl }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   data-source="{{ $__sbfSubmit }}"
                   class="px-4 py-2.5 rounded-full bg-emerald-600/90 hover:bg-emerald-600 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fab fa-whatsapp"></i> WhatsApp Channel
                </a>
            @else
                <div class="hidden md:block"></div>
            @endif

            {{-- WhatsApp DM --}}
            @if($__sbfWaDmHref !== '')
                <a href="{{ $__sbfWaDmHref }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   data-source="{{ $__sbfSubmit }}"
                   class="px-4 py-2.5 rounded-full bg-emerald-600/90 hover:bg-emerald-600 text-white text-sm font-bold inline-flex items-center justify-center gap-2">
                    <i class="fas fa-comments text-xs"></i> WhatsApp DM
                </a>
            @else
                <div class="hidden md:block"></div>
            @endif
        </div>
        @if(old('source') === $__sbfSubmit)
            @error('email')
                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
            @enderror
        @endif
    </div>
</div>
