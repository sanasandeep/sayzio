@php
    $tz = $event?->timezone ?: $calendar->effectiveTimezone();
    $startVal = old('start_at', $event?->start_at?->timezone($tz)->format('Y-m-d\TH:i'));
    $endVal   = old('end_at', $event?->end_at?->timezone($tz)->format('Y-m-d\TH:i'));
    $hashtagsVal = old('hashtags', $event ? collect($event->hashtags ?? [])->map(fn($t) => '#' . $t)->implode(' ') : '');
    $paramsVal = old('params_json', $event && is_array($event->params) ? json_encode($event->params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
@endphp

<div>
    <label class="{{ $labelClass }}">Title</label>
    <input type="text" name="title" value="{{ old('title', $event?->title) }}" class="{{ $inputClass }}" required>
    @error('title') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
</div>

<div>
    <label class="{{ $labelClass }}">Description <span class="text-white/30">(optional)</span></label>
    <textarea name="description" rows="2" class="{{ $inputClass }}">{{ old('description', $event?->description) }}</textarea>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="{{ $labelClass }}">Starts</label>
        <input type="datetime-local" name="start_at" value="{{ $startVal }}" class="{{ $inputClass }}" required>
        @error('start_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Ends <span class="text-white/30">(optional)</span></label>
        <input type="datetime-local" name="end_at" value="{{ $endVal }}" class="{{ $inputClass }}">
        @error('end_at') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="{{ $labelClass }}">Timezone</label>
        <select name="timezone" class="{{ $inputClass }}">
            @foreach($timezones as $z)
                <option value="{{ $z }}" @selected(old('timezone', $tz) === $z)>{{ $z }}</option>
            @endforeach
        </select>
    </div>
</div>

<label class="flex items-center gap-2 text-sm text-white/60">
    <input type="hidden" name="all_day" value="0">
    <input type="checkbox" name="all_day" value="1" {{ old('all_day', $event?->all_day) ? 'checked' : '' }} class="rounded text-blue-500">
    All-day event
</label>

{{-- Location + map pin picker (host form provides x-data="mapPinPicker(...)") --}}
<div>
    <label class="{{ $labelClass }}">Location <span class="text-white/30">(optional)</span></label>
    <input type="text" name="location" x-model="address" placeholder="123 Main St, City" class="{{ $inputClass }}">
</div>
<div>
    <div class="flex items-center justify-between mb-1">
        <span class="{{ $labelClass }}" style="margin-bottom:0;">Pin location</span>
        <button type="button" @click="toggleMap()" class="text-[11px] font-medium" style="color:#90acff;">
            <i class="fas fa-map-location-dot mr-1"></i> <span x-text="showMap ? 'Hide map' : 'Pick on map'"></span>
        </button>
    </div>
    <div x-show="showMap" x-cloak class="mb-1">
        <div class="flex gap-2 mb-2">
            <input x-model="searchQuery" @keydown.enter.prevent="searchAddress()" type="text" placeholder="Search a place or address…" class="{{ $inputClass }}">
            <button type="button" @click="searchAddress()" class="px-3 rounded-lg text-xs font-medium flex-shrink-0" style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.20)">
                <i class="fas fa-magnifying-glass"></i>
            </button>
        </div>
        <div x-ref="map" class="mpp-map" style="height:240px;border-radius:12px;overflow:hidden;border:1px solid var(--border-glass);background:#1e2330;"></div>
        <p class="text-[11px] mt-1.5 text-white/40"><i class="fas fa-circle-info mr-1"></i> Tap the map or drag the pin, we'll fill in the address and coordinates.</p>
    </div>
</div>
<div class="grid grid-cols-2 gap-2">
    <div><label class="{{ $labelClass }}">Latitude <span class="text-white/30">(optional)</span></label><input type="text" name="lat" x-model="lat" @input="syncMapFromInputs()" placeholder="37.7749" class="{{ $inputClass }}"></div>
    <div><label class="{{ $labelClass }}">Longitude <span class="text-white/30">(optional)</span></label><input type="text" name="lng" x-model="lng" @input="syncMapFromInputs()" placeholder="-122.4194" class="{{ $inputClass }}"></div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="{{ $labelClass }}">Hashtags <span class="text-white/30">(space-separated)</span></label>
        <input type="text" name="hashtags" value="{{ $hashtagsVal }}" placeholder="#music #live" class="{{ $inputClass }}">
    </div>
    <div>
        <label class="{{ $labelClass }}">Payment / ticket URL <span class="text-white/30">(optional)</span></label>
        <input type="url" name="payment_url" value="{{ old('payment_url', $event?->payment_url) }}" placeholder="https://…" class="{{ $inputClass }}">
        @error('payment_url') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<details class="text-sm">
    <summary class="cursor-pointer text-white/40 hover:text-white/60">Advanced &middot; custom params (JSON)</summary>
    <textarea name="params_json" rows="3" placeholder='{"key":"value"}' class="{{ $inputClass }} mt-2 font-mono text-xs">{{ $paramsVal }}</textarea>
    @error('params_json') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
</details>
