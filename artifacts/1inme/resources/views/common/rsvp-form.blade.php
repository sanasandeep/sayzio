<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RSVP — {{ $link->title }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f5f3ff; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .rsvp-card { max-width:520px; width:100%; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(91,33,182,0.12); overflow:hidden; }
        .rsvp-header { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:28px; }
        .rsvp-body { padding:28px; background:#fff; }
        .response-pill { cursor:pointer; border:2px solid #e5e7eb; border-radius:14px; padding:14px; text-align:center; font-weight:600; transition:all .15s; background:#fff; }
        .response-pill input { display:none; }
        .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:#ecfdf5; color:#047857; }
        .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:#fffbeb; color:#b45309; }
        .response-pill.is-no:has(input:checked) { border-color:#9ca3af; background:#f3f4f6; color:#374151; }
        .btn-purple { background:#3d6bff; color:#fff; border:none; }
        .btn-purple:hover { background:#2342c7; color:#fff; }
    </style>
</head>
<body>
<div class="card rsvp-card">
    <div class="rsvp-header">
        <div class="small opacity-75 mb-1"><i class="fas fa-calendar-alt me-1"></i> You're invited to</div>
        <h1 class="h3 fw-bold mb-2">{{ $link->title }}</h1>
        @if($link->icsData && $link->icsData->start_date)
            <div class="small">
                <i class="far fa-clock me-1"></i>
                {{ $link->icsData->start_date->setTimezone(new \DateTimeZone($link->icsData->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                @if($link->icsData->location) · <i class="fas fa-map-marker-alt ms-1 me-1"></i>{{ $link->icsData->location }}@endif
            </div>
        @endif
    </div>

    <div class="rsvp-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($submitted && !session('success'))
            <div class="alert alert-info">
                <i class="fas fa-check-circle me-1"></i> You've already responded. Submit again to update your RSVP.
            </div>
        @endif

        @include('common.partials.rsvp-form-fields', ['link' => $link, 'action' => route('redirect.rsvp.submit', $link->alias), 'sourceTag' => 'event_page'])

        <div class="text-center mt-3 small text-muted">
            <a href="{{ url('/' . $link->alias) }}" class="text-muted text-decoration-none">
                <i class="fas fa-download me-1"></i> Download .ics file
            </a>
        </div>
    </div>
</div>
</body>
</html>
