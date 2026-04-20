@extends('user.layouts.app')
@section('title', 'Manual payment instructions')
@section('content')
<div class="max-w-2xl mx-auto p-6 space-y-4">
    <h1 class="text-2xl font-semibold text-white">Thanks — your order is pending approval</h1>
    <p class="text-white/60 text-sm">Your invoice <span class="font-mono text-white">{{ $invoice->number }}</span> has been created. Pay the amount below through bank transfer / UPI and your plan will be activated within one business day.</p>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 text-sm text-white/80 space-y-2">
        <div class="flex justify-between"><span class="text-white/50">Amount</span><span class="font-mono text-white">{{ number_format($invoice->grand_total_minor/100, 2) }} {{ $invoice->currency }}</span></div>
        @if($payee_name)<div class="flex justify-between"><span class="text-white/50">Payee</span><span class="text-white">{{ $payee_name }}</span></div>@endif
        @if($bank_details)
            <div class="pt-2 border-t border-white/10"><div class="text-white/50 mb-1">Bank details</div><pre class="whitespace-pre-wrap text-white/80 text-xs">{{ $bank_details }}</pre></div>
        @endif
        @if($upi_id)<div class="flex justify-between"><span class="text-white/50">UPI</span><span class="font-mono text-white">{{ $upi_id }}</span></div>@endif
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] p-5 text-sm text-white/70 whitespace-pre-wrap">{{ $instructions }}</div>

    <div class="flex gap-3">
        <a href="{{ route('user.invoices.pdf', $invoice) }}" class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/15 text-white text-sm">Download invoice PDF</a>
        <a href="{{ route('user.upgrade') }}" class="px-4 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white/70 text-sm">Back</a>
    </div>
</div>
@endsection
