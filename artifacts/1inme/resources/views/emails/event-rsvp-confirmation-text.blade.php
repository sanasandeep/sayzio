Hi {{ $rsvp->name }},

@if($rsvp->status === 'waitlist')
You've been added to the waitlist for {{ $title }}. We'll email you the moment a spot opens up.
@else
Thanks for your RSVP — you're {{ $rsvp->response === 'yes' ? 'confirmed' : ($rsvp->response === 'maybe' ? 'down as a maybe' : 'marked as not attending') }} for {{ $title }}.
@endif

@php
    $ics = $link->icsData;
    $tz  = $ics?->timezone ?: 'UTC';
@endphp
@if($ics && empty($rsvp->occurrences) && $ics->start_date)
When: {{ $ics->start_date->setTimezone(new \DateTimeZone($tz))->format('D, M j Y · g:i A') }} ({{ $tz }})
@elseif($ics && !empty($rsvp->occurrences))
You picked these dates:
@foreach($rsvp->occurrences as $key)
  - {{ str_replace('#', ' (slot ', $key) . ')' }}
@endforeach
@endif
@if($ics && $ics->location)
Where: {{ $ics->location }}
@endif

Edit or cancel your RSVP any time:
{{ $rsvp->manageUrl() }}

— Sent automatically by Sayzio
