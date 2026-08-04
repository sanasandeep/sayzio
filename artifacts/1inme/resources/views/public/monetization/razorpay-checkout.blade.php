@extends('public.monetization._shell', ['pageTitle' => 'Pay with Razorpay'])

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-2xl border p-6" style="border-color: var(--border-color); background: var(--bg-card);">
        <div class="flex items-center gap-2 mb-4">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center" style="background: rgba(51,149,255,0.12); color: #3395ff;">
                <i class="fas fa-indian-rupee-sign text-lg"></i>
            </span>
            <div>
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-faint);">Secured by Razorpay</div>
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

        <button id="rzp-open" class="w-full rounded-xl py-3 font-semibold text-white" style="background: #3395ff;">
            Pay with Razorpay
        </button>
        <p class="text-xs mt-3" style="color: var(--text-faint);">
            The Razorpay window opens automatically. If it doesn't, click the button above.
        </p>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    (function () {
        var options = {
            key: @json($razorpayKey),
            order_id: @json($order_id),
            name: @json(config('app.name', 'Checkout')),
            description: @json(ucfirst(str_replace('_', ' ', $kind))),
            handler: function () {
                // Settlement happens via the signature-verified webhook;
                // this redirect just lands the fan back in the product.
                window.location.href = @json($returnUrl);
            },
        };
        var open = function () { new Razorpay(options).open(); };
        document.getElementById('rzp-open').addEventListener('click', open);
        open();
    })();
</script>
@endsection
