{{-- model names are supplied by our templates, never by request input. Empty = all. --}}
<div class="folder-multi-filter" x-data="{filterOpen:false}" x-id="['multi-filter']" @keydown.escape.stop="filterOpen=false;$refs.filterTrigger.focus()" @click.outside="filterOpen=false">
    <button type="button" class="folder-filter-trigger" x-ref="filterTrigger" :aria-controls="$id('multi-filter')" @click="filterOpen=!filterOpen" :aria-expanded="filterOpen">
        <i class="fas fa-sliders" aria-hidden="true"></i>
        <span>{{ $filterLabel ?? 'Types' }}</span>
        <span class="folder-filter-count" x-text="{{ $model }}.length ? {{ $model }}.length+' selected' : 'All'"></span>
        <i class="fas fa-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="folder-filter-menu" :id="$id('multi-filter')" x-show="filterOpen" x-cloak>
        <div class="folder-filter-menu-head"><strong>{{ $filterLabel ?? 'Resource types' }}</strong><button type="button" @click="filterOpen=false;$refs.filterTrigger.focus()" aria-label="Close filter"><i class="fas fa-xmark"></i></button></div>
        <div class="folder-filter-quick"><button type="button" @click="{{ $model }}=@js(array_keys($options))">Select all types</button><button type="button" @click="{{ $model }}=[]">Clear filters</button></div>
        <fieldset><legend class="sr-only">{{ $filterLabel ?? 'Resource types' }} · select multiple</legend>
            @foreach($options as $value => $label)
                <label class="folder-filter-option"><input type="checkbox" x-model="{{ $model }}" value="{{ $value }}"><span>{{ $label }}</span>@if(isset($counts))<small>{{ $counts[$value] ?? 0 }}</small>@endif</label>
            @endforeach
        </fieldset>
        <p>Select several to combine them. No selection shows all.</p>
    </div>
</div>
