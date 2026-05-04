    @php $spotifyEmbed = str_replace('open.spotify.com', 'open.spotify.com/embed', $s['url'] ?? ''); @endphp
    <div class="mb-4 rounded-xl overflow-hidden">
        <iframe src="{{ $spotifyEmbed }}" class="w-full rounded-xl" height="{{ ($s['type'] ?? 'track') === 'track' ? '152' : '352' }}"
                frameborder="0" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture" loading="lazy"></iframe>
    </div>
