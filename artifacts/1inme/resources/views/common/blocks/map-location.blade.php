    @php
        $addr = trim($s['address'] ?? '');
        $lat = is_numeric($s['lat'] ?? null) ? (float)$s['lat'] : null;
        $lng = is_numeric($s['lng'] ?? null) ? (float)$s['lng'] : null;
        $zoom = max(1, min(19, (int)($s['zoom'] ?? 15)));
        // lat/lng (when both provided) take precedence — they pin the marker
        // exactly. Otherwise we let the static-map service geocode the
        // address. No Google API key is required either way.
        if ($lat !== null && $lng !== null) {
            $center  = sprintf('%.6f,%.6f', $lat, $lng);
            $staticMap = "https://staticmap.openstreetmap.de/staticmap.php?center={$center}&zoom={$zoom}&size=600x260&markers={$center},red-pushpin";
            $mapsUrl   = "https://www.google.com/maps/search/?api=1&query=" . urlencode($center);
            $dirUrl    = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($center);
            $hasLoc = true;
        } elseif ($addr !== '') {
            $staticMap = 'https://staticmap.openstreetmap.de/staticmap.php?center=' . urlencode($addr) . "&zoom={$zoom}&size=600x260&markers=" . urlencode($addr) . ',red-pushpin';
            $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($addr);
            $dirUrl  = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($addr);
            $hasLoc = true;
        } else {
            $staticMap = $mapsUrl = $dirUrl = '';
            $hasLoc = false;
        }
    @endphp
    <div class="mb-4 glass-block rounded-xl overflow-hidden">
        @if($hasLoc)
            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="block">
                <img src="{{ $staticMap }}"
                     alt="Map of {{ $s['label'] ?: ($addr ?: 'location') }}"
                     class="w-full"
                     style="height: 220px; object-fit: cover; background: #1f2937;"
                     loading="lazy"
                     onerror="this.style.display='none'">
            </a>
            <div class="p-3 flex items-center justify-between gap-2">
                <div class="text-sm font-medium truncate">
                    <i class="fas fa-map-pin mr-1 text-rose-400"></i>{{ $s['label'] ?: $addr }}
                </div>
                <div class="flex gap-2">
                    <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                       class="text-xs px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/20 whitespace-nowrap">
                        <i class="fas fa-map mr-1"></i>Open in Maps
                    </a>
                    @if(!empty($s['show_directions']))
                        <a href="{{ $dirUrl }}" target="_blank" rel="noopener"
                           class="text-xs px-3 py-1.5 rounded-full bg-white/10 hover:bg-white/20 whitespace-nowrap">
                            <i class="fas fa-directions mr-1"></i>Directions
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="p-4 text-center text-xs text-white/40">Add an address to show a map</div>
        @endif
    </div>
