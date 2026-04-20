@extends('admin.layouts.app')

@section('content')
<div class="container py-4" style="max-width:900px">
  <h1 class="h4">Invoice {{ $invoice->number }}</h1>
  @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="card mb-3"><div class="card-body">
    <div><strong>User:</strong> {{ $invoice->user?->email }}</div>
    <div><strong>Issued:</strong> {{ $invoice->issued_at }}</div>
    <div><strong>Status:</strong> {{ $invoice->status }}</div>
    <div><strong>Total:</strong> {{ number_format($invoice->grand_total_minor/100, 2) }} {{ $invoice->currency }}</div>
    <div><strong>Gateway:</strong> {{ $invoice->gateway ?? '—' }}</div>
  </div></div>

  <h5>Refunds</h5>
  <table class="table table-sm">
    <thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Gateway ref</th><th>Created</th></tr></thead>
    <tbody>
    @forelse ($invoice->refunds as $r)
      <tr>
        <td>{{ $r->id }}</td>
        <td>{{ number_format($r->amount_minor/100, 2) }} {{ $r->currency }}</td>
        <td>{{ $r->status }}</td>
        <td><code>{{ $r->gateway_ref }}</code></td>
        <td>{{ $r->created_at }}</td>
      </tr>
      @if ($r->status === 'pending')
        <tr>
          <td colspan="5" class="bg-light">
            <form method="POST" action="{{ route('admin.refunds.confirm', $r) }}" class="d-flex gap-2 align-items-end mb-0">
              @csrf
              <div>
                <label class="form-label small mb-0">Payout reference (bank/UPI/UTR)</label>
                <input type="text" name="gateway_ref" class="form-control form-control-sm" required>
              </div>
              <button class="btn btn-sm btn-success">Confirm refund paid out</button>
            </form>
          </td>
        </tr>
      @endif
    @empty
      <tr><td colspan="5" class="text-muted">No refunds.</td></tr>
    @endforelse
    </tbody>
  </table>

  @if ($invoice->status === 'paid')
  <div class="card">
    <div class="card-body">
      <h6>Issue refund</h6>
      <form method="POST" action="{{ route('admin.invoices.refund', $invoice) }}">
        @csrf
        <div class="mb-2">
          <label class="form-label">Amount ({{ $invoice->currency }})</label>
          <input type="number" step="0.01" min="0.01" max="{{ number_format($invoice->grand_total_minor/100, 2, '.', '') }}"
                 name="amount" class="form-control" value="{{ number_format($invoice->grand_total_minor/100, 2, '.', '') }}" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-check mb-2">
          <input type="checkbox" name="downgrade" value="1" class="form-check-input" id="dg" checked>
          <label class="form-check-label" for="dg">Downgrade user to Free on success</label>
        </div>
        <button class="btn btn-danger">Issue refund</button>
      </form>
    </div>
  </div>
  @endif
</div>
@endsection
