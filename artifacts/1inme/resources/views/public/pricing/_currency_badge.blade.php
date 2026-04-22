{{--
    Visible badge that tells the visitor which currency they're seeing
    and (when applicable) why it was auto-picked, alongside a switcher.

    Required vars:
      - $currency        : 'USD' | 'INR'
      - $currencySource  : PricingResolver::SOURCE_* constant
      - $user            : ?User  (for country-bound copy)
      - $switchRoute     : route name to POST currency to (e.g. 'upgrade.public.switch-currency')
    Optional:
      - $compact (bool)  : tighter layout for embedded contexts
--}}
@php
    $compact = $compact ?? false;
    $isAuto  = $currencySource === \App\Services\PricingResolver::SOURCE_GEO;
    $isManual = $currencySource === \App\Services\PricingResolver::SOURCE_MANUAL;
    $isCountry = $currencySource === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
    $symbol = $currency === 'INR' ? '₹' : '$';
    // Only relevant on the auto-detected branch — when the geo lookup
    // failed (private IP, offline, unknown country) this is null and we
    // fall back to the original currency-only badge silently.
    $geoCountryName = $isAuto ? \App\Services\PricingResolver::geoDetectedCountryName() : null;
@endphp
<div class="inline-flex flex-wrap items-center justify-center gap-2 {{ $compact ? '' : 'mt-3' }}"
     role="group" aria-label="Currency selection">
    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border border-white/10 bg-white/[0.04] text-xs">
        @if($isAuto)
            <i class="fas fa-location-arrow text-violet-300" aria-hidden="true"></i>
            <span class="text-gray-400">Auto-detected:</span>
        @elseif($isManual)
            <i class="fas fa-hand-pointer text-pink-300" aria-hidden="true"></i>
            <span class="text-gray-400">You picked:</span>
        @elseif($isCountry)
            <i class="fas fa-globe text-emerald-300" aria-hidden="true"></i>
            <span class="text-gray-400">Your country:</span>
        @endif
        <span class="font-semibold text-white">{{ $symbol }} {{ $currency }}</span>
        @if($isAuto && $geoCountryName)
            <span class="text-gray-500" aria-hidden="true">·</span>
            <span class="text-gray-400">Looks like you're in
                <span class="text-gray-200">{{ $geoCountryName }}</span></span>
        @endif
    </span>

    @if($isCountry)
        <span class="text-[11px] text-gray-500">
            Set from your billing country (<span class="uppercase">{{ $user->country }}</span>) —
            <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">change</a>
        </span>
    @else
        <form method="POST" action="{{ route($switchRoute) }}" class="inline-flex items-center gap-1">
            @csrf
            <span class="text-[11px] text-gray-500">
                @if($isAuto)
                    Not where you live? Switch:
                @else
                    Switch:
                @endif
            </span>
            <button type="submit" name="currency" value="USD"
                    class="px-2.5 py-0.5 text-[11px] rounded-l-full border border-white/10 {{ $currency === 'USD' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}"
                    aria-pressed="{{ $currency === 'USD' ? 'true' : 'false' }}"
                    aria-label="Show prices in US dollars">USD ($)</button>
            <button type="submit" name="currency" value="INR"
                    class="px-2.5 py-0.5 text-[11px] rounded-r-full border border-white/10 border-l-0 {{ $currency === 'INR' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10' }}"
                    aria-pressed="{{ $currency === 'INR' ? 'true' : 'false' }}"
                    aria-label="Show prices in Indian rupees">INR (₹)</button>
        </form>
    @endif
</div>
