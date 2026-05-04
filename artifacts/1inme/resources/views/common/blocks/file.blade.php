    <a href="{{ $s['url'] ?? '#' }}" target="_blank" class="mb-3 glass-block rounded-xl p-4 flex items-center gap-3 block hover:bg-white/[0.06] transition">
        <div class="w-11 h-11 rounded-xl bg-purple-500/20 flex items-center justify-center"><i class="{{ fa_icon_class($s['icon'] ?? 'fas fa-file-download', 'fas fa-file-download') }} text-purple-400"></i></div>
        <div class="flex-1 min-w-0"><p class="font-medium text-sm truncate">{{ $s['name'] ?? 'Download File' }}</p>@if(!empty($s['size']))<p class="text-xs text-white/40">{{ $s['size'] }}</p>@endif</div>
        <i class="fas fa-download text-white/30"></i>
    </a>
