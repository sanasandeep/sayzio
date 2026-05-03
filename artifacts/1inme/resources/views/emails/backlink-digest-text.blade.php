Hi {{ $userName }},

The 1INME backlink radar found {{ $totalBacklinks }} new mention{{ $totalBacklinks === 1 ? '' : 's' }} across {{ $propertyCount }} of your propert{{ $propertyCount === 1 ? 'y' : 'ies' }} in the last 7 days:

@foreach($properties as $p)
== {{ $p['property_label'] }}: {{ $p['property_value'] ?: $p['matched_url'] }} ==

@foreach($p['mentions'] as $m)
- {{ $m['page_title'] ?: $m['page_host'] ?: $m['page_url'] }}
  {{ $m['page_url'] }}
@if(!empty($m['anchor_text']))
  Anchor: "{{ $m['anchor_text'] }}"
@endif

@endforeach
@endforeach

You're receiving this weekly digest because the backlink radar found new mentions of your properties.
Unsubscribe in one click: {{ $unsubscribeUrl }}
Or change your preferences in your notification settings.
