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

    <form id="payu-form" method="POST" action="{{ $action }}">
        @foreach($fields as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <button type="submit"
            class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium">
            Pay with PayU
        </button>
    </form>

    <p class="text-xs text-white/40 text-center">
        You'll be taken to PayU's secure checkout. Don't close this page until
        the payment completes.
    </p>

    <noscript>
        <p class="text-xs text-amber-300 text-center">
            JavaScript is disabled — tap “Pay with PayU” above to continue.
        </p>
    </noscript>
</div>

<script>
    (function () {
        var form = document.getElementById('payu-form');
        if (form) setTimeout(function () { form.submit(); }, 400);
    })();
</script>
@endsection
