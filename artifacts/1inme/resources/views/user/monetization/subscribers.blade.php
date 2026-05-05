@extends('user.layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif

    {{-- Status filter --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach(['active' => 'Active', 'past_due' => 'Past due', 'canceled' => 'Canceled', 'all' => 'All'] as $key => $label)
            @php $active = $status === $key; @endphp
            <a href="{{ route('user.monetization.subscribers', ['status' => $key]) }}"
               class="px-3 py-1.5 text-xs font-semibold rounded-full border"
               style="background: {{ $active ? '#8b5cf6' : 'transparent' }}; color: {{ $active ? 'white' : 'var(--text-secondary)' }}; border-color: {{ $active ? '#8b5cf6' : 'var(--border-color)' }};">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="rounded-xl border" style="border-color: var(--border-color); background: var(--bg-card);">
        @if($subs->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider" style="color: var(--text-faint);">
                            <th class="px-4 py-3">Fan</th>
                            <th class="px-4 py-3">Tier</th>
                            <th class="px-4 py-3">Cycle</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Renews</th>
                            <th class="px-4 py-3">Last seen</th>
                            <th class="px-4 py-3 text-right">LTV</th>
                            <th class="px-4 py-3 text-right">MRR</th>
                            <th class="px-4 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @foreach($subs as $sub)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        @if($sub->fan?->avatar)
                                            <img src="{{ $sub->fan->avatar }}" class="w-8 h-8 rounded-full object-cover" alt="">
                                        @else
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-semibold text-xs"
                                                 style="background: rgba(139,92,246,0.15); color: #8b5cf6;">
                                                {{ strtoupper(mb_substr($sub->fan?->name ?? '?', 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary);">{{ $sub->fan?->name ?? 'Fan #'.$sub->fan_user_id }}</div>
                                            @if($sub->fan?->handle)
                                                <div class="text-xs" style="color: var(--text-faint);">@{{ $sub->fan->handle }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($sub->tier)
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold"
                                              style="background: rgba(139,92,246,0.12); color: #8b5cf6;">
                                            {{ $sub->tier->badge ? $sub->tier->badge . ' ' : '' }}{{ $sub->tier->name }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-faint);">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3" style="color: var(--text-secondary);">{{ ucfirst($sub->billing_cycle) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold"
                                          style="background: rgba(var(--{{ $sub->statusColor() }}-rgb,148,163,184),0.12);">
                                        {{ $sub->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-faint);">
                                    {{ optional($sub->current_period_end)->format('M j, Y') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-faint);">
                                    @php
                                        $lastAt = $lastActiveMap[$sub->fan_user_id] ?? null;
                                        $lastDt = $lastAt ? \Illuminate\Support\Carbon::parse($lastAt) : null;
                                    @endphp
                                    {{ $lastDt ? $lastDt->diffForHumans() : '—' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs" style="color: var(--text-secondary);">
                                    ${{ number_format(($ltvMap[$sub->fan_user_id] ?? 0) / 100, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold" style="color: var(--text-primary);">
                                    ${{ number_format($sub->price_cents / 100, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <a href="{{ route('user.subscribers.compose', ['to' => $sub->fan_user_id]) }}"
                                           class="text-xs px-2 py-1 rounded border whitespace-nowrap"
                                           style="border-color: var(--border-color); color: var(--text-secondary);">
                                            <i class="far fa-paper-plane mr-1"></i> Message
                                        </a>
                                        @if(in_array($sub->status, ['active','trialing','past_due']))
                                            <form method="POST" action="{{ route('user.monetization.refund') }}" onsubmit="return confirm('Refund this subscriber and revoke access?');">
                                                @csrf
                                                <input type="hidden" name="source" value="sub">
                                                <input type="hidden" name="reference_id" value="{{ $sub->id }}">
                                                <button type="submit" class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: var(--text-secondary);">Refund</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t" style="border-color: var(--border-color);">{{ $subs->links() }}</div>
        @else
            <div class="p-8 text-center text-sm" style="color: var(--text-faint);">
                No subscribers in this view yet. Once fans subscribe to your page, they'll appear here.
            </div>
        @endif
    </div>
</div>
@endsection
