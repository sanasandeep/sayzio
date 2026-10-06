<div class="mt-2 text-xs" style="color:var(--text-muted)">
    <button type="button" @click="doLookup()" class="underline">Find city and state from PIN / ZIP</button>
    <p role="status" aria-live="polite" x-text="lookupMessage"></p>
    <select x-show="places.length > 1" @change="if ($event.target.value !== '') applyPlace(places[Number($event.target.value)])" aria-label="Choose postal locality" class="w-full mt-2 p-2 rounded border" style="background:var(--bg-card);color:var(--text-primary)">
        <option value="">Choose locality</option>
        <template x-for="(place, idx) in places" :key="idx"><option :value="idx" x-text="place.city + ', ' + (place.region || '')"></option></template>
    </select>
</div>
