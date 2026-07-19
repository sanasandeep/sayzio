Hi {{ $userName }},

@if(!empty($isExample))
This is an example preview of your daily digest using made-up creators;
you don't have any pending creator updates yet. When creators you follow
post something, your real digest will look like this.

@elseif(!empty($isSample) && $totalUpdates === 0)
This is a sample preview of your daily digest. You don't have any new creator
updates waiting right now; when creators you follow post something, it will
show up here in your next digest.

@else
@if(!empty($isSample))
This is a sample preview of your daily digest, using your {{ $totalUpdates }} update{{ $totalUpdates === 1 ? '' : 's' }} from {{ $creatorCount }} creator{{ $creatorCount === 1 ? '' : 's' }} currently waiting in your queue.
@else
Here's what creators you follow have been up to since your last digest
({{ $totalUpdates }} update{{ $totalUpdates === 1 ? '' : 's' }} from {{ $creatorCount }} creator{{ $creatorCount === 1 ? '' : 's' }}):
@endif

@foreach($creators as $c)
* {{ $c['name'] }}@if(!empty($c['url'])) - {{ $c['url'] }}@endif

@foreach($c['messages'] as $m)
    - {{ is_array($m) ? ($m['text'] ?? '') : $m }}
@endforeach
@if(!empty($c['extra']) && $c['extra'] > 0)
    - …and {{ $c['extra'] }} more update{{ $c['extra'] === 1 ? '' : 's' }}
@endif

@endforeach
@endif
You're receiving the daily digest from Sayzio. To switch to instant emails or
turn this off, visit your profile notification settings.
