<section class="os-wrap">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">Upcoming preorders <span class="ro-btn">{{ $preordersCount }}</span></h2>
        <div class="flex gap-2">
            <a class="ro-btn" href="{{ route('user.links.'.$preorderKind.'.orders', $link) }}">Orders by placement date</a>
            <a class="ro-btn" href="{{ route('user.links.'.$preorderKind.'.orders', ['link' => $link, 'schedule' => 'upcoming']) }}">All upcoming preorders</a>
        </div>
    </div>
    <p class="text-sm my-2" style="color:var(--text-muted)">Open orders scheduled for the future, including orders placed before the selected date range. Showing the next 20, earliest first.</p>
    @php($preorderZone = $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault())
    <div class="grid md:grid-cols-2 gap-2">
    @forelse($preorders as $preorder)
        <div class="os-tile">
            <strong>#{{ $preorder->token_number ?: $preorder->id }}</strong> · {{ $preorder->status_label }}
            <p>{{ $preorder->wanted_at->copy()->timezone($preorderZone)->format('D j M, g:i a') }} · {{ \App\Modules\User\Support\MenuFulfilment::label($preorder->fulfilment ?: ($preorderKind === 'restaurant' ? 'dine_in' : 'takeaway'), $preorderKind === 'restaurant') }}</p>
            <p class="text-sm">{{ $preorder->customer_name ?: 'Customer' }} · {{ $preorder->currency }} {{ number_format($preorder->total, 2) }}</p>
        </div>
    @empty
        <p class="text-sm">No upcoming preorders.</p>
    @endforelse
    </div>
</section>
