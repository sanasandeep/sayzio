{{--
    End-of-page newsletter CTA. Inputs:
      $heading (optional), $subtext (optional), $source (e.g. 'features-cta')
--}}
@php
    $__nlHeading = $heading ?? 'Get the 1INME newsletter';
    $__nlSubtext = $subtext ?? 'Product updates, growth playbooks for creators, and the occasional template — once a month, no spam.';
    $__nlSource  = $source ?? 'page-cta';
@endphp
<section class="pb-24" aria-labelledby="newsletter-cta-h">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-transparent border border-violet-400/20 rounded-2xl p-8 sm:p-10 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/15 border border-violet-400/30 text-[11px] font-bold uppercase tracking-wider text-violet-200 mb-3">
                <i class="fas fa-envelope-open-text"></i> Newsletter
            </div>
            <h2 id="newsletter-cta-h" class="text-2xl sm:text-3xl font-bold text-white">{{ $__nlHeading }}</h2>
            <p class="mt-2 text-gray-400 max-w-xl mx-auto text-sm leading-relaxed">{{ $__nlSubtext }}</p>

            @if(session('newsletter_success'))
                <div class="mt-5 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm">
                    <i class="fas fa-circle-check"></i> {{ session('newsletter_success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('site.newsletter.subscribe') }}"
                  class="mt-6 flex flex-col sm:flex-row items-stretch gap-2 max-w-lg mx-auto"
                  novalidate>
                @csrf
                <input type="hidden" name="source" value="{{ $__nlSource }}">
                <input type="text" name="website" value="" tabindex="-1" autocomplete="off"
                       class="hidden" aria-hidden="true">
                <label class="sr-only" for="newsletter-email-{{ $__nlSource }}">Email address</label>
                <input type="email" id="newsletter-email-{{ $__nlSource }}" name="email" required
                       placeholder="you@example.com"
                       value="{{ old('email') }}"
                       class="flex-1 min-w-0 px-4 py-3 rounded-full bg-white/5 border border-white/15 text-white placeholder-white/40 text-sm focus:outline-none focus:border-violet-400/60">
                <button type="submit"
                        class="shrink-0 px-6 py-3 rounded-full bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold">
                    Subscribe
                </button>
            </form>
            @error('email')
                <p class="mt-2 text-xs text-red-300">{{ $message }}</p>
            @enderror
            <p class="mt-3 text-[11px] text-gray-500">We respect your inbox. Unsubscribe any time.</p>
        </div>
    </div>
</section>
