@extends('user.partials.layout')

@section('content')
<div class="container py-4">
  <h1 class="h3 mb-3">Upgrade your plan</h1>
  <p class="text-muted">You'll be charged a prorated amount for the remaining days in your current cycle.</p>
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="row">
  @forelse ($plans as $plan)
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body">
          <h5>{{ $plan->name }}</h5>
          <p class="text-muted">{{ $plan->description }}</p>
          <div class="h4 mb-3">
            @if ($current->billing_cycle === 'annual')
              {{ number_format($plan->annual_price, 2) }} /yr
            @else
              {{ number_format($plan->monthly_price, 2) }} /mo
            @endif
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
