Hi {{ $rsvp->name }},

Just a quick reminder that {{ $title }} is coming up.

When:  {{ \Carbon\Carbon::instance($occurrence)->setTimezone(new \DateTimeZone($link->icsData?->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }} ({{ $link->icsData?->timezone ?: 'UTC' }})
@if($link->icsData?->location)
Where: {{ $link->icsData->location }}
@endif

Need to update your RSVP?
{{ $rsvp->manageUrl() }}

— Sent automatically by 1INME
