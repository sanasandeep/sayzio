    @php $alertColors = ['info' => 'border-blue-400/30 bg-blue-500/10', 'success' => 'border-green-400/30 bg-green-500/10', 'warning' => 'border-yellow-400/30 bg-yellow-500/10', 'error' => 'border-red-400/30 bg-red-500/10']; @endphp
    <div class="mb-4 rounded-xl p-4 border {{ $alertColors[$s['type'] ?? 'info'] ?? $alertColors['info'] }}">
        @php $_alertIcon = fa_icon_class($s['icon'] ?? 'fa-info-circle', 'fas fa-info-circle'); @endphp
        <p class="text-sm flex items-center gap-2"><i class="{{ $_alertIcon }}"></i>{{ $s['text'] ?? '' }}</p>
    </div>
