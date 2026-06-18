@extends('public.monetization._shell', ['pageTitle' => 'Thank you'])

@section('content')
<div class="max-w-md mx-auto px-4 py-12">
    <div class="rounded-2xl border p-6 text-center" style="border-color: var(--border-color); background: var(--bg-card);">
        <span class="w-14 h-14 mx-auto rounded-full flex items-center justify-center" style="background: rgba(16,185,129,0.12); color:#10b981;">
            <i class="fas fa-check text-2xl"></i>
        </span>
        <h1 class="mt-4 text-xl font-bold" style="color: var(--text-primary);">{{ $message }}</h1>
        <p class="mt-1 text-sm" style="color: var(--text-faint);">Order #{{ $order->id }} · {{ strtoupper($order->currency) }} {{ number_format($order->subtotal_cents / 100, 2) }}</p>

        <div class="mt-6 space-y-2 text-left">
            @foreach($order->items as $item)
                <div class="flex items-center justify-between gap-3 rounded-xl border p-3" style="border-color: var(--border-color);">
                    <div class="min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--text-primary);">{{ $item->name }}@if($item->quantity > 1) <span style="color: var(--text-faint);">×{{ $item->quantity }}</span>@endif</p>
                        <p class="text-xs" style="color: var(--text-faint);">{{ ucfirst($item->product_type) }} · {{ strtoupper($item->currency) }} {{ number_format($item->unit_price_cents / 100, 2) }}</p>
                    </div>
                    @if($isBuyer && $item->isDigital() && $item->digital_file_url)
                        <a href="{{ route('store.download', ['order' => $order->id, 'item' => $item->id, 'token' => $order->public_token]) }}"
                           class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold"
                           style="background: rgba(99,91,255,0.12); color:#635bff;">
                            <i class="fas fa-download"></i> Download
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        @if($order->contains_physical)
            <p class="mt-5 text-xs" style="color: var(--text-faint);">The seller has been notified and will arrange delivery of your physical item(s).</p>
        @endif

        @if($order->creator)
            <a href="{{ url('/' . ($order->creator->handle ?: $order->creator->id)) }}"
               class="mt-6 inline-block text-sm font-medium" style="color:#635bff;">← Back to {{ '@' . ($order->creator->handle ?: $order->creator->id) }}</a>
        @endif
    </div>
</div>
@endsection
