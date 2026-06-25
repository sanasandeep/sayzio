    @php $nColors = ['info' => 'bg-blue-500/20 border-blue-400/30', 'success' => 'bg-green-500/20 border-green-400/30', 'warning' => 'bg-yellow-500/20 border-yellow-400/30']; @endphp
    <div class="mb-4 rounded-xl p-3 border {{ $nColors[$s['type'] ?? 'info'] ?? $nColors['info'] }} flex items-center gap-3" x-data="{ show: true }" x-show="show">
        <i class="fas fa-bell text-sm"></i><p class="text-sm flex-1">{{ $s['text'] ?? '' }}</p>
        @if($s['dismissible'] ?? true)<button @click="show = false" class="text-white/40 hover:text-white"><i class="fas fa-times text-xs"></i></button>@endif
    </div>
