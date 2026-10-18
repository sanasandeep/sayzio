{{-- The filter bar, shared by the real map and the How-it-works example.

     Sana asked twice why the example had no filters. It had none because
     only the real map was ever filterable -- the legend described filters
     in words instead, which is a poor way to teach a control. Both boards
     now share this, and share one set of filter state, so a filter set
     while reading the example is still set when you look at your own map.

     $stages, $filterPeople and $boardLabel are the only things that differ. --}}
<div class="fnet-filters" role="group" aria-label="Filter stations">
<button type="button" class="fnet-fchip" :class="{'is-on':fnetHideUnused}" :aria-pressed="fnetHideUnused" @click="fnetHideUnused=!fnetHideUnused;fnetRedraw()">Only what is used</button>
<span class="fnet-fsep" aria-hidden="true"></span>
@foreach($stages as $fnetFk => $fnetFs)
<button type="button" class="fnet-fchip is-line" style="--ln:var(--fnet-{{ $fnetFk }})" :class="{'is-on':fnetStageIsOn('{{ $fnetFk }}')}" :aria-pressed="fnetStageIsOn('{{ $fnetFk }}')" @click="fnetToggleStage('{{ $fnetFk }}')">{{ $fnetFs['eyebrow'] }}</button>
@endforeach
<span class="fnet-fsep" aria-hidden="true"></span>
<button type="button" class="fnet-fchip" :class="{'is-on':fnetAi==='ai'}" :aria-pressed="fnetAi==='ai'" @click="fnetAi=fnetAi==='ai'?'all':'ai';fnetRedraw()">Runs on AI</button>
<button type="button" class="fnet-fchip" :class="{'is-on':fnetAi==='plain'}" :aria-pressed="fnetAi==='plain'" @click="fnetAi=fnetAi==='plain'?'all':'plain';fnetRedraw()">Not AI</button>
@if($filterPeople)
<span class="fnet-fsep" aria-hidden="true"></span>
<label class="fnet-fperson"><span class="sr-only">Show only stations a person worked in</span>
<select x-model="fnetPerson" @change="fnetRedraw()"><option value="">Anyone</option>
@foreach($filterPeople as $fp)
<option value="{{ $fp['id'] }}">{{ $fp['name'] }}</option>
@endforeach
</select></label>
@endif
<button type="button" class="fnet-fchip is-clear" x-show="fnetFiltered()" x-cloak @click="fnetClear()">Clear</button>
<span class="fnet-fcount" x-show="fnetFiltered()" x-cloak x-text="fnetHidden() + ' of {{ count($fnet['stations']) }} stations hidden'"></span>
</div>
