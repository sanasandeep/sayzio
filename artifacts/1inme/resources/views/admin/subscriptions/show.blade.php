@extends('admin.layouts.app')

@section('title', 'Subscription #' . $subscription->id)

@section('content')
<div class="container" style="max-width:960px">
    <h1 class="mb-3">Subscription #{{ $subscription->id }}</h1>
    <div class="card mb-4">
        <div class="card-body">
            <div><strong>User:</strong> {{ $subscription->user?->name }} &lt;{{ $subscription->user?->email }}&gt;</div>
            <div><strong>Plan:</strong> {{ $subscription->plan?->name }} ({{ $subscription->billing_cycle }})</div>
            <div><strong>Status:</strong> <span class="badge bg-secondary">{{ $subscription->status }}</span></div>
            <div><strong>Period:</strong>
                {{ optional($subscription->current_period_start)->toDateString() }} -
                {{ optional($subscription->current_period_end)->toDateString() }}
            </div>
            @if ($subscription->grace_until)
                <div><strong>Grace until:</strong> {{ $subscription->grace_until->toDateString() }}</div>
            @endif
            @if ($subscription->cancel_at_period_end)
                <div class="text-warning"><strong>Cancellation scheduled</strong> at period end.</div>
            @endif
            @if ($subscription->replaced_by_id)
                <div><strong>Replaced by:</strong> #{{ $subscription->replaced_by_id }}</div>
            @endif
        </div>
    </div>

    <h2 class="h4 mb-3">Lifecycle timeline</h2>
    <ol class="list-group list-group-numbered mb-4">
        @forelse ($events as $e)
            <li class="list-group-item">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="badge bg-info me-1">{{ $e['kind'] }}</span>
                        {{ $e['label'] }}
                    </div>
                    <small class="text-muted">{{ $e['at'] ? \Illuminate\Support\Carbon::parse($e['at'])->toDayDateTimeString() : '' }}</small>
                </div>
            </li>
        @empty
            <li class="list-group-item text-muted">No events.</li>
        @endforelse
    </ol>

    <h2 class="h4 mb-3">Invoices</h2>
    <table class="table">
        <thead><tr><th>Number</th><th>Issued</th><th>Status</th><th class="text-end">Total</th></tr></thead>
        <tbody>
        @foreach ($invoices as $inv)
            <tr>
                <td><a href="{{ route('admin.invoices.show', $inv) }}">{{ $inv->number }}</a></td>
                <td>{{ optional($inv->issued_at)->toDateString() }}</td>
                <td>{{ $inv->status }}</td>
                <td class="text-end">{{ number_format($inv->grand_total_minor / 100, 2) }} {{ $inv->currency }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
