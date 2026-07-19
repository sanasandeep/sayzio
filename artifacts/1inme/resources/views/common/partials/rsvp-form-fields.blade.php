@php
    $s = (array)($link->settings ?? []);
    $allowPlusOnes = !empty($s['rsvp_allow_plus_ones']);
    $collectPhone  = !empty($s['rsvp_collect_phone']);
    $rsvpSettings  = (array)($s['rsvp_settings'] ?? []);
    $questions     = (array)($rsvpSettings['questions'] ?? []);
    $perOccurrence = !empty($rsvpSettings['per_occurrence']);
    $collectComp   = !empty($rsvpSettings['collect_company']);
    $collectRole   = !empty($rsvpSettings['collect_role']);
    $cap           = (int)($rsvpSettings['capacity'] ?? 0);
    $waitlist      = !empty($rsvpSettings['waitlist_enabled']);
    $deadline      = $rsvpSettings['deadline'] ?? null;
    $closed        = false;
    if ($deadline) {
        try { $closed = (new \DateTime($deadline)) < new \DateTime(); } catch (\Throwable $e) {}
    }
    $usedSeats = 0;
    if ($cap > 0) {
        $usedSeats = (int) \App\Modules\User\Models\Rsvp::query()
            ->where('link_id', $link->id)
            ->where('response', 'yes')->where('status', 'confirmed')
            ->sum(\DB::raw('plus_ones + 1'));
    }
    $occurrences = [];
    if ($perOccurrence && ($ics = $link->icsData)) {
        $occurrences = $ics->upcomingOccurrences(12);
    }
@endphp

@if($cap > 0 || $deadline)
    <div class="small text-muted mb-3">
        @if($cap > 0)
            <div><i class="fas fa-users me-1"></i>
                <strong>{{ $usedSeats }}/{{ $cap }}</strong> seats taken.
                @if($usedSeats >= $cap && $waitlist)
                    <span class="badge bg-warning text-dark ms-1">Waitlist open</span>
                @elseif($usedSeats >= $cap)
                    <span class="badge bg-danger ms-1">Full</span>
                @endif
            </div>
        @endif
        @if($deadline)
            <div><i class="far fa-clock me-1"></i> RSVP deadline: {{ \Carbon\Carbon::parse($deadline)->format('D, M j Y · g:i A') }}</div>
        @endif
    </div>
@endif

@if($closed)
    <div class="alert alert-warning small">
        <i class="fas fa-lock me-1"></i> RSVPs are closed for this event.
    </div>
@else
<form method="POST" action="{{ $action }}" class="rsvp-form">
    @csrf
    <input type="hidden" name="_source" value="{{ $sourceTag ?? 'event_page' }}">

    @if ($errors->any())
        <div class="alert alert-danger small">
            @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
        </div>
    @endif

    <label class="form-label small fw-semibold">Will you attend?</label>
    <div class="row g-2 mb-3">
        <div class="col-4"><label class="response-pill is-yes d-block"><input type="radio" name="response" value="yes" required>
            <i class="fas fa-check-circle d-block mb-1"></i><span class="small">Going</span></label></div>
        <div class="col-4"><label class="response-pill is-maybe d-block"><input type="radio" name="response" value="maybe">
            <i class="fas fa-question-circle d-block mb-1"></i><span class="small">Maybe</span></label></div>
        <div class="col-4"><label class="response-pill is-no d-block"><input type="radio" name="response" value="no">
            <i class="fas fa-times-circle d-block mb-1"></i><span class="small">Can't make it</span></label></div>
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Your name *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="120">
    </div>

    <div class="mb-3">
        <label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="160">
    </div>

    @if($collectPhone)
        <div class="mb-3">
            <label class="form-label small fw-semibold">Phone</label>
            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="40">
        </div>
    @endif

    @if($collectComp)
        <div class="mb-3">
            <label class="form-label small fw-semibold">Company</label>
            <input type="text" name="company" class="form-control" value="{{ old('company') }}" maxlength="191">
        </div>
    @endif
    @if($collectRole)
        <div class="mb-3">
            <label class="form-label small fw-semibold">Job title / role</label>
            <input type="text" name="role" class="form-control" value="{{ old('role') }}" maxlength="191">
        </div>
    @endif

    @if($allowPlusOnes)
        <div class="mb-3">
            <label class="form-label small fw-semibold">Bringing extra guests?</label>
            <input type="number" name="plus_ones" class="form-control" value="{{ old('plus_ones', 0) }}" min="0" max="20">
        </div>
    @endif

    @if($perOccurrence && !empty($occurrences))
        <div class="mb-3">
            <label class="form-label small fw-semibold">Which date(s) are you joining?</label>
            <div class="border rounded p-2" style="max-height:180px; overflow-y:auto; background:#fafafa;">
                @foreach($occurrences as $occ)
                    <label class="d-flex align-items-center gap-2 small py-1">
                        <input type="checkbox" name="occurrences[]" value="{{ $occ['key'] }}">
                        <span>{{ $occ['start']->format('D, M j · g:i A') }}@if($occ['label']), {{ $occ['label'] }}@endif</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @foreach($questions as $q)
        @php
            $label = $q['label'] ?? '';
            $type  = $q['type'] ?? 'text';
            $req   = !empty($q['required']);
            $opts  = (array) ($q['options'] ?? []);
        @endphp
        @continue($label === '')
        <div class="mb-3">
            <label class="form-label small fw-semibold">{{ $label }}@if($req) *@endif</label>
            @if($type === 'select')
                <select name="answers[{{ $label }}]" class="form-select" @if($req) required @endif>
                    <option value="">pick one</option>
                    @foreach($opts as $o)<option value="{{ $o }}">{{ $o }}</option>@endforeach
                </select>
            @elseif($type === 'checkbox')
                <div class="d-flex flex-wrap gap-2">
                    @foreach($opts as $o)
                        <label class="small d-flex align-items-center gap-1">
                            <input type="checkbox" name="answers[{{ $label }}][]" value="{{ $o }}"> {{ $o }}
                        </label>
                    @endforeach
                </div>
            @else
                <input type="text" name="answers[{{ $label }}]" class="form-control" maxlength="500" @if($req) required @endif>
            @endif
        </div>
    @endforeach

    <div class="mb-3">
        <label class="form-label small fw-semibold">Message <span class="text-muted">(optional)</span></label>
        <textarea name="message" class="form-control" rows="2" maxlength="1000">{{ old('message') }}</textarea>
    </div>

    <button type="submit" class="btn btn-purple w-100 fw-semibold py-2">
        <i class="fas fa-paper-plane me-1"></i>
        @if($cap > 0 && $usedSeats >= $cap && $waitlist) Join the waitlist @else Send RSVP @endif
    </button>
</form>
@endif
