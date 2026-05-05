@extends('public.monetization._shell', ['pageTitle' => 'Manage subscription · @' . $creator->handle])

@section('content')
<div class="max-w-md mx-auto px-4 py-8">
    <a href="{{ route('creator-profile.show', ['handle' => $creator->handle]) }}" class="text-sm" style="color: #8b5cf6;">← Back to profile</a>
    <h1 class="text-2xl font-bold mt-2 mb-1" style="color: var(--text-primary);">Manage subscription</h1>
    <p class="text-sm mb-6" style="color: var(--text-faint);">Your support of {{ $creator->name }}.</p>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif

    <div class="rounded-xl border p-5 mb-4" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="flex items-center justify-between mb-3">
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Tier</div>
                <div class="font-bold text-lg" style="color: var(--text-primary);">
                    {{ $sub->tier->badge ? $sub->tier->badge.' ' : '' }}{{ $sub->tier->name ?? 'Subscriber' }}
                </div>
            </div>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                  style="background: rgba(16,185,129,0.12); color: #10b981;">
                {{ $sub->statusLabel() }}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Cycle</div>
                <div style="color: var(--text-primary);">{{ ucfirst($sub->billing_cycle) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Price</div>
                <div style="color: var(--text-primary);">${{ number_format($sub->price_cents / 100, 2) }} {{ strtoupper($sub->currency) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Started</div>
                <div style="color: var(--text-primary);">{{ optional($sub->started_at)->format('M j, Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">{{ $sub->cancel_at_period_end ? 'Ends' : 'Renews' }}</div>
                <div style="color: var(--text-primary);">{{ optional($sub->current_period_end)->format('M j, Y') ?? '—' }}</div>
            </div>
        </div>
    </div>

    @if($sub->cancel_at_period_end)
        <div class="rounded-xl border p-4 mb-4 text-sm" style="border-color: rgba(245,158,11,0.4); background: rgba(245,158,11,0.08); color: #f59e0b;">
            Your subscription will end on {{ optional($sub->current_period_end)->format('M j, Y') }}. You'll keep tier access until then.
        </div>
        <form method="POST" action="{{ route('creator-profile.subscription.resume', ['handle' => $creator->handle]) }}">
            @csrf
            <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm" style="background: #8b5cf6; color: white;">
                Resume subscription
            </button>
        </form>
    @elseif($sub->isCurrent())
        <form method="POST" action="{{ route('creator-profile.subscription.cancel', ['handle' => $creator->handle]) }}"
              onsubmit="return confirm('Cancel your subscription? You\'ll keep access until the period ends.');">
            @csrf
            <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm border" style="border-color: var(--border-color); color: var(--text-secondary);">
                Cancel at period end
            </button>
        </form>
    @endif

    <div class="mt-4 text-center">
        <a href="{{ route('creator-profile.subscribe.show', ['handle' => $creator->handle]) }}" class="text-sm" style="color: #8b5cf6;">
            Switch tier →
        </a>
    </div>
</div>
@endsection
