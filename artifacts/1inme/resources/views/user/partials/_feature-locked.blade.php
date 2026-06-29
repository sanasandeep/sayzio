{{-- Reusable "feature locked by admin" state.

  Renders a glassmorphic card explaining a feature that the platform
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
    @keyframes featureLockShimmer {
        0%   { transform: translateX(-120%) skewX(-12deg); }
        100% { transform: translateX(220%)  skewX(-12deg); }
    }
    @keyframes featureLockHalo {
        0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,.35); }
        50%      { box-shadow: 0 0 0 14px rgba(59,130,246,0); }
    }
    .feature-lock-icon  { animation: featureLockPulse 2.6s ease-in-out infinite; }
    .feature-lock-halo  { animation: featureLockHalo 2.6s ease-in-out infinite; }
    .feature-lock-badge i { animation: featureLockShackle 1.8s ease-in-out infinite; transform-origin: 50% 70%; }
    .feature-lock-shine {
        position: absolute; inset: 0; overflow: hidden; border-radius: inherit; pointer-events: none;
    }
    .feature-lock-shine::after {
        content: ""; position: absolute; top: 0; bottom: 0; left: 0; width: 40%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.10), transparent);
        animation: featureLockShimmer 3.4s ease-in-out infinite;
    }
    @media (prefers-reduced-motion: reduce) {
        .feature-lock-icon,
        .feature-lock-halo,
        .feature-lock-badge i { animation: none !important; }
        .feature-lock-shine::after { display: none; }
    }
</style>
@endpush
@endonce

<div class="relative overflow-hidden rounded-2xl border border-primary-500/20 bg-gradient-to-br from-primary-500/[0.08] to-primary-400/[0.04] p-8 text-center">
    <div class="feature-lock-shine"></div>

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

    <h2 class="mt-4 text-white font-semibold text-xl">{{ $__flTitle }}</h2>
    <p class="text-sm text-white/60 mt-2 max-w-lg mx-auto">{{ $__flDesc }}</p>

    @if(!empty($__flOffers))
        <ul class="mt-5 max-w-md mx-auto space-y-2 text-left">
            @foreach($__flOffers as $__flOffer)
                <li class="flex items-start gap-2.5 text-sm text-white/70">
                    <i class="fa-solid fa-circle-check text-primary-300 mt-0.5 shrink-0"></i>
                    <span>{{ $__flOffer }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <p class="text-xs text-white/40 mt-6 max-w-md mx-auto">
        This feature is currently turned off by the platform administrator. Reach out to have it enabled for your account.
    </p>

    @if($__flCtaUrl)
        <a href="{{ $__flCtaUrl }}"
           class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium transition-colors">
            <i class="fa-solid fa-arrow-up"></i> {{ $__flCta }}
        </a>
    @else
        <span class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-xl bg-white/5 border border-white/10 text-white/70 text-sm font-medium">
            <i class="fa-solid fa-circle-info"></i> {{ $__flCta }}
        </span>
    @endif
</div>
