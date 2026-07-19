<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket check-in: {{ $link->title }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <style>
        body { background:#f5f3ff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .card-lookup { max-width:420px; width:100%; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(61,107,255,0.12); overflow:hidden; text-align:center; }
        .card-header { padding:24px; color:#fff; }
        .card-body { padding:24px; background:#fff; }
    </style>
</head>
<body>
<div class="card card-lookup">
    @if(!$isOwner)
        <div class="card-header bg-secondary">
            <h1 class="h5 fw-bold mb-0"><i class="fas fa-lock me-1"></i> Sign in required</h1>
        </div>
        <div class="card-body">
            <p class="text-muted">Sign in as the event owner or an authorized team member to check in this ticket.</p>
            <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
        </div>
    @elseif(!$ticket)
        <div class="card-header bg-danger">
            <h1 class="h5 fw-bold mb-0"><i class="fas fa-times-circle me-1"></i> Ticket not found</h1>
        </div>
        <div class="card-body"><p class="text-muted mb-0">No ticket matches this code for this event.</p></div>
    @else
        <div class="card-header" style="background: {{ $result['ok'] ? '#10b981' : '#f59e0b' }};">
            <h1 class="h5 fw-bold mb-0">
                <i class="fas {{ $result['ok'] ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-1"></i>
                {{ $result['ok'] ? 'Checked in' : 'Not checked in' }}
            </h1>
        </div>
        <div class="card-body">
            <p class="mb-1 fw-semibold">{{ $result['message'] }}</p>
            @if(!empty($result['ticket']))
                <div class="text-muted small mt-2">
                    {{ $result['ticket']['attendee_name'] }} &middot; {{ $result['ticket']['tier_name'] }} &middot; qty {{ $result['ticket']['quantity'] }}
                </div>
                @if($result['status'] === 'already_checked_in' && !empty($result['ticket']['checked_in_at']))
                    <div class="text-muted small mt-1">
                        Previously checked in {{ \Illuminate\Support\Carbon::parse($result['ticket']['checked_in_at'])->format('g:i A') }}
                        @if(!empty($result['ticket']['checked_in_by'])) by {{ $result['ticket']['checked_in_by'] }}@endif
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
</body>
</html>
