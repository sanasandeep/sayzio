@extends('user.layouts.app')

@section('content')
<div class="max-w-6xl mx-auto p-4 md:p-6">
    @include('user.monetization._nav')

    @if(session('success'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(16,185,129,0.12); color: #10b981;">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 rounded-lg text-sm" style="background: rgba(239,68,68,0.12); color: #ef4444;">{{ session('error') }}</div>@endif

    <div class="rounded-xl border" style="border-color: var(--border-color); background: var(--bg-card);">
        @if($events->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider" style="color: var(--text-faint);">
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Fan</th>
                            <th class="px-4 py-3">Provider</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y" style="border-color: var(--border-color);">
                        @foreach($events as $e)
                            <tr>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-faint);">
                                    {{ optional($e->occurred_at)->format('M j, Y · g:ia') }}
                                </td>
                                <td class="px-4 py-3" style="color: var(--text-primary);">
                                    {{ $e->describeShort() }}
                                    <div class="text-[11px] uppercase tracking-wider mt-0.5" style="color: var(--text-faint);">{{ $e->source }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($e->fan)
                                        <div class="flex items-center gap-2">
                                            @if($e->fan->avatar)
                                                <img src="{{ \App\Support\PublicStorageUrl::resolve($e->fan->avatar) }}" class="w-6 h-6 rounded-full object-cover" alt="">
                                            @endif
                                            <span class="text-sm" style="color: var(--text-secondary);">{{ $e->fan->name }}</span>
                                        </div>
                                    @else
                                        <span style="color: var(--text-faint);">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">{{ $e->gateway ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold" style="color: {{ $e->amount_cents >= 0 ? '#10b981' : '#ef4444' }};">
                                    {{ $e->amount_cents >= 0 ? '+' : '−' }}${{ number_format(abs($e->amount_cents) / 100, 2) }} {{ strtoupper($e->currency) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($e->source === 'tip' && $e->amount_cents > 0 && $e->reference_id)
                                        <form method="POST" action="{{ route('user.monetization.refund') }}" onsubmit="return confirm('Refund this tip?');">
                                            @csrf
                                            <input type="hidden" name="source" value="tip">
                                            <input type="hidden" name="reference_id" value="{{ $e->reference_id }}">
                                            <button type="submit" class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: var(--text-secondary);">Refund</button>
                                        </form>
                                    @elseif($e->source === 'ppv' && $e->amount_cents > 0 && $e->reference_id)
                                        <form method="POST" action="{{ route('user.monetization.refund') }}" onsubmit="return confirm('Refund this unlock and revoke access?');">
                                            @csrf
                                            <input type="hidden" name="source" value="ppv">
                                            <input type="hidden" name="reference_id" value="{{ $e->reference_id }}">
                                            <button type="submit" class="text-xs px-2 py-1 rounded border" style="border-color: var(--border-color); color: var(--text-secondary);">Refund</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t" style="border-color: var(--border-color);">{{ $events->links() }}</div>
        @else
            <div class="p-8 text-center text-sm" style="color: var(--text-faint);">
                Your payment ledger is empty. Once fans pay, every event lands here for reconciliation.
            </div>
        @endif
    </div>
</div>
@endsection
