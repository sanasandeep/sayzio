@extends('user.layouts.app')
@section('title', 'Ledger Report')
@section('content')
@php
    $t = $report['totals'];
    $cur = $report['currency'];
    $money = fn ($m) => $cur . ' ' . number_format($m / 100, 2);
@endphp
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Ledger / P&amp;L</h1>
            <p class="hero-subtitle">Income, refunds, expenses, tax &amp; profit for the selected period.</p>
        </div>
        <a href="{{ route('user.billing.ledger.export', request()->query()) }}" class="btn-primary"><i class="fas fa-download mr-2"></i>Export CSV</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-6 p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
        <label class="text-xs" style="color: var(--text-muted);">From<input type="date" name="from" value="{{ $from->toDateString() }}" class="block mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
        <label class="text-xs" style="color: var(--text-muted);">To<input type="date" name="to" value="{{ $to->toDateString() }}" class="block mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);"></label>
        <label class="text-xs" style="color: var(--text-muted);">Company
            <select name="company" class="block mt-1 p-2 rounded-lg border" style="background: var(--bg-glass-input); border-color: var(--border-soft); color: var(--text-primary);">
                <option value="">All</option>
                @foreach($companies as $co)<option value="{{ $co->id }}" @selected($companyId == $co->id)>{{ $co->name }}</option>@endforeach
            </select>
        </label>
        <button class="btn-primary">Apply</button>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @php
            $cards = [
                ['Net income', $t['net_income_minor'], '#22c55e'],
                ['Expenses', $t['expense_minor'] + $t['expense_tax_minor'], '#ef4444'],
                ['Tax collected', $t['tax_collected_minor'], '#f59e0b'],
                ['Profit', $t['profit_minor'], '#3d6bff'],
            ];
        @endphp
        @foreach($cards as [$label, $val, $color])
            <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                <p class="text-xs" style="color: var(--text-muted);">{{ $label }}</p>
                <p class="text-xl font-bold mt-1" style="color: {{ $color }};">{{ $money($val) }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Invoices ({{ $t['invoice_count'] }})</h2>
            <table class="w-full text-sm">
                <thead><tr style="color: var(--text-muted);"><th class="text-left p-1">#</th><th class="text-left p-1">Paid</th><th class="text-right p-1">Amount</th></tr></thead>
                <tbody>
                @forelse($report['invoices'] as $inv)
                    <tr style="color: var(--text-primary);"><td class="p-1">{{ $inv['number'] }}</td><td class="p-1">{{ $inv['paid_at'] }}</td><td class="p-1 text-right">{{ $money($inv['amount_minor']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="p-2 text-center" style="color: var(--text-muted);">No paid invoices.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Expenses ({{ $t['expense_count'] }})</h2>
            <table class="w-full text-sm">
                <thead><tr style="color: var(--text-muted);"><th class="text-left p-1">Date</th><th class="text-left p-1">Vendor</th><th class="text-right p-1">Amount</th></tr></thead>
                <tbody>
                @forelse($report['expenses'] as $exp)
                    <tr style="color: var(--text-primary);"><td class="p-1">{{ $exp['spent_at'] }}</td><td class="p-1">{{ $exp['vendor'] ?: '—' }}</td><td class="p-1 text-right">{{ $money($exp['amount_minor'] + $exp['tax_minor']) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="p-2 text-center" style="color: var(--text-muted);">No expenses.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
