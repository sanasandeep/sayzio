{{-- Shared optional details for layouts that do not already display them. --}}
@php
    $detailsBg = $blockStyle['_profile_details_bg'] ?? '';
    $detailsBg = $detailsBg !== '' ? $detailsBg : (($blockStyle['bg_color'] ?? '') ?: '#f8fafc');
    $detailsInk = ($blockStyle['_profile_details_text'] ?? '') ?: (($blockStyle['text_color'] ?? '') ?: '#172033');
    $detailsAccent = ($blockStyle['_profile_accent_color'] ?? '') ?: (($blockStyle['text_color'] ?? '') ?: '#2459a6');
    $detailsCtaBg = ($blockStyle['_profile_cta_bg'] ?? '') ?: '#172033';
    $detailsCtaInk = ($blockStyle['_profile_cta_text'] ?? '') ?: '#ffffff';
    $hasDetails = ($showLocation && $location !== '') || ($showWebsite && $website !== '')
        || ($showCta && $ctaLabel !== '' && $ctaUrl !== '') || ($showSocials && !empty($psocials))
        || ($showVerified && $verified);
@endphp
@if($hasDetails)
<div data-profile-details class="px-5 py-4" style="background:{{ $detailsBg }};color:{{ $detailsInk }};border-top:1px solid color-mix(in srgb, {{ $detailsInk }} 15%, transparent);">
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
        @if($showVerified && $verified)<span><i class="fas fa-circle-check mr-1" style="color:{{ $detailsAccent }}" aria-hidden="true"></i>Verified</span>@endif
        @if($showLocation && $location !== '')<span><i class="fas fa-location-dot mr-1" aria-hidden="true"></i>{{ $location }}</span>@endif
        @if($showWebsite && $website !== '')<a href="{{ $website }}" target="_blank" rel="noopener noreferrer" class="hover:underline" style="color:{{ $detailsAccent }};overflow-wrap:anywhere;"><i class="fas fa-link mr-1" aria-hidden="true"></i>{{ preg_replace('#^https?://(www\.)?#', '', rtrim($website, '/')) }}</a>@endif
    </div>
    @if($showSocials)
        @include('common.biolink-profile-socials', ['psocials' => $psocials, 'socialIcons' => $socialIcons, 'accent' => $detailsAccent, 'chip' => 'accent_outline', 'align' => 'left'])
    @endif
    @if($showCta && $ctaLabel !== '' && $ctaUrl !== '')
        <a href="{{ $ctaUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold mt-3" style="background:{{ $detailsCtaBg }};color:{{ $detailsCtaInk }};">{{ $ctaLabel }}<i class="fas fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i></a>
    @endif
</div>
@endif
