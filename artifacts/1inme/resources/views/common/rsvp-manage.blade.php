<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Manage your RSVP — {{ $link->title ?: $link->alias }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <style>
        body { background:#f5f3ff; min-height:100vh; padding:24px; }
        .rsvp-card { max-width:560px; margin: 0 auto; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(91,33,182,0.12); overflow:hidden; }
        .rsvp-header { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:28px; }
        .rsvp-body { padding:28px; background:#fff; }
        .response-pill { cursor:pointer; border:2px solid #e5e7eb; border-radius:14px; padding:14px; text-align:center; font-weight:600; transition:all .15s; background:#fff; }
        .response-pill input { display:none; }
        .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:#ecfdf5; color:#047857; }
        .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:#fffbeb; color:#b45309; }
        .response-pill.is-no:has(input:checked) { border-color:#9ca3af; background:#f3f4f6; color:#374151; }
        .btn-purple { background:#3d6bff; color:#fff; border:none; }
        .btn-purple:hover { background:#2342c7; color:#fff; }
        .pill { display:inline-block; padding:.25rem .6rem; border-radius:999px; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
        .pill-confirmed { background:#dcfce7; color:#166534; }
        .pill-waitlist { background:#fef3c7; color:#92400e; }
        .pill-cancelled { background:#fee2e2; color:#991b1b; }
        .ticket-box { border:1px dashed #c4b5fd; border-radius:14px; padding:16px; text-align:center; background:#faf9ff; }
        .ticket-box .qr-wrap { background:#fff; padding:10px; border-radius:10px; border:1px solid #e5e7eb; display:inline-block; }
    </style>
</head>
<body>
@php
    $s = (array)($link->settings ?? []);
    $allowPlusOnes = !empty($s['rsvp_allow_plus_ones']);
    $rsvpSettings  = (array)($s['rsvp_settings'] ?? []);
    $questions     = (array)($rsvpSettings['questions'] ?? []);
    $ticket = $rsvp->ticket;
    $ticketQr = null;
    if ($ticket && $ticket->isValid()) {
        $checkinUrl = route('user.events.checkin.lookup', ['link' => $link->id, 'code' => $ticket->code]);
        $ticketQr = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(180)->margin(1)->generate($checkinUrl);
    }
@endphp
<div class="card rsvp-card">
    <div class="rsvp-header">
        <div class="small opacity-75 mb-1"><i class="fas fa-pen-to-square me-1"></i> Update your RSVP</div>
        <h1 class="h4 fw-bold mb-1">{{ $link->title ?: $link->alias }}</h1>
        <div class="small">
            <span class="pill pill-{{ $rsvp->status }}">{{ $rsvp->status }}</span>
            @if($link->icsData?->start_date)
                <span class="ms-2"><i class="far fa-clock me-1"></i>
                    {{ $link->icsData->start_date->setTimezone(new \DateTimeZone($link->icsData->timezone ?: 'UTC'))->format('D, M j · g:i A') }}
                </span>
            @endif
        </div>
    </div>

    <div class="rsvp-body">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger small">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
        @endif

        @if($rsvp->status === 'cancelled')
            <div class="alert alert-secondary small">Your RSVP is cancelled. You can submit a new response below to reactivate it.</div>
        @endif

        @if($ticketQr)
            <div class="ticket-box mb-4">
                <div class="fw-semibold small mb-2"><i class="fas fa-ticket-alt me-1"></i> Your check-in ticket</div>
                <div class="qr-wrap mb-2">{!! $ticketQr !!}</div>
                <div class="small text-muted">Code: <code>{{ $ticket->code }}</code></div>
                <a href="{{ route('redirect.event.ticket', ['alias' => $link->alias, 'code' => $ticket->code]) }}" class="small" target="_blank" rel="noopener">Open full-screen ticket</a>
            </div>
        @endif

        <form method="POST" action="{{ route('redirect.rsvp.manage.update', [$link->alias, $rsvp->manage_token]) }}">
            @csrf
            <label class="form-label small fw-semibold">Will you attend?</label>
            <div class="row g-2 mb-3">
                @foreach(['yes' => ['Going','fa-check-circle','is-yes'], 'maybe' => ['Maybe','fa-question-circle','is-maybe'], 'no' => ['Can\'t make it','fa-times-circle','is-no']] as $val => $meta)
                    <div class="col-4"><label class="response-pill {{ $meta[2] }} d-block">
                        <input type="radio" name="response" value="{{ $val }}" {{ $rsvp->response === $val ? 'checked' : '' }} required>
                        <i class="fas {{ $meta[1] }} d-block mb-1"></i><span class="small">{{ $meta[0] }}</span>
                    </label></div>
                @endforeach
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Your name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $rsvp->name) }}" required maxlength="120">
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $rsvp->email) }}" maxlength="160">
            </div>
            @if(!empty($s['rsvp_collect_phone']))
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Phone</label>
                    <input type="tel" name="phone" class="form-control" value="{{ old('phone', $rsvp->phone) }}" maxlength="40">
                </div>
            @endif
            @if(!empty($rsvpSettings['collect_company']))
                <div class="mb-3"><label class="form-label small fw-semibold">Company</label>
                    <input type="text" name="company" class="form-control" value="{{ old('company', $rsvp->company) }}" maxlength="191"></div>
            @endif
            @if(!empty($rsvpSettings['collect_role']))
                <div class="mb-3"><label class="form-label small fw-semibold">Job title / role</label>
                    <input type="text" name="role" class="form-control" value="{{ old('role', $rsvp->role) }}" maxlength="191"></div>
            @endif
            @if($allowPlusOnes)
                <div class="mb-3"><label class="form-label small fw-semibold">Plus-ones</label>
                    <input type="number" name="plus_ones" class="form-control" min="0" max="20" value="{{ old('plus_ones', $rsvp->plus_ones) }}"></div>
            @endif

            @foreach($questions as $q)
                @php $label = $q['label'] ?? ''; $type = $q['type'] ?? 'text'; $opts = (array)($q['options'] ?? []);
                     $cur = $rsvp->answers[$label] ?? null; @endphp
                @continue($label === '')
                <div class="mb-3">
                    <label class="form-label small fw-semibold">{{ $label }}</label>
                    @if($type === 'select')
                        <select name="answers[{{ $label }}]" class="form-select">
                            <option value="">— pick one —</option>
                            @foreach($opts as $o)<option value="{{ $o }}" {{ $cur === $o ? 'selected' : '' }}>{{ $o }}</option>@endforeach
                        </select>
                    @elseif($type === 'checkbox')
                        @php $sel = is_array($cur) ? $cur : []; @endphp
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($opts as $o)
                                <label class="small d-flex align-items-center gap-1">
                                    <input type="checkbox" name="answers[{{ $label }}][]" value="{{ $o }}" {{ in_array($o, $sel) ? 'checked' : '' }}> {{ $o }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <input type="text" name="answers[{{ $label }}]" class="form-control" value="{{ is_string($cur) ? $cur : '' }}" maxlength="500">
                    @endif
                </div>
            @endforeach

            <div class="mb-3">
                <label class="form-label small fw-semibold">Message</label>
                <textarea name="message" class="form-control" rows="2" maxlength="1000">{{ old('message', $rsvp->message) }}</textarea>
            </div>

            <button class="btn btn-purple w-100 fw-semibold py-2"><i class="fas fa-save me-1"></i> Update RSVP</button>
        </form>

        @if($rsvp->status !== 'cancelled')
            <form method="POST" action="{{ route('redirect.rsvp.manage.cancel', [$link->alias, $rsvp->manage_token]) }}" class="mt-3"
                  onsubmit="return confirm('Cancel your RSVP for this event?')">
                @csrf
                <button class="btn btn-outline-danger w-100 btn-sm">
                    <i class="fas fa-times me-1"></i> Cancel my RSVP
                </button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
