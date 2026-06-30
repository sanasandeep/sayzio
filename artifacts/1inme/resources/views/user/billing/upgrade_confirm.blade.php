@extends('user.layouts.app')

@section('content')
<div class="container py-4" style="max-width:640px">
  <h1 class="h3 mb-3">Confirm upgrade</h1>
  <div class="card mb-3"><div class="card-body">
    <div class="mb-2"><strong>Current plan:</strong> {{ $current->plan->name }}</div>
    <div class="mb-2"><strong>New plan:</strong> {{ $target->name }}</div>
    <div class="mb-2"><strong>Full {{ $cycle === 'annual' ? 'annual' : 'monthly' }} charge:</strong>
      {{ number_format($amount_minor/100, 2) }} {{ $currency }}
      <small class="text-muted">(tax shown at checkout)</small>
    </div>
    <div class="text-muted small">A fresh {{ $cycle === 'annual' ? 'annual' : 'monthly' }} cycle starts today, so your
      next renewal moves to
      {{ \Carbon\Carbon::parse(now())->addMonths($cycle === 'annual' ? 12 : 1)->toFormattedDateString() }}.</div>
    <div class="text-muted small mt-1">Any unused time on your current plan isn't deducted from this charge. If you
      have time left over, our team can review it for an optional credit afterwards.</div>
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
