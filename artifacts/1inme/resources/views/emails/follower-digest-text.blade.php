Hi {{ $userName }},

Here's what creators you follow have been up to since your last digest
({{ $totalUpdates }} update{{ $totalUpdates === 1 ? '' : 's' }} from {{ $creatorCount }} creator{{ $creatorCount === 1 ? '' : 's' }}):

@foreach($creators as $c)
* {{ $c['name'] }}@if(!empty($c['url'])) — {{ $c['url'] }}@endif

@foreach($c['messages'] as $m)
    - {{ $m }}
@endforeach
@if(!empty($c['extra']) && $c['extra'] > 0)
    - …and {{ $c['extra'] }} more update{{ $c['extra'] === 1 ? '' : 's' }}
@endif

@endforeach
You're receiving the daily digest from 1INME. To switch to instant emails or
turn this off, visit your profile notification settings.
