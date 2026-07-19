Hi {{ $rsvp->name }},

Just a quick reminder that {{ $title }} is coming up.

When:  {{ \Carbon\Carbon::instance($occurrence)->setTimezone(new \DateTimeZone(\App\Support\PlatformTimezone::resolve($link->icsData?->timezone)))->format('D, M j Y · g:i A') }} ({{ \App\Support\PlatformTimezone::resolve($link->icsData?->timezone) }})
@if($link->icsData?->location)
Where: {{ $link->icsData->location }}
@endif

Need to update your RSVP?
{{ $rsvp->manageUrl() }}

- Sent automatically by Sayzio
