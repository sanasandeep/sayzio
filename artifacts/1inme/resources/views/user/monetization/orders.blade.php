@extends('user.layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif

    <div class="rounded-xl border" style="border-color: var(--border-color); background: var(--bg-card);">
        @if($orders->count())
            <div class="divide-y" style="border-color: var(--border-color);">
                @foreach($orders as $order)
                    @php
                        $statusTint = match($order->status) {
                            \App\Modules\User\Models\ProductOrder::STATUS_PAID       => '#3b82f6',
                            \App\Modules\User\Models\ProductOrder::STATUS_FULFILLED  => '#10b981',
                            \App\Modules\User\Models\ProductOrder::STATUS_CANCELLED  => '#ef4444',
                            default => '#64748b',
                        };
                    @endphp
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: rgba(245,158,11,0.12); color: #f59e0b;">
                                    <i class="fas fa-bag-shopping"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                        Order #{{ $order->id }}
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold" style="background: {{ $statusTint }}1f; color: {{ $statusTint }};">
                                            {{ $order->statusLabel() }}
                                        </span>
                                        @if($order->contains_physical)
                                            <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold" style="background: rgba(139,92,246,0.12); color: #8b5cf6;"><i class="fas fa-truck mr-1"></i>Physical</span>
                                        @endif
                                    </div>
                                    <div class="text-xs" style="color: var(--text-faint);">
                                        {{ optional($order->paid_at)->format('M j, Y · g:ia') ?? '—' }}
                                        @if($order->buyer)
                                            · {{ $order->buyer->name ?? ('Buyer #'.$order->buyer_user_id) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <div class="text-sm font-bold" style="color: var(--text-primary);">{{ strtoupper($order->currency) }} {{ number_format($order->subtotal_cents / 100, 2) }}</div>
                                    <div class="text-[11px]" style="color: var(--text-faint);">{{ ucfirst($order->gateway) }}</div>
                                </div>
                                @if($order->status === \App\Modules\User\Models\ProductOrder::STATUS_PAID && $order->contains_physical)
                                    <form method="POST" action="{{ route('user.monetization.orders.fulfill', $order->id) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold" style="background: rgba(16,185,129,0.12); color: #10b981;">
                                            <i class="fas fa-check"></i> Mark fulfilled
                                        </button>
                                    </form>
                                @endif
                                @if($order->conversation_id)
                                    <a href="{{ route('user.inbox.dms.thread', $order->conversation_id) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border" style="border-color: var(--border-color); color: var(--text-secondary);">
                                        <i class="fas fa-comment"></i> Message
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 pl-13 space-y-1.5">
                            @foreach($order->items as $item)
                                <div class="flex items-center justify-between gap-3 text-xs" style="color: var(--text-secondary);">
                                    <span class="truncate">{{ $item->name }}@if($item->quantity > 1) ×{{ $item->quantity }}@endif <span class="opacity-60">({{ ucfirst($item->product_type) }})</span></span>
                                    <span class="flex-shrink-0">{{ strtoupper($item->currency) }} {{ number_format($item->lineTotalCents() / 100, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="p-4 border-t" style="border-color: var(--border-color);">{{ $orders->links() }}</div>
        @else
            <div class="p-12 text-center">
                <span class="w-14 h-14 mx-auto rounded-full flex items-center justify-center" style="background: rgba(245,158,11,0.12); color: #f59e0b;"><i class="fas fa-bag-shopping text-2xl"></i></span>
                <p class="mt-3 text-sm font-medium" style="color: var(--text-primary);">No product orders yet</p>
                <p class="mt-1 text-xs" style="color: var(--text-faint);">Add a Product block to your biolink with native checkout enabled to start selling.</p>
            </div>
        @endif
    </div>
</div>
@endsection
