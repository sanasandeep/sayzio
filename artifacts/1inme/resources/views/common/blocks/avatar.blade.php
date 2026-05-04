    <div class="flex justify-center mb-4">
        @if(!empty($s['url']))
            <img src="{{ $s['url'] }}" alt="Avatar"
                 class="{{ ($s['rounded'] ?? true) ? 'rounded-full' : 'rounded-2xl' }} object-cover border-2 border-white/10"
                 style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
        @else
            <div class="rounded-full bg-white/10 backdrop-blur flex items-center justify-center border-2 border-white/10"
                 style="width: {{ $s['size'] ?? 96 }}px; height: {{ $s['size'] ?? 96 }}px;">
                <span class="text-3xl font-bold">{{ strtoupper(substr($link->title ?: 'B', 0, 1)) }}</span>
            </div>
        @endif
    </div>
