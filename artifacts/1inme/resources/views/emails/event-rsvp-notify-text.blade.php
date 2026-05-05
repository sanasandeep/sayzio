@php
    $resp = \App\Modules\User\Models\Rsvp::RESPONSES[$rsvp->response] ?? $rsvp->response;
    $status = \App\Modules\User\Models\Rsvp::STATUSES[$rsvp->status] ?? $rsvp->status;
@endphp
A new RSVP just came in for {{ $link->title ?: $link->alias }}.

Name:     {{ $rsvp->name }}
Email:    {{ $rsvp->email ?: '—' }}
Phone:    {{ $rsvp->phone ?: '—' }}
@if($rsvp->company)Company:  {{ $rsvp->company }}@endif

@if($rsvp->role)Role:     {{ $rsvp->role }}@endif

Response: {{ $resp }} ({{ $status }})
@if($rsvp->plus_ones)Plus-ones: {{ $rsvp->plus_ones }}@endif


@if($rsvp->message)Message:
{{ $rsvp->message }}
@endif

@if(!empty($rsvp->answers))
Custom answers:
@foreach($rsvp->answers as $q => $a)
  - {{ $q }}: {{ is_array($a) ? implode(', ', $a) : $a }}
@endforeach
@endif

View the guest list:
{{ url('/user/links/' . $link->id . '/rsvps') }}
