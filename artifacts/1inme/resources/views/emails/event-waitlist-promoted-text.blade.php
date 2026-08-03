Hi {{ $rsvp->name }},

@if($paid)
Good news: a spot just opened up for {{ $title }}, and you're next on the waitlist.

Since this is a paid ticket, your spot isn't reserved automatically. Grab it before someone else does:

{{ \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/' . $link->alias)) }}

@if($tier)
Tier: {{ $tier->name }} ({{ $tier->priceLabel() }})
@endif
@else
Great news: a spot opened up for {{ $title }} and you've been moved off the waitlist. You're confirmed!

@if($link->icsData?->start_date)
When:  {{ $link->icsData->start_date->setTimezone(new \DateTimeZone(\App\Support\PlatformTimezone::resolve($link->icsData?->timezone)))->format('D, M j Y · g:i A') }} ({{ \App\Support\PlatformTimezone::resolve($link->icsData?->timezone) }})
@endif
@if($link->icsData?->location)
Where: {{ $link->icsData->location }}
@endif

View or update your RSVP:
{{ \App\Modules\Common\Support\PlatformHosts::outboundUrl($rsvp->manageUrl()) }}
@endif

- Sent automatically by Sayzio
