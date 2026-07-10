@extends('public.monetization._shell', ['pageTitle' => 'Confirm payment'])

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-2xl border p-6" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(99,91,255,0.12); color: #635bff;">
                <i class="fas fa-credit-card text-lg"></i>
            </span>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Hosted by {{ ucfirst($provider) }}</div>
                <div class="font-semibold" style="color: var(--text-primary);">Confirm your payment</div>
            </div>
        </div>

        <div class="rounded-xl border p-3 mb-4 text-xs" style="border-color: var(--border-color); color: var(--text-secondary); background: rgba(99,91,255,0.04);">
            <strong>Preview mode.</strong> The provider's API keys aren't configured yet, so this is a stand-in for the real
            hosted checkout. Click confirm to simulate a successful payment and continue.
        </div>

        <div class="space-y-2 text-sm mb-5" style="color: var(--text-secondary);">
            <div class="flex justify-between">
                <span>Type</span>
                <span class="font-semibold" style="color: var(--text-primary);">
                    @switch($kind)
                        @case('subscription') Subscription @break
                        @case('ppv') Post unlock @break
                        @case('tip') Tip @break
                        @default One-time charge
                    @endswitch
                </span>
            </div>
            <div class="flex justify-between">
                <span>Reference</span>
                <span class="font-mono text-xs" style="color: var(--text-primary);">{{ $reference }}</span>
            </div>
        </div>

        {{-- The form action is a server-signed URL (pre-generated in the controller).
             This prevents an attacker from driving the confirm endpoint directly
             without a valid server-issued signature — even if they know the token. --}}
        <form method="POST" action="{{ $confirmUrl }}">
            @csrf
            <button type="submit" class="w-full py-2.5 rounded-lg font-semibold text-sm mb-2" style="background: #635bff; color: white;">
                Confirm payment
            </button>
            <a href="{{ url()->previous() }}" class="block text-center text-sm" style="color: var(--text-faint);">Cancel</a>
        </form>
    </div>
</div>
@endsection
