@extends('user.partials.layout')

@section('content')
<div class="container py-4">
  <h1 class="h3 mb-4">Billing</h1>

  @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  @if ($subscription)
    @if (in_array($subscription->status, ['past_due', 'grace']) && $graceDaysRemaining !== null)
      <div class="alert alert-warning">
        <strong>Your renewal failed.</strong>
        Your {{ $subscription->plan->name ?? 'plan' }} features will end in
        <strong>{{ $graceDaysRemaining }}</strong> day{{ $graceDaysRemaining === 1 ? '' : 's' }}
        (on {{ \Carbon\Carbon::parse($subscription->grace_until)->toFormattedDateString() }}).
        Please update payment to keep your plan.
      </div>
    @endif

    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1">{{ $subscription->plan->name ?? '—' }}
              <span class="badge bg-secondary text-uppercase">{{ $subscription->billing_cycle }}</span>
              <span class="badge bg-info">{{ $subscription->status }}</span>
            </h5>
            <div class="text-muted">
              Next renewal:
              {{ \Carbon\Carbon::parse($subscription->current_period_end)->toFormattedDateString() }}
            </div>
            @if ($subscription->cancel_at_period_end)
              <div class="text-danger mt-1">Will cancel at end of current period.</div>
            @endif
          </div>
          <div class="text-end">
            <a href="{{ route('user.billing.upgrade') }}" class="btn btn-primary btn-sm">Upgrade plan</a>
            @if ($subscription->cancel_at_period_end)
              <form method="POST" action="{{ route('user.billing.resume') }}" class="d-inline">@csrf
                <button class="btn btn-outline-secondary btn-sm">Resume</button>
              </form>
            @else
              <form method="POST" action="{{ route('user.billing.cancel') }}" class="d-inline"
                    onsubmit="return confirm('Stop renewing at period end?');">@csrf
                <button class="btn btn-outline-danger btn-sm">Cancel at period end</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>
  @else
    <div class="alert alert-info">You're on the Free plan. <a href="{{ route('user.upgrade') }}">See plans</a>.</div>
  @endif

  <h5>Invoices</h5>
  <div class="table-responsive mb-4">
    <table class="table table-sm">
      <thead><tr><th>Number</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @forelse ($invoices as $inv)
        <tr>
          <td><code>{{ $inv->number }}</code></td>
          <td>{{ \Carbon\Carbon::parse($inv->issued_at)->toFormattedDateString() }}</td>
          <td>{{ number_format($inv->grand_total_minor / 100, 2) }} {{ $inv->currency }}</td>
          <td>{{ $inv->status }}</td>
          <td class="text-end">
            <a href="{{ route('user.invoices.pdf', $inv) }}" target="_blank">PDF</a>
            @if (in_array($inv->id, $refundableInvoices))
              <form method="POST" action="{{ route('user.billing.refund', $inv) }}" class="d-inline ms-2"
                    onsubmit="return confirm('Refund and downgrade to Free?');">@csrf
                <button class="btn btn-link btn-sm text-danger p-0">Refund &amp; downgrade</button>
              </form>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-muted">No invoices yet.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  <h5>Credit notes</h5>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead><tr><th>Number</th><th>Date</th><th>Amount</th><th></th></tr></thead>
      <tbody>
      @forelse ($creditNotes as $cn)
        <tr>
          <td><code>{{ $cn->number }}</code></td>
          <td>{{ \Carbon\Carbon::parse($cn->issued_at)->toFormattedDateString() }}</td>
          <td>{{ number_format($cn->amount_minor / 100, 2) }} {{ $cn->currency }}</td>
          <td class="text-end"><a href="{{ route('user.billing.credit-note.pdf', $cn) }}" target="_blank">PDF</a></td>
        </tr>
      @empty
        <tr><td colspan="4" class="text-muted">No credit notes yet.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
