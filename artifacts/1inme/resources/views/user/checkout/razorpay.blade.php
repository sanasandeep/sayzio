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

    <button id="rzp-pay"
        class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
        Pay with Razorpay
    </button>

    <p class="text-xs text-white/40 text-center">
        You'll see Razorpay's secure checkout overlay. Don't close this page until
        you've completed or cancelled the payment.
    </p>

    <noscript>
        <p class="text-xs text-rose-300 text-center">
            JavaScript is required to complete this payment. Please enable it and reload.
        </p>
    </noscript>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    (function () {
        var options = {
            key: @json($key_id),
            name: @json($merchant_name),
            description: @json($description),
            prefill: @json($prefill),
            theme: { color: '#3d6bff' },
            handler: function (response) {
                // Razorpay captured the payment. Our source of truth is
                // the webhook — redirect to the billing page; the
                // receipt email follows.
                window.location.href = '/user/billing?paid=' + encodeURIComponent(@json($invoice->number));
            },
            modal: {
                ondismiss: function () {
                    window.location.href = '/user/billing?cancelled=' + encodeURIComponent(@json($invoice->number));
                }
            }
        };
        @if (!empty($subscription_id))
            options.subscription_id = @json($subscription_id);
        @else
            options.order_id = @json($order_id);
            options.amount = @json($amount_minor);
            options.currency = @json($currency);
        @endif

        var btn = document.getElementById('rzp-pay');
        function open() {
            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function () {
                window.location.href = '/user/billing?failed=' + encodeURIComponent(@json($invoice->number));
            });
            rzp.open();
        }
        btn.addEventListener('click', open);
        // Auto-open once on first render so users don't sit on the button.
        setTimeout(open, 300);
    })();
</script>
@endsection
