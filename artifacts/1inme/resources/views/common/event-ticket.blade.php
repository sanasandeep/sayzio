<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your ticket — {{ $link->title }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    @include('common.partials.fontawesome')
    <style>
        body { background:#f5f3ff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .ticket-card { max-width:420px; width:100%; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(61,107,255,0.12); overflow:hidden; text-align:center; }
        .ticket-header { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:24px; }
        .ticket-body { padding:24px; background:#fff; }
        .qr-wrap { background:#fff; padding:12px; border-radius:12px; border:1px solid #e5e7eb; display:inline-block; }
        .status-badge { font-size:.8rem; }
    </style>
</head>
<body>
<div class="card ticket-card">
    <div class="ticket-header">
        <div class="small opacity-75 mb-1"><i class="fas fa-ticket-alt me-1"></i> Ticket</div>
        <h1 class="h5 fw-bold mb-0">{{ $link->title }}</h1>
    </div>
    <div class="ticket-body">
        @if($link->icsData && $link->icsData->start_date)
            <div class="small text-muted mb-3">
                <i class="far fa-clock me-1"></i>
                {{ $link->icsData->start_date->setTimezone(new \DateTimeZone($link->icsData->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                @if($link->icsData->location) · <i class="fas fa-map-marker-alt ms-1 me-1"></i>{{ $link->icsData->location }}@endif
            </div>
        @endif

        <div class="qr-wrap mb-3">{!! $qr !!}</div>

        <div class="fw-semibold">{{ $ticket->attendee_name }}</div>
        <div class="small text-muted mb-2">{{ $ticket->tier?->name ?? ($ticket->rsvp_id ? 'RSVP' : null) }} &middot; Qty {{ $ticket->quantity }}</div>
        <div class="small text-muted mb-3">Code: <code>{{ $ticket->code }}</code></div>

        @if($ticket->status === \App\Modules\User\Models\EventTicket::STATUS_CHECKED_IN)
            @php($scannerName = $ticket->checkedInBy?->name)
            <span class="badge bg-success status-badge">Checked in{{ $ticket->checked_in_at ? ' · ' . $ticket->checked_in_at->format('g:i A') : '' }}{{ $scannerName ? ' by ' . $scannerName : '' }}</span>
        @elseif(in_array($ticket->status, ['cancelled', 'refunded']))
            <span class="badge bg-secondary status-badge">{{ ucfirst($ticket->status) }}</span>
        @else
            <span class="badge bg-primary status-badge">Valid — show this at the door</span>
        @endif
    </div>
</div>
</body>
</html>
