@extends('public.monetization._shell', ['pageTitle' => 'Subscribe to ' . ($creator->name ?? '@'.$creator->handle)])

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 md:py-12">

    {{-- Header --}}
    <div class="text-center mb-8">
        @if($creator->avatar)
            <img src="{{ $creator->avatar }}" alt="" class="w-16 h-16 rounded-full mx-auto mb-3 object-cover ring-2 ring-violet-500/30">
        @endif
        <a href="{{ route('creator-profile.show', ['handle' => $creator->handle]) }}" class="text-sm" style="color: #8b5cf6;">← Back to profile</a>
        <h1 class="text-3xl font-bold mt-2" style="color: var(--text-primary);">Support {{ $creator->name }}</h1>
        @if($creator->tagline)
            <p class="mt-1 text-sm max-w-md mx-auto" style="color: var(--text-faint);">{{ $creator->tagline }}</p>
        @endif
        <div class="inline-flex items-center gap-2 mt-3 px-3 py-1 rounded-full text-xs"
             style="background: rgba(16,185,129,0.12); color: #10b981;">
            <i class="fas fa-shield-check"></i>
            100% goes to {{ $creator->name }} — 1INME takes 0%
        </div>
    </div>

    @if($existing && $existing->isCurrent())
        <div class="max-w-md mx-auto mb-6 p-4 rounded-xl border text-center" style="border-color: var(--border-color); background: var(--bg-card);">
            <div class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
                You're a {{ $existing->tier->name ?? 'subscriber' }} ✓
            </div>
            <a href="{{ route('creator-profile.subscription.manage', ['handle' => $creator->handle]) }}"
               class="text-xs" style="color: #8b5cf6;">Manage subscription →</a>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-md mx-auto mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="max-w-md mx-auto mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ $errors->first() }}</div>
    @endif

    {{-- Cycle toggle --}}
    <div class="flex items-center justify-center gap-2 mb-6" id="cycleToggle">
        <button type="button" data-cycle="monthly" class="cycle-btn px-4 py-2 text-sm font-semibold rounded-full border" data-active="true"
                style="background: #8b5cf6; color: white; border-color: #8b5cf6;">Monthly</button>
        <button type="button" data-cycle="yearly" class="cycle-btn px-4 py-2 text-sm font-semibold rounded-full border"
                style="background: transparent; color: var(--text-secondary); border-color: var(--border-color);">Yearly</button>
    </div>

    {{-- Tiers grid --}}
    <div class="grid grid-cols-1 md:grid-cols-{{ min(3, max(1, $tiers->count())) }} gap-4 mb-8">
        @foreach($tiers as $tier)
            @php
                $color = match($tier->color) {
                    'sky' => '#0ea5e9', 'emerald' => '#10b981', 'amber' => '#f59e0b',
                    'rose' => '#f43f5e', 'fuchsia' => '#d946ef', 'slate' => '#64748b',
                    default => '#8b5cf6',
                };
                $monthly = '$' . number_format($tier->price_monthly_cents / 100, 2);
                $yearly = $tier->price_yearly_cents ? '$' . number_format($tier->price_yearly_cents / 100, 2) : null;
                $discount = $tier->yearlyDiscountPercent();
            @endphp
            <div class="rounded-2xl border p-6 flex flex-col" style="border-color: var(--border-color); background: var(--bg-card);">
                <div class="flex items-center gap-2 mb-2">
                    @if($tier->badge)
                        @if(str_starts_with($tier->badge, 'fa'))
                            <i class="{{ $tier->badge }}" style="color: {{ $color }};"></i>
                        @else
                            <span>{{ $tier->badge }}</span>
                        @endif
                    @endif
                    <h3 class="text-lg font-bold" style="color: var(--text-primary);">{{ $tier->name }}</h3>
                </div>
                <div class="mb-4">
                    @if($tier->is_free)
                        <div class="text-3xl font-bold" style="color: var(--text-primary);">Free</div>
                    @else
                        <div class="text-3xl font-bold" style="color: var(--text-primary);">
                            <span class="price-monthly">{{ $monthly }}</span>
                            <span class="price-yearly hidden">{{ $yearly ?: $monthly }}</span>
                            <span class="text-sm font-normal" style="color: var(--text-faint);">
                                / <span class="cycle-label">month</span>
                            </span>
                        </div>
                        @if($discount && $yearly)
                            <div class="text-xs mt-1 yearly-discount hidden" style="color: #10b981;">Save {{ $discount }}% with yearly</div>
                        @endif
                    @endif
                </div>
                <ul class="space-y-1.5 mb-6 text-sm flex-1" style="color: var(--text-secondary);">
                    @foreach($tier->visiblePerks() as $perk)
                        <li class="flex items-start gap-2">
                            <i class="fas fa-check mt-0.5 text-xs" style="color: {{ $color }};"></i>
                            <span>{{ $perk }}</span>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('creator-profile.subscribe', ['handle' => $creator->handle]) }}" class="space-y-2 subscribe-form">
                    @csrf
                    <input type="hidden" name="tier_id" value="{{ $tier->id }}">
                    <input type="hidden" name="cycle" value="monthly" class="cycle-input">
                    @if(!$tier->is_free)
                        <input type="text" name="promo_code" placeholder="Promo code (optional)"
                               class="w-full px-3 py-2 rounded-lg border bg-transparent text-sm uppercase font-mono"
                               style="border-color: var(--border-color); color: var(--text-primary);">
                    @endif
                    <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm" style="background: {{ $color }}; color: white;">
                        @if($tier->is_free)
                            Follow for free
                        @elseif($existing && $existing->tier_id === $tier->id && $existing->isCurrent())
                            Current plan
                        @else
                            Subscribe
                        @endif
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    @if(!$tiers->count())
        <div class="max-w-md mx-auto p-6 text-center rounded-xl border text-sm" style="border-color: var(--border-color); color: var(--text-faint);">
            This creator hasn't set up subscription tiers yet.
        </div>
    @endif
</div>

<script>
document.addEventListener('click', (e) => {
    const btn = e.target.closest('.cycle-btn');
    if (!btn) return;
    const cycle = btn.dataset.cycle;
    document.querySelectorAll('.cycle-btn').forEach((b) => {
        const active = b.dataset.cycle === cycle;
        b.style.background = active ? '#8b5cf6' : 'transparent';
        b.style.color = active ? 'white' : 'var(--text-secondary)';
        b.style.borderColor = active ? '#8b5cf6' : 'var(--border-color)';
    });
    document.querySelectorAll('.cycle-input').forEach((i) => i.value = cycle);
    document.querySelectorAll('.cycle-label').forEach((l) => l.textContent = cycle === 'yearly' ? 'year' : 'month');
    document.querySelectorAll('.price-monthly').forEach((p) => p.classList.toggle('hidden', cycle === 'yearly'));
    document.querySelectorAll('.price-yearly').forEach((p) => p.classList.toggle('hidden', cycle !== 'yearly'));
    document.querySelectorAll('.yearly-discount').forEach((d) => d.classList.toggle('hidden', cycle !== 'yearly'));
});
</script>
@endsection
