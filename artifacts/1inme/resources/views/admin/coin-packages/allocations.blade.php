@extends('admin.layouts.app')

@section('title', 'Coin Purchase Allocations')
@section('page_title', 'Coin Purchase Allocations')

@section('content')
<div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
    <p class="text-sm text-white/40 ak-note"><i class="fas fa-lock mr-1"></i>Internal revenue split per completed coin purchase (API budget vs platform margin), snapshotted at purchase time. Never customer-facing.</p>
    <a href="{{ route('admin.coin-packages.index') }}" class="px-4 py-2 bg-white/10 text-white/80 rounded-xl text-sm font-medium hover:bg-white/[0.06] ak-strong"><i class="fas fa-arrow-left mr-2"></i>Back to Packages</a>
</div>

<form method="GET" action="{{ route('admin.coin-packages.allocations') }}" class="mb-6 flex items-end gap-3 flex-wrap bg-white/[0.03] border border-white/10 rounded-2xl p-4">
    <div>
        <label class="block text-xs text-white/60 mb-1 ak-muted">From</label>
        <input type="date" name="from" value="{{ $from }}" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
    </div>
    <div>
        <label class="block text-xs text-white/60 mb-1 ak-muted">To</label>
        <input type="date" name="to" value="{{ $to }}" class="px-3 py-2 bg-white/5 border border-white/10 rounded-xl text-white text-sm ak-strong ak-input">
    </div>
    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700">Filter</button>
    @if($from || $to)
        <a href="{{ route('admin.coin-packages.allocations') }}" class="px-4 py-2 text-white/60 text-sm hover:text-white ak-muted">Clear</a>
    @endif
</form>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
    @forelse($totals as $t)
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5">
            @php $sym = $t->currency === 'INR' ? '₹' : ($t->currency === 'USD' ? '$' : $t->currency . ' '); @endphp
            <div class="flex items-center justify-between mb-3">
                <span class="text-sm font-semibold text-white ak-strong">{{ $t->currency }}</span>
                <span class="text-xs text-white/40 ak-note">{{ number_format($t->purchases) }} purchase{{ $t->purchases == 1 ? '' : 's' }} · {{ number_format($t->coins) }} coins</span>
            </div>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-white/40 ak-note">Collected</span><span class="font-semibold text-white ak-strong">{{ $sym }}{{ number_format($t->amount_minor / 100, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-white/40 ak-note">API budget</span><span class="font-semibold text-sky-300 ak-blue">{{ $sym }}{{ number_format($t->api_budget_minor / 100, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-white/40 ak-note">Platform margin</span><span class="font-semibold text-emerald-300 ak-green">{{ $sym }}{{ number_format($t->margin_minor / 100, 2) }}</span></div>
            </div>
        </div>
    @empty
        <div class="col-span-full text-sm text-white/40 ak-note">No completed coin purchases in this range yet.</div>
    @endforelse
</div>

<div class="bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-white/10 text-left text-xs text-white/40 uppercase tracking-wider">
                    <th class="px-4 py-3 ak-note">Date</th>
                    <th class="px-4 py-3 ak-note">Invoice</th>
                    <th class="px-4 py-3 ak-note">User</th>
                    <th class="px-4 py-3 ak-note">Package</th>
                    <th class="px-4 py-3 text-right ak-note">Coins</th>
                    <th class="px-4 py-3 text-right ak-note">Collected</th>
                    <th class="px-4 py-3 text-right ak-note">API budget</th>
                    <th class="px-4 py-3 text-right ak-note">Margin</th>
                    <th class="px-4 py-3 text-right ak-note">Split</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $sym = $row->currency === 'INR' ? '₹' : ($row->currency === 'USD' ? '$' : $row->currency . ' '); @endphp
                    <tr class="border-b border-white/5">
                        <td class="px-4 py-3 text-white/60 whitespace-nowrap ak-muted">{{ $row->created_at?->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-3 text-white/60 ak-muted">{{ $row->invoice->number ?? ('#' . $row->invoice_id) }}</td>
                        <td class="px-4 py-3 text-white/80 ak-strong">{{ $row->user->name ?? '—' }}<span class="block text-xs text-white/30 ak-note">{{ $row->user->email ?? '' }}</span></td>
                        <td class="px-4 py-3 text-white/80 ak-strong">{{ $row->package->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-amber-300 font-semibold ak-amber">{{ number_format($row->coins) }}</td>
                        <td class="px-4 py-3 text-right text-white font-semibold ak-strong">{{ $sym }}{{ number_format($row->amount_minor / 100, 2) }}</td>
                        <td class="px-4 py-3 text-right text-sky-300 ak-blue">{{ $sym }}{{ number_format($row->api_budget_minor / 100, 2) }}</td>
                        <td class="px-4 py-3 text-right text-emerald-300 ak-green">{{ $sym }}{{ number_format($row->margin_minor / 100, 2) }}</td>
                        <td class="px-4 py-3 text-right text-white/50 whitespace-nowrap ak-muted">{{ rtrim(rtrim(number_format($row->api_budget_pct, 2, '.', ''), '0'), '.') }}% / {{ rtrim(rtrim(number_format($row->margin_pct, 2, '.', ''), '0'), '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-white/40 ak-note">No allocation rows yet; they appear as coin purchases complete.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())
        <div class="px-4 py-3 border-t border-white/10">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
