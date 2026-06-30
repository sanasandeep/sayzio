@extends('user.layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h3 mb-3">Change to a lower plan</h1>
  <p class="text-muted">
    A downgrade is scheduled and applies at the end of your current billing period —
    you keep your current plan and its features until then, and nothing is charged now.
    Looking to move to the Free plan instead? Use <a href="{{ route('user.billing.show') }}">Cancel at period end</a>.
  </p>
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  @if (!empty($scheduledDowngradePlan))
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div>
        You already have a downgrade to <strong>{{ $scheduledDowngradePlan->name }}</strong> scheduled for
        {{ \Carbon\Carbon::parse($current->current_period_end)->toFormattedDateString() }}.
      </div>
      <form method="POST" action="{{ route('user.billing.downgrade.cancel') }}">@csrf
        <button class="btn btn-outline-secondary btn-sm">Cancel scheduled downgrade</button>
      </form>
    </div>
  @endif

  <div class="row">
  @forelse ($plans as $plan)
    <div class="col-md-4 mb-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5>{{ $plan->name }}</h5>
          <p class="text-muted small">{{ $plan->description }}</p>
          <div class="h4 mb-3">
            {{ number_format($plan->getAttribute('downgrade_price_minor') / 100, 2) }} {{ $current->currency }}
            <span class="text-muted small">/{{ $current->billing_cycle === 'annual' ? 'yr' : 'mo' }}</span>
          </div>

          @php $lost = $plan->getAttribute('downgrade_lost_addons') ?? []; @endphp
          @if (count($lost))
            <div class="alert alert-warning small py-2">
              <i class="fas fa-exclamation-triangle"></i>
              These add-ons aren't available on {{ $plan->name }} and will be removed when the downgrade applies:
              <strong>{{ implode(', ', $lost) }}</strong>.
            </div>
          @endif

          <form method="POST" action="{{ route('user.billing.downgrade.schedule') }}" class="mt-auto"
                onsubmit="return window.themedConfirmSubmit(this, {title: 'Schedule downgrade to {{ addslashes($plan->name) }}?', message: 'This applies at the end of your current billing period. You keep your current plan until then and can cancel anytime before it applies.', confirmText: 'Schedule downgrade', confirmIcon: 'fa-arrow-down', iconClass: 'fa-arrow-down'})">
            @csrf
            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
            <button class="btn btn-outline-primary w-100">Schedule downgrade</button>
          </form>
        </div>
      </div>
    </div>
  @empty
    <div class="col"><div class="alert alert-info">No lower-tier paid plans are available for this cycle.</div></div>
  @endforelse
  </div>
</div>
@endsection
