    <div class="mb-4 glass-block rounded-xl p-4 space-y-3">
        @foreach(($s['items'] ?? []) as $item)
        <div>
            <div class="flex justify-between text-xs mb-1"><span>{{ $item['label'] ?? '' }}</span><span>{{ $item['value'] ?? 0 }}%</span></div>
            <div class="w-full h-2 rounded-full bg-white/10"><div class="h-full rounded-full transition-all" style="width: {{ $item['value'] ?? 0 }}%; background: {{ $item['color'] ?? '#7c3aed' }};"></div></div>
        </div>
        @endforeach
    </div>
