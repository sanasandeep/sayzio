@php
    /** @var array $s */
    /** @var string $fontColor */
    $tracks = is_array($s['tracks'] ?? null) ? $s['tracks'] : (is_array($s['items'] ?? null) ? $s['items'] : []);
    $layout = $s['layout'] ?? ($s['_registry']['layout'] ?? 'compact');
    $accent = $s['accent_color'] ?? '#3d6bff';
    $title  = trim($s['title'] ?? '');
    $listId = 'audio_list_' . $block->id;
@endphp

<div class="mb-4 glass-block rounded-xl p-4" id="{{ $listId }}"
     x-data="{
         current: -1, playing: false, audio: null,
         play(idx, url) {
             if (this.audio) { this.audio.pause(); this.audio.currentTime = 0; }
             if (this.current === idx && this.playing) { this.playing = false; this.current = -1; return; }
             this.audio = new Audio(url);
             this.audio.play().catch(() => {});
             this.audio.addEventListener('ended', () => { this.playing = false; this.current = -1; });
             this.current = idx; this.playing = true;
         }
     }">
    @if($title !== '')
        <p class="text-sm font-semibold mb-3" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if(empty($tracks))
        <p class="text-xs opacity-50 text-center py-4" style="color: {{ $fontColor }};">No tracks yet</p>
    @elseif($layout === 'cards')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($tracks as $idx => $t)
                @php $url = $t['url'] ?? ''; @endphp
                <div class="flex items-center gap-3 p-3 rounded-xl border"
                     style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                    @if(!empty($t['cover']))
                        <img src="{{ $t['cover'] }}" alt="" class="w-14 h-14 rounded-lg object-cover flex-shrink-0">
                    @else
                        <div class="w-14 h-14 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background: {{ $accent }}22;">
                            <i class="fas fa-music text-xl" style="color: {{ $accent }};"></i>
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: {{ $fontColor }};">{{ $t['title'] ?? 'Track ' . ($idx + 1) }}</div>
                        @if(!empty($t['artist']))<div class="text-xs opacity-60 truncate" style="color: {{ $fontColor }};">{{ $t['artist'] }}</div>@endif
                    </div>
                    <button type="button" @click="play({{ $idx }}, @js($url))"
                            class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 transition"
                            style="background: {{ $accent }}; color: #fff;">
                        <i class="fas" :class="(current === {{ $idx }} && playing) ? 'fa-pause' : 'fa-play'"></i>
                    </button>
                </div>
            @endforeach
        </div>
    @elseif($layout === 'wave')
        <div class="space-y-2">
            @foreach($tracks as $idx => $t)
                @php $url = $t['url'] ?? ''; @endphp
                <div class="p-3 rounded-xl border" style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                    <div class="flex items-center gap-3 mb-2">
                        <button type="button" @click="play({{ $idx }}, @js($url))"
                                class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 transition"
                                style="background: {{ $accent }}; color: #fff;">
                            <i class="fas text-xs" :class="(current === {{ $idx }} && playing) ? 'fa-pause' : 'fa-play'"></i>
                        </button>
                        <div class="text-sm font-medium truncate" style="color: {{ $fontColor }};">{{ $t['title'] ?? 'Track ' . ($idx + 1) }}</div>
                    </div>
                    <div class="flex items-end gap-0.5 h-8 px-2">
                        @for($i = 0; $i < 32; $i++)
                            @php $h = 20 + (($i * 7 + $idx * 13) % 80); @endphp
                            <div class="flex-1 rounded-full transition-all"
                                 style="height: {{ $h }}%; background: {{ $accent }}; opacity: {{ ($i % 4 === 0) ? '0.9' : '0.4' }};"></div>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- compact --}}
        <div class="divide-y" style="--tw-divide-opacity: 1; border-color: {{ $fontColor }}10;">
            @foreach($tracks as $idx => $t)
                @php $url = $t['url'] ?? ''; @endphp
                <div class="flex items-center gap-3 py-2.5">
                    <button type="button" @click="play({{ $idx }}, @js($url))"
                            class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 transition"
                            style="background: {{ $accent }}22; color: {{ $accent }};">
                        <i class="fas text-xs" :class="(current === {{ $idx }} && playing) ? 'fa-pause' : 'fa-play'"></i>
                    </button>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate" style="color: {{ $fontColor }};">{{ $t['title'] ?? 'Track ' . ($idx + 1) }}</div>
                        @if(!empty($t['artist']))<div class="text-[11px] opacity-60 truncate" style="color: {{ $fontColor }};">{{ $t['artist'] }}</div>@endif
                    </div>
                    @if(!empty($t['duration']))
                        <span class="text-[10px] opacity-50 whitespace-nowrap" style="color: {{ $fontColor }};">{{ $t['duration'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
