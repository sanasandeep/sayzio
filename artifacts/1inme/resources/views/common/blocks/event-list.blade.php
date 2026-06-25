@php
    $events = is_array($s['events'] ?? null) ? $s['events'] : (is_array($s['items'] ?? null) ? $s['items'] : []);
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'compact');
    $accent = $s['accent_color'] ?? '#3d6bff';
    $title  = trim($s['title'] ?? '');

    $fmtDate = static function ($v) {
        if (! $v) return null;
        try { return \Carbon\Carbon::parse($v); } catch (\Throwable $e) { return null; }
    };
@endphp

<div class="mb-4 glass-block rounded-xl p-4">
    @if($title !== '')
        <p class="text-sm font-semibold mb-3" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if(empty($events))
        <p class="text-xs opacity-50 text-center py-4" style="color: {{ $fontColor }};">No events yet</p>
    @elseif($layout === 'cards')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($events as $e)
                @php $d = $fmtDate($e['date'] ?? null); @endphp
                <a href="{{ $e['url'] ?? '#' }}" @if(!empty($e['url'])) target="_blank" rel="noopener" @endif
                   class="block p-3 rounded-xl border transition hover:-translate-y-0.5"
                   style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                    @if($d)
                        <div class="text-[10px] uppercase tracking-wider mb-1" style="color: {{ $accent }};">{{ $d->format('M d, Y · g:i A') }}</div>
                    @endif
                    <div class="text-sm font-semibold" style="color: {{ $fontColor }};">{{ $e['title'] ?? 'Event' }}</div>
                    @if(!empty($e['location']))<div class="text-xs opacity-60 mt-1" style="color: {{ $fontColor }};"><i class="fas fa-map-pin mr-1"></i>{{ $e['location'] }}</div>@endif
                </a>
            @endforeach
        </div>
    @elseif($layout === 'agenda')
        <div class="space-y-3">
            @foreach($events as $e)
                @php $d = $fmtDate($e['date'] ?? null); @endphp
                <div class="flex gap-3" style="color: {{ $fontColor }};">
                    <div class="w-14 flex-shrink-0 text-center rounded-lg py-2"
                         style="background: {{ $accent }}1a; color: {{ $accent }};">
                        @if($d)
                            <div class="text-[10px] font-semibold uppercase">{{ $d->format('M') }}</div>
                            <div class="text-xl font-bold leading-none">{{ $d->format('d') }}</div>
                        @else
                            <i class="fas fa-calendar text-lg"></i>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold">{{ $e['title'] ?? 'Event' }}</div>
                        @if($d)<div class="text-[11px] opacity-60">{{ $d->format('g:i A') }}</div>@endif
                        @if(!empty($e['location']))<div class="text-xs opacity-60 mt-0.5"><i class="fas fa-map-pin mr-1"></i>{{ $e['location'] }}</div>@endif
                        @if(!empty($e['description']))<div class="text-xs opacity-70 mt-1">{{ $e['description'] }}</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="space-y-2">
            @foreach($events as $e)
                @php $d = $fmtDate($e['date'] ?? null); @endphp
                <a href="{{ $e['url'] ?? '#' }}" @if(!empty($e['url'])) target="_blank" rel="noopener" @endif
                   class="flex items-center gap-3 px-2 py-2 rounded-lg transition" style="color: {{ $fontColor }};">
                    <div class="w-1 h-10 rounded-full flex-shrink-0" style="background: {{ $accent }};"></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">{{ $e['title'] ?? 'Event' }}</div>
                        @if($d)<div class="text-[11px] opacity-60">{{ $d->format('M d · g:i A') }}{{ !empty($e['location']) ? ' · ' . $e['location'] : '' }}</div>@endif
                    </div>
                    @if(!empty($e['url']))<i class="fas fa-arrow-right text-xs opacity-40"></i>@endif
                </a>
            @endforeach
        </div>
    @endif
</div>
