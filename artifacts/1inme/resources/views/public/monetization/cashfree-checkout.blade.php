@extends('public.monetization._shell', ['pageTitle' => 'Pay with Cashfree'])

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-2xl border p-6" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(105,51,211,0.12); color: #6933d3;">
                <i class="fas fa-money-bill-transfer text-lg"></i>
            </span>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Secured by Cashfree</div>
                <div class="font-semibold" style="color: var(--text-primary);">Complete your payment</div>
            </div>
        </div>

        <div class="space-y-2 text-sm mb-5" style="color: var(--text-secondary);">
            <div class="flex justify-between">
                <span>Amount</span>
                <span class="font-semibold" style="color: var(--text-primary);">
                    {{ strtoupper($currency) }} {{ number_format(((int) $amount) / 100, 2) }}
                </span>
            </div>
            <div class="flex justify-between">
                <span>Reference</span>
                <span class="font-mono text-xs" style="color: var(--text-primary);">{{ $reference }}</span>
            </div>
        </div>

        <button id="cf-open" class="w-full rounded-xl py-3 font-semibold text-white" style="background: #6933d3;">
            Pay with Cashfree
        </button>
        <p class="text-xs mt-3" style="color: var(--text-faint);">
            The Cashfree window opens automatically. If it doesn't, click the button above.
        </p>
    </div>
</div>

<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
    (function () {
        var cashfree = Cashfree({ mode: @json($mode) });
        var open = function () {
            // redirectTarget "_self": Cashfree sends the buyer back to
            // the order's return_url (checkout.return). Settlement
            // happens via the signature-verified webhook, never here.
            cashfree.checkout({
                paymentSessionId: @json($session_id),
                redirectTarget: '_self',
            });
        };
        document.getElementById('cf-open').addEventListener('click', open);
        open();
    })();
</script>
@endsection
