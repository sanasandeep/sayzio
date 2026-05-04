    @php $vhSize = ($s['font_size'] ?? '24') . 'px'; @endphp
    <div class="mb-3 text-{{ $s['alignment'] ?? 'center' }}">
        <h2 class="font-bold inline-flex items-center gap-2" style="font-size: {{ $vhSize }};">
            {{ $s['text'] ?? '' }}
            <svg class="inline-block shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#1d9bf0"/><path d="M9.5 12.5l2 2 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </h2>
    </div>
