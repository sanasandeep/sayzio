@extends('user.layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h3 mb-4">Billing</h1>

  @if(\App\Services\Billing\WalletService::isEnabled())
    @php
      $__wallet = app(\App\Services\Billing\WalletService::class)->walletFor(auth()->user());
      $__low = (int) ($__wallet->low_balance_threshold ?? 100);
    @endphp
    <div class="card mb-4 border-0" style="background:linear-gradient(135deg,#3d6bff20,#f59e0b20);">
      <div class="card-body d-flex align-items-center justify-content-between">
        <div>
          <div class="text-muted small text-uppercase">Coin wallet</div>
          <div class="h4 mb-0">{{ number_format($__wallet->balance) }} 🪙</div>
          @if($__low > 0 && $__wallet->balance < $__low)
            <div class="small text-warning mt-1"><i class="fas fa-exclamation-triangle"></i> Balance below {{ number_format($__low) }} coins — top up to keep using coin add-ons.</div>
          @endif
        </div>
        <div class="d-flex gap-2">
          <a href="{{ route('user.wallet.show') }}" class="btn btn-outline-secondary btn-sm">View wallet</a>
          <a href="{{ route('user.wallet.buy') }}" class="btn btn-primary btn-sm">Buy coins</a>
        </div>
      </div>
    </div>
  @endif

  @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  @if (!empty($appReturn))
    {{-- Post-payment landing for a checkout that started in the native app.
         Fire the sayzio://billing/refresh deep link so the app invalidates its
         cached plan/subscription and re-pulls the user the moment it reopens.
         The button is a manual fallback for browsers that block auto-firing a
         custom scheme without a user gesture. --}}
    <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert">
      <span><i class="fas fa-check-circle me-1"></i> Payment complete — returning you to the Sayzio app…</span>
      <a href="sayzio://billing/refresh" class="btn btn-primary btn-sm" id="billing-return-to-app">
        <i class="fas fa-mobile-screen-button me-1"></i> Return to app
      </a>
    </div>
    <script>
      (function () {
        var DEEP_LINK = 'sayzio://billing/refresh';
        // Defer the auto hand-off briefly so the success banner paints first
        // and the browser can associate the navigation with the just-finished
        // interaction rather than a bare page-load. If no app is registered
        // (e.g. this markup somehow reaches a desktop browser) the navigation
        // simply no-ops; the manual "Return to app" button is the fallback.
        window.setTimeout(function () {
          try {
            window.location.href = DEEP_LINK;
          } catch (e) {
            /* no-op — the manual button above still works */
          }
        }, 400);
      })();
    </script>
  @endif

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
            @elseif (!empty($scheduledDowngradePlan))
              <div class="text-warning mt-1">
                Scheduled to change to <strong>{{ $scheduledDowngradePlan->name }}</strong> on
                {{ \Carbon\Carbon::parse($subscription->current_period_end)->toFormattedDateString() }}.
              </div>
            @endif
          </div>
          <div class="text-end">
            <a href="{{ route('user.billing.upgrade') }}" class="btn btn-primary btn-sm">Upgrade plan</a>
            @if (!$subscription->cancel_at_period_end && empty($scheduledDowngradePlan))
              <a href="{{ route('user.billing.downgrade') }}" class="btn btn-outline-primary btn-sm">Downgrade plan</a>
            @endif
            @if (!empty($scheduledDowngradePlan))
              <form method="POST" action="{{ route('user.billing.downgrade.cancel') }}" class="d-inline">@csrf
                <button class="btn btn-outline-secondary btn-sm">Cancel downgrade</button>
              </form>
            @endif
            @if ($subscription->cancel_at_period_end)
              <form method="POST" action="{{ route('user.billing.resume') }}" class="d-inline">@csrf
                <button class="btn btn-outline-secondary btn-sm">Resume</button>
              </form>
            @else
              <form method="POST" action="{{ route('user.billing.cancel') }}" class="d-inline"
                    onsubmit="return window.themedConfirmSubmit(this, {title: 'Stop renewing at period end?', message: 'You will keep paid features until the current period ends.', confirmText: 'Cancel renewal', confirmIcon: 'fa-circle-xmark', iconClass: 'fa-circle-xmark'})">@csrf
                <button class="btn btn-outline-danger btn-sm">Cancel at period end</button>
              </form>
            @endif
          </div>
        </div>

        @if (!empty($addons) && count($addons))
          <hr class="my-3">
          <h6 class="text-muted text-uppercase small mb-2">Active add-ons</h6>
          <ul class="list-unstyled mb-0">
            @foreach ($addons as $sa)
              <li class="d-flex justify-content-between py-1 border-bottom">
                <span>
                  {{ $sa->addon->name ?? 'Add-on' }}
                  @if (($sa->qty ?? 1) > 1)
                    <span class="badge bg-light text-dark ms-1">× {{ $sa->qty }}</span>
                  @endif
                  @if (!empty($sa->addon?->type))
                    <span class="badge bg-secondary ms-1 text-uppercase small">{{ str_replace('_', ' ', $sa->addon->type) }}</span>
                  @endif
                </span>
              </li>
            @endforeach
          </ul>
        @else
          <hr class="my-3">
          <div class="text-muted small">No add-ons on this subscription.</div>
        @endif
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
                    onsubmit="return window.themedConfirmSubmit(this, {title: 'Refund and downgrade to Free?', message: 'Your subscription will be refunded and the workspace will move to the Free plan.', confirmText: 'Refund &amp; downgrade', confirmIcon: 'fa-money-bill-wave', iconClass: 'fa-money-bill-wave'})">@csrf
                {{-- Stable per-render key so a double-click / retried submit is a no-op instead of a second refund. --}}
                <input type="hidden" name="idempotency_key" value="refund-{{ $inv->id }}-{{ \Illuminate\Support\Str::uuid() }}">
                <button class="btn btn-link btn-sm text-danger p-0">Refund &amp; downgrade</button>
              </form>
            @elseif ($inv->status === 'paid' && $inv->paid_at)
              <a href="mailto:support@1inme.com?subject=Refund%20request%20for%20invoice%20{{ urlencode($inv->number) }}"
                 class="btn btn-link btn-sm text-muted p-0 ms-2"
                 title="The self-serve refund window for this invoice has closed.">
                Contact support
              </a>
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
