@extends('portal.layout')
@section('title', 'Invoices')
@section('content')
<h1 class="text-xl font-bold mb-4">Your invoices</h1>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase">
            <tr>
                <th class="text-left px-4 py-2">Number</th>
                <th class="text-left px-4 py-2">Issued</th>
                <th class="text-right px-4 py-2">Total</th>
                <th class="text-left px-4 py-2">Status</th>
                <th class="text-right px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($invoices as $invoice)
                <tr>
                    <td class="px-4 py-3 font-mono text-xs">{{ $invoice->number }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ optional($invoice->issued_at)->format('M j, Y') ?: '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">
                        {{ strtoupper($invoice->currency) }} {{ number_format($invoice->grand_total_minor / 100, 2) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-1 rounded-full
                            {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700' :
                               ($invoice->status === 'void' ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-700') }}">
                            {{ ucfirst($invoice->status ?: 'pending') }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @if($invoice->status !== 'paid')
                            <form action="{{ route('portal.invoices.pay', $invoice->id) }}" method="POST" class="inline">
                                @csrf
                                <button class="brand-btn px-3 py-1.5 rounded text-xs font-semibold">
                                    <i class="fas fa-credit-card mr-1"></i>Pay now
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400">Paid {{ optional($invoice->paid_at)->format('M j') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No invoices.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
