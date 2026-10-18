{{-- The map as a vertical tree, for phone width.

     Sana, 2026-10-18: "in mobile view, covert it to verticle tree type with
     expand and collaps". So this replaces the card grid below 760px rather
     than sitting alongside it -- the grid and the rails are hidden there,
     because a rail drawn between two stacked cards says something untrue.

     Built from the same $stations the grid is built from, so the two cannot
     disagree about what the folder holds. Stages collapse with <details>,
     which needs no JavaScript and keeps keyboard and screen-reader behaviour
     for free. A station opens the panel it already had -- the item list lives
     there, and duplicating it here would be a second thing to keep in step.

     Filters apply here too: a hidden station is hidden in both views. --}}
@php
    $treeByStage = [];
    foreach ($stations as $treeStation) {
        $treeByStage[$treeStation['stage']][] = $treeStation;
    }
@endphp
<div class="fnet-tree" aria-label="Stations by stage">
@foreach($stages as $treeKey => $treeStage)
@if(!empty($treeByStage[$treeKey]))
<details class="fnet-tnode" open style="--ln:var(--fnet-{{ $treeKey }})" x-show="fnetStageIsOn('{{ $treeKey }}')">
<summary class="fnet-tsum"><span class="fnet-tdot" aria-hidden="true"></span><b>{{ $treeStage['eyebrow'] }}</b><i>{{ count($treeByStage[$treeKey]) }}</i></summary>
@foreach($treeByStage[$treeKey] as $treeStn)
<button type="button" class="fnet-trow{{ $treeStn['in_use'] ? '' : ' is-off' }}{{ $treeStn['ai'] ? ' is-ai' : '' }}"
  x-show="fnetVisible('{{ $treeStn['kind'] }}')"
  @click="openPanel('{{ ($panelKey ?? 'flow').':'.$treeStn['kind'] }}',$event.currentTarget)"
  :aria-expanded="selected==='{{ ($panelKey ?? 'flow').':'.$treeStn['kind'] }}'" aria-controls="folder-map-panel">
<span class="fnet-tico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $treeStn['icon'] !!}</svg></span>
<span class="fnet-tname">{{ $labels[$treeStn['kind']] ?? $treeStn['kind'] }}@if($treeStn['ai'])<em>AI</em>@endif</span>
<span class="fnet-tct">{{ number_format($treeStn['count']) }}</span>
<span class="fnet-tfig">{{ $treeStn['figure'] ? (($treeStn['figure']['of'] !== null ? number_format($treeStn['figure']['value']).' of '.number_format($treeStn['figure']['of']) : number_format($treeStn['figure']['value'])).' '.$treeStn['figure']['suffix']) : 'not counted here' }}</span>
</button>
@endforeach
</details>
@endif
@endforeach
</div>
