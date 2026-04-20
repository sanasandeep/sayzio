@extends('user.layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px">
  <h1 class="h3 mb-3">Confirm upgrade</h1>
  <div class="card mb-3"><div class="card-body">
    <div class="mb-2"><strong>Current plan:</strong> {{ $current->plan->name }}</div>
    <div class="mb-2"><strong>New plan:</strong> {{ $target->name }}</div>
    <div class="mb-2"><strong>Days remaining in cycle:</strong> {{ $calc['days_left'] }} / {{ $calc['days_in_cycle'] }}</div>
    <div class="mb-2"><strong>Prorated charge:</strong>
      {{ number_format($calc['amount_minor']/100, 2) }} {{ $current->currency }}
      <small class="text-muted">(tax shown at checkout)</small>
    </div>
    <div class="text-muted small">Your renewal date remains
      {{ \Carbon\Carbon::parse($current->current_period_end)->toFormattedDateString() }}.</div>
  </div></div>

  <form method="POST" action="{{ route('user.billing.upgrade.handoff') }}">
    @csrf
    <input type="hidden" name="plan_id" value="{{ $target->id }}">
    <label class="form-label">Payment method</label>
    <select name="gateway" class="form-select mb-3" required>
      @foreach ($gateways as $g)
        <option value="{{ $g->slug() }}">{{ $g->displayName() }}</option>
      @endforeach
    </select>
    <div class="d-flex gap-2">
      <a href="{{ route('user.billing.show') }}" class="btn btn-outline-secondary">Cancel</a>
      <button class="btn btn-primary">Continue to payment</button>
    </div>
  </form>
</div>
@endsection
