Hi {{ $creator->name }},

Your week on Sayzio ({{ $periodStart }} – {{ $periodEnd }}):

  +{{ $newFollowers }} new followers
  +{{ $newSubscribers }} new subscribers
  ${{ number_format($unlockRevenueCents/100, 2) }} in unlock revenue
  {{ $newPosts->count() }} new posts

@foreach($newPosts as $p)
  - {{ $p->title ?: \Illuminate\Support\Str::limit($p->body, 60) }}
@endforeach

Open your Stats home: {{ $statsUrl }}
View your profile:  {{ $profileUrl }}
