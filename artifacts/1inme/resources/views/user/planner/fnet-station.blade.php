{{-- One station, rendered by both the real map and the How-it-works example,
     so the example cannot drift into looking like a different product.

     $panelKey decides which set of panel sections a click opens -- 'flow' for
     the real folder, 'guide' for the example -- and only the real map is
     filterable, because the example is there to show all nineteen. --}}
@php $fnetTag = $interactive ? 'button' : 'div'; $fnetKey = ($panelKey ?? 'flow').':'.$station['kind']; $fnetFiltered = ($panelKey ?? 'flow') === 'flow'; @endphp
<{{ $fnetTag }}
@if($interactive)
type="button" @if($fnetFiltered)x-show="fnetVisible('{{ $station['kind'] }}')"@endif @click="openPanel('{{ $fnetKey }}',$event.currentTarget)" :aria-expanded="selected==='{{ $fnetKey }}'" aria-controls="folder-map-panel"
@endif
class="fnet-stn{{ $station['in_use'] ? '' : ' is-off' }}{{ $station['ai'] ? ' is-ai' : '' }}{{ $interactive ? '' : ' is-static' }}" data-fnet-kind="{{ $station['kind'] }}" style="--c:{{ $station['col'] }};--r:{{ $station['row'] }};--ln:var(--fnet-{{ $station['stage'] }})">
<span class="fnet-top"><span class="fnet-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $station['icon'] !!}</svg></span><span class="fnet-nm">{{ $labels[$station['kind']] ?? $station['kind'] }}</span></span>
<span class="fnet-ct">{{ number_format($station['count']) }}</span>
@if($station['figure'])
<span class="fnet-fg"><b>{{ $station['figure']['of'] !== null ? number_format($station['figure']['value']).' of '.number_format($station['figure']['of']) : number_format($station['figure']['value']) }}</b> {{ $station['figure']['suffix'] }}</span>
@else
<span class="fnet-no">not counted here</span>
@endif
<span class="fnet-stage-chip">{{ $stages[$station['stage']]['eyebrow'] ?? '' }}</span>
@if($activity['counted'])
<span class="fnet-who">
@foreach(array_slice($station['people'], 0, $faces) as $fnetWho)
@if(!empty($fnetWho['avatar_url']))
<img class="fnet-av" src="{{ $fnetWho['avatar_url'] }}" alt="{{ $fnetWho['name'] }}" title="{{ $fnetWho['name'] }} — {{ number_format($fnetWho['changes']) }} change{{ $fnetWho['changes'] === 1 ? '' : 's' }}" loading="lazy">
@else
<i class="fnet-av fnet-initial" title="{{ $fnetWho['name'] }} — {{ number_format($fnetWho['changes']) }} change{{ $fnetWho['changes'] === 1 ? '' : 's' }}">{{ mb_substr($fnetWho['name'], 0, 1) }}</i>
@endif
@endforeach
@if(count($station['people']) > $faces)
<i class="fnet-av fnet-more">+{{ count($station['people']) - $faces }}</i>
@endif
</span>
@endif
@if($station['in_use'])
<span class="fnet-live" aria-hidden="true"></span>
@endif
</{{ $fnetTag }}>
