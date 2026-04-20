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
            <span class="font-mono text-white">{{ number_format($invoice->grand_total_minor / 100, 2) }} {{ $currency }}</span>
        </div>
    </div>

    <div id="paypal-button-container" class="rounded-2xl border border-white/10 bg-white/[0.02] p-5"></div>

    <p class="text-xs text-white/40 text-center">
        You'll see PayPal's secure checkout window. Don't close this page until
        you've completed or cancelled the payment.
    </p>

    <noscript>
        <p class="text-xs text-rose-300 text-center">
            JavaScript is required to complete this payment. Please enable it and reload.
        </p>
    </noscript>
</div>

@php
    $sdkParams = http_build_query([
        'client-id' => $client_id,
        'currency'  => $currency,
        'intent'    => $subscription_id ? 'subscription' : 'capture',
        'vault'     => $subscription_id ? 'true' : 'false',
    ]);
    $sdkExtra = $subscription_id ? '&components=buttons' : '';
@endphp
<script src="https://www.paypal.com/sdk/js?{{ $sdkParams }}{{ $sdkExtra }}"
        data-namespace="paypal"></script>
<script>
    (function () {
        var invoiceNumber = @json($invoice->number);
        var paymentConfig = @if ($subscription_id)
            {
                createSubscription: function () { return @json($subscription_id); },
                onApprove: function () {
                    window.location.href = '/user/billing?paid=' + encodeURIComponent(invoiceNumber);
                }
            }
        @else
            {
                createOrder: function () { return @json($order_id); },
                onApprove: function (data, actions) {
                    return actions.order.capture().then(function () {
                        window.location.href = '/user/billing?paid=' + encodeURIComponent(invoiceNumber);
                    });
                }
            }
        @endif;
        paymentConfig.onCancel = function () {
            window.location.href = '/user/billing?cancelled=' + encodeURIComponent(invoiceNumber);
        };
        paymentConfig.onError = function () {
            window.location.href = '/user/billing?failed=' + encodeURIComponent(invoiceNumber);
        };
        if (window.paypal && paypal.Buttons) {
            paypal.Buttons(paymentConfig).render('#paypal-button-container');
        }
    })();
</script>
@endsection
