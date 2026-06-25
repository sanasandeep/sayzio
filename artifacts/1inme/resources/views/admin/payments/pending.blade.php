@extends('admin.layouts.app')
@section('title', 'Pending Payments')
@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-white">Pending Payments</h1>
        <p class="text-sm text-white/50">Offline / manual transfers and any gateway payment that needs a human second-look.</p>
    </div>
    @if(session('success'))<div class="px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-400/30 text-emerald-200 text-sm">{{ session('success') }}</div>@endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-white/5 text-white/60 text-xs uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2 text-left">Invoice</th>
                    <th class="px-4 py-2 text-left">User</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="text-white/80">
                @forelse($invoices as $inv)
                    <tr class="border-t border-white/5 align-top">
                        <td class="px-4 py-3">
                            <div class="font-mono text-white">{{ $inv->number }}</div>
                            <div class="text-xs text-white/50">{{ $inv->created_at?->diffForHumans() }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-white">{{ $inv->user?->name }}</div>
                            <div class="text-xs text-white/50">{{ $inv->user?->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($inv->grand_total_minor/100, 2) }} {{ $inv->currency }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs bg-amber-500/20 text-amber-200">{{ $inv->status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @php($buyerRef = $buyerRefs[$inv->id] ?? null)
                            @if($buyerRef)
                                <div class="mb-1 text-xs text-emerald-200/90" title="Buyer-submitted reference — verify against your bank/UPI statement">
                                    <i class="fa-solid fa-user-check"></i> Buyer ref: <span class="font-mono">{{ $buyerRef }}</span>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('admin.payments.mark-paid', $inv) }}" class="flex items-center gap-2" onsubmit="return window.themedConfirmSubmit(this, {title: 'Mark invoice {{ $inv->number }} as paid?', message: 'The subscription will be activated immediately.', confirmText: 'Mark paid', confirmIcon: 'fa-check', iconClass: 'fa-check'})">
                                @csrf
                                <input type="text" name="reference" value="{{ $buyerRef }}" placeholder="Reference #" class="px-2 py-1 rounded bg-white/5 border border-white/10 text-white text-xs w-32">
                                <button type="submit" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium">Mark paid</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-white/40">Nothing to review — all caught up.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $invoices->links() }}

    @if($reviewAttempts->count())
        <div class="rounded-2xl border border-amber-400/20 bg-amber-500/5 p-5">
            <h2 class="text-white font-medium mb-2">Flagged gateway attempts</h2>
            <p class="text-xs text-white/50 mb-3">Payments that returned `requires_review` from a real gateway. Check the gateway dashboard before marking the invoice paid.</p>
            <table class="min-w-full text-xs">
                <thead class="text-white/50"><tr><th class="px-2 py-1 text-left">Gateway</th><th class="px-2 py-1 text-left">Ref</th><th class="px-2 py-1 text-left">Invoice</th><th class="px-2 py-1 text-left">When</th></tr></thead>
                <tbody class="text-white/80">
                    @foreach($reviewAttempts as $pa)
                        <tr class="border-t border-white/5">
                            <td class="px-2 py-1">{{ $pa->gateway }}</td>
                            <td class="px-2 py-1 font-mono">{{ $pa->gateway_ref }}</td>
                            <td class="px-2 py-1 font-mono">{{ $pa->invoice?->number }}</td>
                            <td class="px-2 py-1">{{ $pa->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
