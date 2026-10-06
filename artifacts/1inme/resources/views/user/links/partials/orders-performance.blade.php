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
        <div class="overflow-x-auto"><table class="app-collection-table w-full text-sm"><thead><tr><th class="text-left p-2">Date</th><th class="text-right p-2">Orders</th><th class="text-right p-2">Order value ({{ $performanceCurrency }})</th></tr></thead><tbody>
        @forelse($performanceInsights['daily'] ?? [] as $performanceDay)
            <tr><td class="p-2">{{ $performanceDay['day'] }}</td><td class="text-right p-2">{{ $performanceDay['orders'] }}</td><td class="text-right p-2">{{ number_format($performanceDay['value'], 2) }}</td></tr>
        @empty
            <tr><td colspan="3" class="p-2">No sales in this range.</td></tr>
        @endforelse
        </tbody></table></div>
    </details>
</section>
<section class="os-wrap">
    <h2 class="font-bold mb-2">Service, customers and payments</h2>
    <form data-list-filters method="get" class="flex flex-wrap items-center gap-2 mb-4">
        @foreach(request()->except(['target_minutes', 'page']) as $performanceKey => $performanceFilter)
            @if(is_scalar($performanceFilter))
                <input type="hidden" name="{{ $performanceKey }}" value="{{ $performanceFilter }}">
            @endif
        @endforeach
        <label for="performance-target">Preparation target (minutes)</label>
        <input id="performance-target" name="target_minutes" type="number" min="1" max="240" value="{{ $performance['target'] }}" class="ro-btn" style="width:90px">
        <button class="ro-btn" type="submit">Apply</button>
    </form>
    <div class="os-tiles">
        @foreach(['prep' => 'Average time to ready', 'collection' => 'Ready to collection'] as $performanceMetric => $performanceLabel)
            <div class="os-tile"><div class="os-k">{{ $performanceLabel }}</div><div class="os-v">{{ count($performance[$performanceMetric]) ? number_format(array_sum($performance[$performanceMetric]) / count($performance[$performanceMetric]), 1).' min' : 'Unavailable' }}</div><div class="os-s">{{ count($performance[$performanceMetric]) }} orders with recorded timestamps</div></div>
        @endforeach
        <div class="os-tile"><div class="os-k">Late orders</div><div class="os-v">{{ $performance['late'] }}</div><div class="os-s">Not ready after target; scheduled orders start at wanted time</div></div>
        <div class="os-tile"><div class="os-k">Repeat customers</div><div class="os-v">{{ $performance['identified_customers'] ? number_format(100 * $performance['repeat_customers'] / $performance['identified_customers'], 1).'%' : 'Unavailable' }}</div><div class="os-s">{{ $performance['repeat_customers'] }} of {{ $performance['identified_customers'] }} identified customers ordered twice in this range</div></div>
        <div class="os-tile"><div class="os-k">Bulk orders</div><div class="os-v">{{ $performance['billable'] ? number_format(100 * $performance['bulk_orders'] / $performance['billable'], 1) : '0.0' }}%</div><div class="os-s">{{ $performance['bulk_orders'] }} coupon-issuing orders · {{ $performanceCurrency }} {{ number_format($performance['bulk_value'], 2) }}</div></div>
        <div class="os-tile"><div class="os-k">Paid extras</div><div class="os-v">{{ $performanceCurrency }} {{ number_format($performance['extras_value'], 2) }}</div><div class="os-s">{{ $performance['extras_units'] }} item units with paid extras</div></div>
        <div class="os-tile"><div class="os-k">Recorded collections</div><div class="os-v">{{ $performanceCurrency }} {{ number_format($performance['collected'], 2) }}</div><div class="os-s">{{ $performance['payment_records'] }} payment records; cancelled orders excluded</div></div>
        <div class="os-tile"><div class="os-k">Balance on recorded payments</div><div class="os-v">{{ $performanceCurrency }} {{ number_format($performance['unpaid_value'], 2) }}</div><div class="os-s">{{ $performance['billable'] - $performance['payment_records'] }} orders have no payment information</div></div>
    </div>
    <p class="text-sm mt-3" style="color:var(--text-muted)">Timing starts with status changes recorded after this update. Use Record payment on an order to enter the cumulative amount collected; completing an order does not record a payment.</p>
    <details class="mt-4"><summary class="font-bold cursor-pointer">Previous period comparison</summary>
        @if($performance['previous'])
            <p class="my-2">Equal elapsed window immediately before this range. Cancelled orders excluded.</p>
            @foreach(['orders' => ['Orders', $performance['billable']], 'value' => ['Order value', $performance['value']]] as $performanceKey => $performanceComparison)
                @php($performanceBefore = $performance['previous'][$performanceKey])
                <p>{{ $performanceComparison[0] }}: {{ number_format($performanceComparison[1], $performanceKey === 'value' ? 2 : 0) }} vs {{ number_format($performanceBefore, $performanceKey === 'value' ? 2 : 0) }} previously · {{ $performanceBefore > 0 ? number_format(100 * ($performanceComparison[1] - $performanceBefore) / $performanceBefore, 1).'%' : 'No previous baseline' }}</p>
            @endforeach
        @else
            <p>No previous comparison for an unbounded date range.</p>
        @endif
    </details>
    <div class="grid md:grid-cols-3 gap-4 mt-4">
        @foreach(['hours' => 'Peak hours', 'weekdays' => 'Peak weekdays', 'reasons' => 'Cancellation reasons'] as $performanceKey => $performanceLabel)
            <div><h3 class="font-bold">{{ $performanceLabel }}</h3>
                @forelse($performance[$performanceKey] as $performanceLabelValue => $performanceCount)
                    <p class="text-sm">{{ $performanceKey === 'hours' ? \App\Modules\User\Support\MenuInsights::hourLabel((int) $performanceLabelValue) : $performanceLabelValue }}: {{ $performanceCount }}</p>
                @empty
                    <p class="text-sm">No data in this range.</p>
                @endforelse
            </div>
        @endforeach
    </div>
    <p class="text-sm mt-2" style="color:var(--text-muted)">Peak hours and weekdays use the owner's timezone. Customers without a phone or contact are excluded from the repeat rate.</p>
</section>
