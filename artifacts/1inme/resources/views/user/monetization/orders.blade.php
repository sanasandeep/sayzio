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
                            \App\Modules\User\Models\ProductOrder::STATUS_REFUNDED   => '#f59e0b',
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
                                @if($order->isRefundable())
                                    <div x-data="{ open: false }" class="relative">
                                        <button type="button" @click="open = true" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold" style="background: rgba(239,68,68,0.12); color: #ef4444;">
                                            <i class="fas fa-rotate-left"></i> Refund
                                        </button>
                                        <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-[60] flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
                                            <div @click.outside="open = false" class="w-full max-w-md rounded-xl border p-5" style="border-color: var(--border-color); background: var(--bg-card);">
                                                <h3 class="text-sm font-bold" style="color: var(--text-primary);">Refund & cancel order #{{ $order->id }}?</h3>
                                                <p class="mt-1 text-xs" style="color: var(--text-faint);">
                                                    This refunds {{ strtoupper($order->currency) }} {{ number_format($order->subtotal_cents / 100, 2) }} to the buyer, cancels the order@if($order->contains_digital) and revokes their digital downloads@endif. This can't be undone.
                                                </p>
                                                <form method="POST" action="{{ route('user.monetization.orders.refund', $order->id) }}" class="mt-4">
                                                    @csrf
                                                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Reason (optional, shared with buyer)</label>
                                                    <input type="text" name="refund_reason" maxlength="280" placeholder="e.g. Out of stock" class="w-full px-3 py-2 rounded-lg text-sm border bg-transparent" style="border-color: var(--border-color); color: var(--text-primary);">
                                                    <div class="mt-4 flex justify-end gap-2">
                                                        <button type="button" @click="open = false" class="px-3 py-2 rounded-lg text-xs font-semibold border" style="border-color: var(--border-color); color: var(--text-secondary);">Cancel</button>
                                                        <button type="submit" class="px-3 py-2 rounded-lg text-xs font-semibold" style="background: #ef4444; color: #fff;">Refund & cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($order->conversation_id)
                                    <a href="{{ route('user.inbox.dms.thread', $order->conversation_id) }}" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border" style="border-color: var(--border-color); color: var(--text-secondary);">
                                        <i class="fas fa-comment"></i> Message
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if($order->status === \App\Modules\User\Models\ProductOrder::STATUS_REFUNDED && ($order->refunded_at || $order->refund_reason))
                            <div class="mt-2 text-xs" style="color: #f59e0b;">
                                <i class="fas fa-rotate-left mr-1"></i>
                                Refunded{{ $order->refunded_at ? ' on '.$order->refunded_at->format('M j, Y') : '' }}@if($order->refund_reason) · {{ $order->refund_reason }}@endif
                            </div>
                        @endif

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
                <p class="mt-1 text-xs" style="color: var(--text-faint);">Add a Product block to your Link in Bio with native checkout enabled to start selling.</p>
            </div>
        @endif
    </div>
</div>
@endsection
