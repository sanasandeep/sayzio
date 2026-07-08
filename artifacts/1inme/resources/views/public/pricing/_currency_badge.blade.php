{{--
    Compact currency switcher — no "You picked" / "Auto-detected" chip.
    Used by /user/upgrade to give the signed-in upgrade page its own
    inline switch (independent of the public-site footer).

    Required vars:
      - $currency        : 'USD' | 'INR'  (server default for no-JS / first paint)
      - $currencySource  : PricingResolver::SOURCE_* constant
      - $user            : ?User  (for country-bound copy)
    Requires the host page to define Alpine `currency` (string) and
    `switchCurrency(c)` in an ancestor x-data scope.
    Optional:
      - $compact (bool)  : tighter layout for embedded contexts
--}}
@php
    $compact = $compact ?? false;
    $isCountry = $currencySource === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
    $isAuto    = $currencySource === \App\Services\PricingResolver::SOURCE_GEO;
@endphp
<div class="inline-flex flex-wrap items-center justify-center gap-2 {{ $compact ? '' : 'mt-3' }}"
     role="group" aria-label="Currency selection">
    @if($isCountry)
        <span class="text-[11px] text-gray-500">
            {{ $currency === 'INR' ? '₹ INR' : '$ USD' }} — Set from your billing country (<span class="uppercase">{{ $user->country }}</span>) —
            <a href="{{ route('user.profile.edit') }}" class="text-blue-400 hover:underline">change</a>
        </span>
    @else
        <div class="inline-flex items-center gap-1">
            <button type="button" @click="switchCurrency('USD')"
                    :class="currency === 'USD' ? 'bg-blue-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10'"
                    :aria-pressed="currency === 'USD' ? 'true' : 'false'"
                    class="px-2.5 py-0.5 text-[11px] rounded-l-full border border-white/10 transition-colors motion-reduce:transition-none"
                    aria-label="Show prices in US dollars">USD ($)</button>
            <button type="button" @click="switchCurrency('INR')"
                    :class="currency === 'INR' ? 'bg-blue-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10'"
                    :aria-pressed="currency === 'INR' ? 'true' : 'false'"
                    class="px-2.5 py-0.5 text-[11px] rounded-r-full border border-white/10 border-l-0 transition-colors motion-reduce:transition-none"
                    aria-label="Show prices in Indian rupees">INR (₹)</button>
        </div>
        @if($isAuto)
            <span class="text-[11px] text-gray-500">auto-detected — switch anytime</span>
        @endif
    @endif
</div>
