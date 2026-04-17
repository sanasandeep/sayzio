@php
    $s = (array)($link->settings ?? []);
    $allowPlusOnes = !empty($s['rsvp_allow_plus_ones']);
    $collectPhone  = !empty($s['rsvp_collect_phone']);
@endphp
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

    @if($allowPlusOnes)
        <div class="mb-3">
            <label class="form-label small fw-semibold">Bringing extra guests?</label>
            <input type="number" name="plus_ones" class="form-control" value="{{ old('plus_ones', 0) }}" min="0" max="20">
        </div>
    @endif

    <div class="mb-3">
        <label class="form-label small fw-semibold">Message <span class="text-muted">(optional)</span></label>
        <textarea name="message" class="form-control" rows="2" maxlength="1000">{{ old('message') }}</textarea>
    </div>

    <button type="submit" class="btn btn-purple w-100 fw-semibold py-2">
        <i class="fas fa-paper-plane me-1"></i> Send RSVP
    </button>
</form>
