{{-- One station, rendered by both the real map and the How-it-works example,
     so the example cannot drift into looking like a different product.

     $panelKey decides which set of panel sections a click opens -- 'flow' for
     the real folder, 'guide' for the example -- and only the real map is
     filterable, because the example is there to show all nineteen.

     Sana, 2026-10-18, on the deployed map: "texts getting overflow", then
     "can u go with more like stats cards with svg icons". So the card is now
     a stat card. The name is a small uppercase label with the whole card
     width to itself, and that is what fixes the overflow -- it used to share
     a row with the icon and had about ninety pixels for names like "Budget &
     Forecast". The count is the dominant figure; the measured figure and the
     faces sit in a footer strip.

     The stage is the card's fill rather than a chip, so nothing on the card
     repeats it. The key under the map names the colours, and the title
     attribute carries the stage for anyone who cannot use colour alone. --}}
@php
    $fnetTag = $interactive ? 'button' : 'div';
    $fnetKey = ($panelKey ?? 'flow').':'.$station['kind'];
    // Both boards filter now -- the example teaches the control by having it.
    $fnetFiltered = true;
    $fnetName = $labels[$station['kind']] ?? $station['kind'];
    $fnetStage = $stages[$station['stage']]['eyebrow'] ?? '';
@endphp
<{{ $fnetTag }}
@if($interactive)
type="button" @if($fnetFiltered)x-show="fnetVisible('{{ $station['kind'] }}')"@endif @click="openPanel('{{ $fnetKey }}',$event.currentTarget)" :aria-expanded="selected==='{{ $fnetKey }}'" aria-controls="folder-map-panel"
@endif
title="{{ $fnetName }}{{ $fnetStage ? ' · '.$fnetStage : '' }}{{ $station['in_use'] ? '' : ' · nothing here yet' }}"
class="fnet-stn{{ $station['in_use'] ? '' : ' is-off' }}{{ $station['ai'] ? ' is-ai' : '' }}{{ $interactive ? '' : ' is-static' }}" data-fnet-kind="{{ $station['kind'] }}" style="--c:{{ $station['col'] }};--r:{{ $station['row'] }};--ln:var(--fnet-{{ $station['stage'] }})">
<span class="fnet-glow" aria-hidden="true"></span>
<span class="fnet-top"><span class="fnet-nm">{{ $fnetName }}</span><span class="fnet-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $station['icon'] !!}</svg></span></span>
<span class="fnet-ct">{{ number_format($station['count']) }}</span>
<span class="fnet-foot">
@if($station['figure'])
<span class="fnet-fg"><b>{{ $station['figure']['of'] !== null ? number_format($station['figure']['value']).' of '.number_format($station['figure']['of']) : number_format($station['figure']['value']) }}</b> {{ $station['figure']['suffix'] }}</span>
@else
<span class="fnet-no">not counted here</span>
@endif
@if($activity['counted'])
<span class="fnet-who">
@foreach(array_slice($station['people'], 0, $faces) as $fnetWho)
@if(!empty($fnetWho['avatar_url']))
<img class="fnet-av" src="{{ $fnetWho['avatar_url'] }}" alt="{{ $fnetWho['name'] }}" title="{{ $fnetWho['name'] }} · {{ number_format($fnetWho['changes']) }} change{{ $fnetWho['changes'] === 1 ? '' : 's' }}" loading="lazy">
@else
<i class="fnet-av fnet-initial" title="{{ $fnetWho['name'] }} · {{ number_format($fnetWho['changes']) }} change{{ $fnetWho['changes'] === 1 ? '' : 's' }}">{{ mb_substr($fnetWho['name'], 0, 1) }}</i>
@endif
@endforeach
@if(count($station['people']) > $faces)
<i class="fnet-av fnet-more">+{{ count($station['people']) - $faces }}</i>
@endif
</span>
@endif
</span>
@if($station['in_use'])
<span class="fnet-live" aria-hidden="true"></span>
@endif
</{{ $fnetTag }}>
