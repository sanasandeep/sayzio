{{--
    Visible badge that tells the visitor which currency they're seeing
    and (when applicable) why it was auto-picked, alongside a switcher.

    The switcher is now instant: it flips the currency client-side with
    NO page reload by calling `switchCurrency('USD'|'INR')` and reading
    the reactive `currency` value — both supplied by the enclosing
    Alpine `x-data` scope (see /user/upgrade and the landing teaser).
    The host scope pings its persistence route in the background, so
    this partial no longer needs `$switchRoute`.

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
    $isAuto  = $currencySource === \App\Services\PricingResolver::SOURCE_GEO;
    $isManual = $currencySource === \App\Services\PricingResolver::SOURCE_MANUAL;
    $isCountry = $currencySource === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
    $symbol = $currency === 'INR' ? '₹' : '$';
    // Only relevant on the auto-detected branch — when the geo lookup
    // failed (private IP, offline, unknown country) this is null and we
    // fall back to the original currency-only badge silently.
    $geoCountryName = $isAuto ? \App\Services\PricingResolver::geoDetectedCountryName() : null;
    // City is best-effort — when present we prefix the country to make
    // the inference more transparent ("Mumbai, India"). Suppressed when
    // there's no country to attach it to so we never show a bare city.
    $geoCity = ($isAuto && $geoCountryName) ? \App\Services\PricingResolver::geoDetectedCity() : null;
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
                <span class="text-gray-200">{{ $geoCity ? $geoCity . ', ' . $geoCountryName : $geoCountryName }}</span></span>
        @endif
    </span>

    @if($isCountry)
        <span class="text-[11px] text-gray-500">
            Set from your billing country (<span class="uppercase">{{ $user->country }}</span>) —
            <a href="{{ route('user.profile.edit') }}" class="text-violet-400 hover:underline">change</a>
        </span>
    @else
        {{-- Instant client-side switch — no page reload. `currency` and
             `switchCurrency()` come from the host page's Alpine scope; the
             host pings its persistence route in the background. --}}
        <div class="inline-flex items-center gap-1">
            <span class="text-[11px] text-gray-500">
                @if($isAuto)
                    Not where you live? Switch:
                @else
                    Switch:
                @endif
            </span>
            <button type="button" @click="switchCurrency('USD')"
                    :class="currency === 'USD' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10'"
                    :aria-pressed="currency === 'USD' ? 'true' : 'false'"
                    class="px-2.5 py-0.5 text-[11px] rounded-l-full border border-white/10 transition-colors motion-reduce:transition-none"
                    aria-label="Show prices in US dollars">USD ($)</button>
            <button type="button" @click="switchCurrency('INR')"
                    :class="currency === 'INR' ? 'bg-violet-600 text-white' : 'bg-white/5 text-gray-300 hover:bg-white/10'"
                    :aria-pressed="currency === 'INR' ? 'true' : 'false'"
                    class="px-2.5 py-0.5 text-[11px] rounded-r-full border border-white/10 border-l-0 transition-colors motion-reduce:transition-none"
                    aria-label="Show prices in Indian rupees">INR (₹)</button>
        </div>
    @endif
</div>
