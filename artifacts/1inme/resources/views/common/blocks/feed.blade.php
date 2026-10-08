@php
    $youtubeFeed = $block->type === 'youtube_feed';
    $channelId = trim((string) ($s['channel_id'] ?? ''));
    $validChannel = preg_match('/^UC[\w-]{22}$/', $channelId) === 1;
    $feedUrl = $youtubeFeed
        ? ($validChannel ? 'https://www.youtube.com/feeds/videos.xml?channel_id='.urlencode($channelId) : '')
        : trim((string) ($s['url'] ?? ''));
    $count = max(1, min($youtubeFeed ? 10 : 20, (int) ($s['count'] ?? ($youtubeFeed ? 3 : 5))));
    // Unsaved admin fixtures must not make network calls for every preview.
    $entries = $block->exists && $feedUrl !== '' ? app(\App\Modules\User\Services\BiolinkFeedService::class)->entries($feedUrl, $count) : [];
    $fallbackUrl = $youtubeFeed ? ($validChannel ? 'https://www.youtube.com/channel/'.$channelId : '') : $feedUrl;
    $fallbackScheme = strtolower(parse_url($fallbackUrl, PHP_URL_SCHEME) ?? '');
@endphp
<div class="mb-4 glass-block rounded-xl p-4">
    <div class="flex items-center gap-2 mb-3"><i class="{{ $youtubeFeed ? 'fab fa-youtube' : 'fas fa-rss' }}" aria-hidden="true"></i><span class="text-sm font-medium">{{ $youtubeFeed ? 'Latest videos' : 'Latest posts' }}</span></div>
    @if($entries)
        <ul class="space-y-3">
            @foreach($entries as $entry)
                <li><a href="{{ $entry['url'] }}" target="_blank" rel="noopener noreferrer" class="block text-sm font-medium underline underline-offset-4" style="overflow-wrap:anywhere">{{ $entry['title'] }}</a></li>
            @endforeach
        </ul>
    @else
        <p class="text-xs opacity-70">{{ $block->exists ? 'No recent entries available.' : 'Entries appear on your published page.' }}</p>
    @endif
    @if(in_array($fallbackScheme, ['http', 'https'], true))
        <a href="{{ $fallbackUrl }}" target="_blank" rel="noopener noreferrer" class="inline-block text-xs underline mt-3">{{ $youtubeFeed ? 'Open channel' : 'Open feed' }}</a>
    @endif
</div>
