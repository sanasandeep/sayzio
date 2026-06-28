@extends('user.layouts.app')
@section('title', 'Receipt ' . $receipt->number)
@section('content')
@php
    $cur = strtoupper($invoice->currency ?: 'USD');
    $money = fn ($m) => $cur . ' ' . number_format(((int) $m) / 100, 2);
    $company = $invoice->billingCompany;
@endphp
<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Receipt {{ $receipt->number }}</h1>
            <p class="hero-subtitle">For invoice {{ $invoice->number }}</p>
        </div>
        <a href="{{ route('user.client-invoices.edit', $invoice) }}" class="hero-back"><i class="fas fa-arrow-left"></i></a>
    </div>

    <div class="p-6 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
        <div class="flex items-start justify-between mb-6">
            <div>
                @if($company)
                    <h2 class="font-bold text-lg" style="color: var(--text-primary);">{{ $company->name }}</h2>
                    @if($company->email)<p class="text-xs" style="color: var(--text-muted);">{{ $company->email }}</p>@endif
                    @if($company->tax_id_value)<p class="text-xs" style="color: var(--text-muted);">{{ $company->tax_id_label ?: 'Tax ID' }}: {{ $company->tax_id_value }}</p>@endif
                @endif
            </div>
            <span class="text-[10px] px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">PAID</span>
        </div>

        <dl class="grid grid-cols-2 gap-3 text-sm mb-6">
            <div><dt class="text-xs" style="color: var(--text-muted);">Receipt #</dt><dd style="color: var(--text-primary);">{{ $receipt->number }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted);">Date</dt><dd style="color: var(--text-primary);">{{ optional($receipt->created_at)->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted);">Method</dt><dd style="color: var(--text-primary);">{{ ucfirst($receipt->method ?: 'manual') }}@if($receipt->gateway) · {{ $receipt->gateway }}@endif</dd></div>
            <div><dt class="text-xs" style="color: var(--text-muted);">Billed to</dt><dd style="color: var(--text-primary);">{{ $invoice->recipient_email ?: '—' }}</dd></div>
            @if($receipt->gateway_ref)<div><dt class="text-xs" style="color: var(--text-muted);">Reference</dt><dd style="color: var(--text-primary);">{{ $receipt->gateway_ref }}</dd></div>@endif
        </dl>

        <table class="w-full text-sm mb-4">
            <thead><tr style="color: var(--text-muted);"><th class="text-left p-2">Description</th><th class="text-right p-2">Qty</th><th class="text-right p-2">Amount</th></tr></thead>
            <tbody>
            @foreach((array) $invoice->line_items as $li)
                <tr style="color: var(--text-primary);">
                    <td class="p-2">{{ $li['label'] ?? '' }}</td>
                    <td class="p-2 text-right">{{ $li['quantity'] ?? 1 }}</td>
                    <td class="p-2 text-right">{{ $money(($li['amount_minor'] ?? 0) * ($li['quantity'] ?? 1)) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="flex justify-end">
            <div class="w-56 text-sm space-y-1" style="color: var(--text-primary);">
                @if((int) $invoice->discount_minor > 0)<div class="flex justify-between"><span style="color: var(--text-muted);">Discount</span><span>-{{ $money($invoice->discount_minor) }}</span></div>@endif
                <div class="flex justify-between"><span style="color: var(--text-muted);">Tax</span><span>{{ $money($invoice->tax_total_minor) }}</span></div>
                <div class="flex justify-between font-bold border-t pt-1" style="border-color: var(--border-soft);"><span>Total paid</span><span>{{ $money($invoice->grand_total_minor) }}</span></div>
                @if((int) $invoice->refundedTotalMinor() > 0)
                    <div class="flex justify-between text-rose-600"><span>Refunded</span><span>-{{ $money($invoice->refundedTotalMinor()) }}</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
