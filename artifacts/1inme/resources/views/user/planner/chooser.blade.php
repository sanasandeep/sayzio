@php
    $pickerChoices = array_map(fn($c)=>['value'=>$c['kind'].':'.$c['id'],'label'=>$c['label'],'kind'=>$c['kind']],$choices);
    $pickerTypeLabels=\App\Services\Planner\PlannerResources::typeLabels();
    $pickerKinds = collect($choices)->pluck('kind')->unique()->mapWithKeys(fn($kind)=>[$kind=>$pickerTypeLabels[$kind]??ucfirst($kind)])->all();
@endphp
<div class="folder-chooser" x-data="{
    selected:@js($selected??[]),query:'',typeFilters:[],choices:@js($pickerChoices),limit:{{ $limit??20 }},typeLabels:@js($pickerTypeLabels),
    get visible(){return this.choices.filter(c=>(!this.typeFilters.length||this.typeFilters.includes(c.kind))&&c.label.toLowerCase().includes(this.query.trim().toLowerCase()));},
    selectVisible(){this.selected=[...new Set([...this.selected,...this.visible.map(c=>c.value)])].slice(0,this.limit);}
}">
    <template x-for="value in selected" :key="value"><input type="hidden" name="{{ $fieldName }}" :value="value"></template>
    <details class="folder-picker-panel">
        <summary><span><i class="fas fa-layer-group" aria-hidden="true"></i> {{ $label??'Select items' }}</span><span class="folder-picker-summary"><span x-text="selected.length"></span> selected <i class="fas fa-chevron-down" aria-hidden="true"></i></span></summary>
        <div class="folder-picker-body">
            <div class="folder-resource-toolbar"><label class="folder-search"><i class="fas fa-search" aria-hidden="true"></i><input x-model="query" placeholder="Search available items…" aria-label="Search selectable items"></label>@include('user.planner.multi-filter',['model'=>'typeFilters','options'=>$pickerKinds,'filterLabel'=>'Types'])</div>
            <div class="folder-picker-actions"><span><strong x-text="selected.length"></strong> / {{ $limit??20 }} selected · <span x-text="visible.length"></span> matching</span><div><button type="button" @click="selectVisible()" :disabled="selected.length>=limit||!visible.length">Select visible</button><button type="button" @click="selected=[]" :disabled="!selected.length">Clear selection</button></div></div>
            <p class="notes-hint" x-show="selected.length>=limit" role="status">Selection limit reached. Remove an item to choose another.</p>
            <div class="folder-picker-list"><template x-for="choice in visible" :key="choice.value"><label class="folder-picker-option" :class="selected.includes(choice.value)?'is-selected':''"><input type="checkbox" x-model="selected" :value="choice.value" :disabled="selected.length>=limit&&!selected.includes(choice.value)"><span class="folder-resource-type" x-text="typeLabels[choice.kind]||choice.kind"></span><strong x-text="choice.label"></strong></label></template><p class="folder-filter-empty" x-show="!visible.length">No matching items. Try another type or clear the search.</p></div>
        </div>
    </details>
    <div class="folder-selected-chips" x-show="selected.length"><template x-for="value in selected" :key="value"><button type="button" class="form-chip text-xs" @click="selected=selected.filter(v=>v!==value)" :aria-label="'Remove '+(choices.find(c=>c.value===value)?.label||value)"><span x-text="choices.find(c=>c.value===value)?.label||value"></span><span aria-hidden="true">×</span></button></template></div>
</div>
