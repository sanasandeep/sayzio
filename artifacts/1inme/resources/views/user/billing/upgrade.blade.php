@extends('user.layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h3 mb-3">Upgrade your plan</h1>
  <p class="text-muted">Upgrading charges the full plan price and starts a fresh billing cycle today. Time left on your current plan isn't deducted — if any is left over, our team can review it for an optional credit.</p>
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row">
  @forelse ($plans as $plan)
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5>{{ $plan->name }}</h5>
          <p class="text-muted">{{ $plan->description }}</p>
          @php
            $priced = \App\Services\PricingResolver::priceForCurrency(
                $plan,
                (string) $current->currency,
                $current->billing_cycle === 'annual' ? 'annual' : 'monthly'
            );
          @endphp
          <div class="h4 mb-3">
            {{ $priced['formatted'] }}
            <span class="text-muted small">/{{ $current->billing_cycle === 'annual' ? 'yr' : 'mo' }}</span>
          </div>
          <form method="POST" action="{{ route('user.billing.upgrade.confirm') }}">
            @csrf
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <button class="btn btn-primary w-100">Preview upgrade</button>
          </form>
        </div>
      </div>
    </div>
  @empty
    <div class="col"><div class="alert alert-info">No higher-tier plans available for this cycle.</div></div>
  @endforelse
  </div>
</div>
@endsection
