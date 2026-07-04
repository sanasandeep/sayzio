@extends('user.layouts.app')
@section('title', 'Client Invoices')
@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="page-hero mb-6 flex items-center justify-between">
        <div>
            <h1 class="hero-title">Client Invoices</h1>
            <p class="hero-subtitle">Invoices generated from your kanban cards.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.client-invoices.receipts.create') }}" class="px-3 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-receipt mr-1"></i> New Receipt
            </a>
            <a href="{{ route('user.client-invoices.create') }}" class="px-3 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-file-invoice mr-1"></i> New Invoice
            </a>
            <a href="{{ route('user.tasks.index') }}" class="px-3 py-2 rounded-lg text-sm font-semibold border" style="border-color: var(--border-strong); color: var(--text-primary);">
                <i class="fas fa-columns mr-1"></i> Boards
            </a>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @foreach(['draft' => 'Draft','sent' => 'Outstanding','overdue' => 'Overdue','paid' => 'Paid'] as $st => $label)
            @php($t = $totals->get($st))
            <a href="?status={{ $st }}" class="p-4 rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
                <div class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">{{ $label }}</div>
                <div class="text-2xl font-bold mt-1" style="color: var(--text-primary);">{{ (int) ($t->c ?? 0) }}</div>
                <div class="text-xs" style="color: var(--text-muted);">{{ number_format(((int)($t->amt ?? 0)) / 100, 2) }}</div>
            </a>
        @endforeach
    </div>

    <div class="rounded-xl border" style="border-color: var(--border-soft); background: var(--bg-card);">
        <table class="w-full text-sm">
            <thead><tr style="color: var(--text-muted);">
                <th class="text-left p-3">Number</th>
                <th class="text-left p-3">Status</th>
                <th class="text-left p-3">Recipient</th>
                <th class="text-right p-3">Amount</th>
                <th class="text-left p-3">Issued</th>
                <th></th>
            </tr></thead>
            <tbody>
            @forelse($invoices as $inv)
                <tr style="border-top: 1px solid var(--border-soft); color: var(--text-primary);">
                    <td class="p-3 font-mono">{{ $inv->number }}</td>
                    <td class="p-3">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" style="background: rgba(61,107,255,0.12); color: #3d6bff;">{{ strtoupper($inv->status) }}</span>
                        @if(!empty($sendFailedMap[$inv->id]) && $inv->status !== 'paid')
                            <span class="ml-1 inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full" style="background: rgba(225,29,72,0.12); color:#e11d48;" title="The last attempt to email this invoice failed — it was not delivered.">
                                <i class="fas fa-triangle-exclamation mr-1"></i> SEND FAILED
                            </span>
                        @endif
                    </td>
                    <td class="p-3">{{ $inv->recipient_email ?? '—' }}</td>
                    <td class="p-3 text-right">{{ strtoupper($inv->currency) }} {{ number_format($inv->grand_total_minor / 100, 2) }}</td>
                    <td class="p-3">{{ optional($inv->issued_at)->format('Y-m-d') }}</td>
                    <td class="p-3 text-right">
                        <div class="inline-flex items-center gap-2">
                            @if(!empty($sendFailedMap[$inv->id]) && $inv->status !== 'paid')
                                <form action="{{ route('user.client-invoices.send', $inv) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold" style="color:#e11d48;" title="Retry sending the invoice email">
                                        <i class="fas fa-rotate-right mr-1"></i>Retry
                                    </button>
                                </form>
                                @if(!empty($payUrls[$inv->id]))
                                    <a href="{{ $payUrls[$inv->id] }}" target="_blank" rel="noopener" class="text-xs font-semibold" style="color: var(--text-muted);" title="Open the manual pay link to share">Pay link</a>
                                @endif
                            @endif
                            <a href="{{ route('user.client-invoices.edit', $inv) }}" class="text-xs font-semibold" style="color: #3d6bff;">Open →</a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="p-6 text-center" style="color: var(--text-muted);">No client invoices yet. Pick billable cards on a board to start one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $invoices->links() }}</div>
</div>
@endsection
