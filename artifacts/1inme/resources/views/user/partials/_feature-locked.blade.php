{{-- Reusable "feature locked by admin" state.

  Renders a liquid-glass card explaining a feature that the platform
  administrator has currently turned off, with an animated lock + feature
  icon and a call-to-action. Models its layout/copy on the upgrade-prompt
  pattern used elsewhere (e.g. AI Brand Kits) so it feels native.

  Props:
    $title       string        required — the feature name, e.g. "Wallet & Coins".
    $description string        required — a short plain-language summary.
    $offers      array<string> optional — bullet list of what the feature lets you do.
    $icon        string        optional — Font Awesome class for the feature icon
                                           (default 'fa-solid fa-coins').
    $cta         string        optional — CTA button label (default 'Contact your admin').
    $ctaUrl      string|null   optional — CTA link target. When null the button
                                           is rendered as a non-link badge.
--}}
@php
    $__flTitle  = $title ?? 'This feature';
    $__flDesc   = $description ?? '';
    $__flOffers = isset($offers) && is_array($offers) ? $offers : [];
    $__flIcon   = $icon ?? 'fa-solid fa-coins';
    $__flCta    = $cta ?? 'Contact your admin';
    $__flCtaUrl = $ctaUrl ?? null;
@endphp

@once
@push('styles')
<style>
    @keyframes featureLockShackle {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-2px) rotate(-4deg); }
    }
    @keyframes featureLockPulse {
        0%, 100% { transform: scale(1);    opacity: 1; }
        50%      { transform: scale(1.06); opacity: .85; }
    }
    @keyframes featureLockHalo {
        0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,.35); }
        50%      { box-shadow: 0 0 0 14px rgba(59,130,246,0); }
    }
    .feature-lock-icon  { animation: featureLockPulse 2.6s ease-in-out infinite; }
    .feature-lock-halo  { animation: featureLockHalo 2.6s ease-in-out infinite; }
    .feature-lock-badge i { animation: featureLockShackle 1.8s ease-in-out infinite; transform-origin: 50% 70%; }
    /* Shared liquid-glass card — consumes the --lg-* tokens from
       theme-styles (26px blur/saturate, inset top highlight, long soft
       shadow); the tokens flip per mode so light stays legible. */
    .feature-locked-card {
        border-radius: var(--lg-radius, 1.5rem);
        background: var(--lg-bg, rgba(255,255,255,0.045));
        border: 1px solid var(--lg-border, rgba(255,255,255,0.10));
        backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        -webkit-backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        box-shadow: var(--lg-shadow, 0 30px 70px -35px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.07));
    }
    .feature-locked-card .fl-title { color: var(--text-primary, #fff); }
    .feature-locked-card .fl-desc  { color: var(--text-muted, rgba(255,255,255,.6)); }
    .feature-locked-card .fl-note  { color: var(--text-dimmed, rgba(255,255,255,.4)); }
    .feature-locked-card .fl-offer { color: var(--text-secondary, rgba(255,255,255,.7)); }
    html.light-mode .feature-locked-card .feature-lock-icon { color: var(--c-primary, #3d6bff); }
    @media (prefers-reduced-motion: reduce) {
        .feature-lock-icon,
        .feature-lock-halo,
        .feature-lock-badge i { animation: none !important; }
    }
</style>
@endpush
@endonce

<div class="feature-locked-card relative overflow-hidden p-8 text-center">
    <div class="relative flex items-center justify-center mb-5">
        <span class="feature-lock-halo relative flex h-20 w-20 items-center justify-center rounded-2xl bg-primary-500/15 border border-primary-400/20">
            <i class="{{ $__flIcon }} feature-lock-icon text-4xl text-primary-200"></i>
            <span class="feature-lock-badge absolute -bottom-2 -right-2 flex h-9 w-9 items-center justify-center rounded-full bg-primary-600 border border-primary-300/30 shadow-lg">
                <i class="fa-solid fa-lock text-white text-sm"></i>
            </span>
        </span>
    </div>

    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-200 text-xs font-semibold uppercase tracking-wider">
        <i class="fa-solid fa-lock text-[10px]"></i> Locked by admin
    </span>

    <h2 class="fl-title mt-4 font-semibold text-xl">{{ $__flTitle }}</h2>
    <p class="fl-desc text-sm mt-2 max-w-lg mx-auto">{{ $__flDesc }}</p>

    @if(!empty($__flOffers))
        <ul class="mt-5 max-w-md mx-auto space-y-2 text-left">
            @foreach($__flOffers as $__flOffer)
                <li class="fl-offer flex items-start gap-2.5 text-sm">
                    <i class="fa-solid fa-circle-check text-primary-300 mt-0.5 shrink-0"></i>
                    <span>{{ $__flOffer }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="fl-note text-xs mt-6 max-w-md mx-auto">
        This feature is currently turned off by the platform administrator. Reach out to have it enabled for your account.
    </p>

    @if($__flCtaUrl)
        <a href="{{ $__flCtaUrl }}"
           class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium transition-colors">
            <i class="fa-solid fa-arrow-up"></i> {{ $__flCta }}
        </a>
    @else
        <span class="fl-offer inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl text-sm font-medium" style="background: var(--bg-glass, rgba(255,255,255,0.05)); border: 1px solid var(--border-glass, rgba(255,255,255,0.10));">
            <i class="fa-solid fa-circle-info"></i> {{ $__flCta }}
        </span>
    @endif
</div>
