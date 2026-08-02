@php $title = $link->title ?: $link->alias; @endphp
@if($recipientName)Hi {{ $recipientName }},

@endif{{ $messageBody }}

—
About this event: {{ $title }}
@if($link->icsData?->location)
Where: {{ $link->icsData->location }}
@endif
{{ \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/' . $link->alias)) }}

You're receiving this because you RSVP'd to or hold a ticket for this event.
- Sent by the organizer via Sayzio
