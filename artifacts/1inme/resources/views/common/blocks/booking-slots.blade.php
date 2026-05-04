@php
    $slots  = is_array($s['slots'] ?? null) ? $s['slots'] : (is_array($s['items'] ?? null) ? $s['items'] : []);
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'list');
    $accent = $s['accent_color'] ?? '#7c3aed';
    $title  = trim($s['title'] ?? 'Book a slot');
    $cta    = $s['cta_text'] ?? 'Book';

    $fmt = static function ($v) {
        try { return \Carbon\Carbon::parse($v); } catch (\Throwable $e) { return null; }
    };
@endphp

<div class="mb-4 glass-block rounded-xl p-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-3" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if(empty($slots))
        <p class="text-xs opacity-50 text-center py-4" style="color: {{ $fontColor }};">No slots available</p>
    @elseif($layout === 'grid')
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach($slots as $sl)
                @php $d = $fmt($sl['start'] ?? null); $taken = ! empty($sl['taken']); @endphp
                <a href="{{ ! $taken ? ($sl['url'] ?? '#') : '#' }}"
                   @if(! $taken) target="_blank" rel="noopener" @endif
                   class="block p-3 rounded-xl text-center text-xs transition {{ $taken ? 'opacity-40 cursor-not-allowed line-through' : 'hover:-translate-y-0.5' }}"
                   style="background: {{ $accent }}1a; border: 1px solid {{ $accent }}33; color: {{ $fontColor }};">
                    @if($d)
                        <div class="font-semibold">{{ $d->format('M d') }}</div>
                        <div class="text-[10px] opacity-70">{{ $d->format('g:i A') }}</div>
                    @else
                        <div>{{ $sl['label'] ?? 'Slot' }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach($slots as $sl)
                @php $d = $fmt($sl['start'] ?? null); $taken = ! empty($sl['taken']); @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl border {{ $taken ? 'opacity-40' : '' }}"
                     style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08; color: {{ $fontColor }};">
                    <div class="flex-1 min-w-0">
                        @if($d)
                            <div class="text-sm font-semibold">{{ $d->format('D, M d') }}</div>
                            <div class="text-[11px] opacity-70">{{ $d->format('g:i A') }}{{ !empty($sl['duration']) ? ' · ' . $sl['duration'] : '' }}</div>
                        @else
                            <div class="text-sm font-semibold">{{ $sl['label'] ?? 'Slot' }}</div>
                        @endif
                    </div>
                    @if($taken)
                        <span class="text-[10px] uppercase tracking-wider px-2 py-1 rounded-full bg-white/10">Taken</span>
                    @else
                        <a href="{{ $sl['url'] ?? '#' }}" target="_blank" rel="noopener"
                           class="px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition"
                           style="background: {{ $accent }}; color: #fff;">{{ $cta }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
