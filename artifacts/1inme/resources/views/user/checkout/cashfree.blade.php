@extends('user.layouts.app')
@section('title', 'Complete payment')

@section('content')
<div class="max-w-xl mx-auto p-6 space-y-4">
    <h1 class="text-xl font-semibold text-white">Complete your payment</h1>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 space-y-2 text-sm">
        <div class="flex justify-between text-white/70">
            <span>Invoice</span>
            <span class="font-mono text-white">{{ $invoice->number }}</span>
        </div>
        <div class="flex justify-between text-white/70">
            <span>Amount due</span>
            <span class="font-mono text-white">{{ number_format($amount_minor / 100, 2) }} {{ $currency }}</span>
        </div>
    </div>

    <button id="cf-pay"
        class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
        Pay with Cashfree
    </button>

    <p class="text-xs text-white/40 text-center">
        You'll be taken to Cashfree's secure checkout. Don't close this page until
        the payment completes.
    </p>

    <noscript>
        <p class="text-xs text-rose-300 text-center">
            JavaScript is required to complete this payment. Please enable it and reload.
        </p>
    </noscript>
</div>

<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
<script>
    (function () {
        var invoiceNumber = @json($invoice->number);
        var sessionId     = @json($payment_session_id);
        var mode          = @json($mode);
        if (!window.Cashfree) return;

        var cashfree = Cashfree({ mode: mode === 'sandbox' ? 'sandbox' : 'production' });

        function open() {
            cashfree.checkout({
                paymentSessionId: sessionId,
                returnUrl: '/user/billing?paid=' + encodeURIComponent(invoiceNumber),
            }).then(function (result) {
                if (result && result.error) {
                    window.location.href = '/user/billing?failed=' + encodeURIComponent(invoiceNumber);
                } else if (result && result.redirect) {
                    // SDK handles redirect itself.
                } else {
                    window.location.href = '/user/billing?paid=' + encodeURIComponent(invoiceNumber);
                }
            });
        }
        var btn = document.getElementById('cf-pay');
        btn.addEventListener('click', open);
        setTimeout(open, 300);
    })();
</script>
@endsection
