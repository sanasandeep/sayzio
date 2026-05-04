    @php
        $videoId = $s['video_id'] ?? '';
        if (str_contains($videoId, 'youtube.com') || str_contains($videoId, 'youtu.be')) {
            preg_match('/(?:v=|\/)([\w-]{11})/', $videoId, $m);
            $videoId = $m[1] ?? $videoId;
        }
    @endphp
    <div class="mb-4 rounded-xl overflow-hidden aspect-video">
        <iframe src="https://www.youtube.com/embed/{{ $videoId }}" class="w-full h-full rounded-xl"
                frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
    </div>
