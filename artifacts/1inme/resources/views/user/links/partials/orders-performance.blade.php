@php
    $performanceTotal = $performanceSummary['orders'];
    $performanceCompleted = $performanceSummary['by_status']['completed'] ?? ['count' => 0, 'value' => 0];
    $performanceBillable = $performanceTotal - $performanceSummary['cancelled'];
@endphp
<section class="os-wrap">
    <h2 class="font-bold mb-2">Order performance</h2>
    <p class="text-sm mb-4" style="color:var(--text-muted)">For the selected date range. Rates include every order; item averages exclude cancellations. Refresh this page to update these statistics.</p>
    <div class="os-tiles">
        <div class="os-tile"><div class="os-k">Completed</div><div class="os-v">{{ $performanceTotal ? number_format(100 * $performanceCompleted['count'] / $performanceTotal, 1) : '0.0' }}%</div><div class="os-s">{{ $performanceCompleted['count'] }} of {{ $performanceTotal }} orders</div></div>
        <div class="os-tile"><div class="os-k">Cancelled</div><div class="os-v">{{ $performanceTotal ? number_format(100 * $performanceSummary['cancelled'] / $performanceTotal, 1) : '0.0' }}%</div><div class="os-s">{{ $performanceSummary['cancelled'] }} orders</div></div>
        <div class="os-tile"><div class="os-k">Completed order value</div><div class="os-v">{{ $performanceCurrency }} {{ number_format($performanceCompleted['value'], 2) }}</div><div class="os-s">Order totals, not payment receipts</div></div>
        <div class="os-tile"><div class="os-k">Units per order</div><div class="os-v">{{ $performanceBillable ? number_format($performanceInsights['sold'] / $performanceBillable, 1) : '0.0' }}</div><div class="os-s">Cancelled orders excluded</div></div>
    </div>
    <details class="mt-4"><summary class="cursor-pointer font-bold">Daily order and value trends</summary>
        <p class="text-sm my-2" style="color:var(--text-muted)">Latest 31 dates with sales within the selected range. Cancelled orders excluded. Dates use the application's stored order timezone.</p>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th class="text-left p-2">Date</th><th class="text-right p-2">Orders</th><th class="text-right p-2">Order value ({{ $performanceCurrency }})</th></tr></thead><tbody>
        @forelse($performanceInsights['daily'] ?? [] as $performanceDay)
            <tr><td class="p-2">{{ $performanceDay['day'] }}</td><td class="text-right p-2">{{ $performanceDay['orders'] }}</td><td class="text-right p-2">{{ number_format($performanceDay['value'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="p-2">No sales in this range.</td></tr>
        @endforelse
        </tbody></table></div>
    </details>
</section>
