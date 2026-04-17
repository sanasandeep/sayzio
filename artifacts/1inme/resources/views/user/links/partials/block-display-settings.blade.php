@php
    $vis = $block->settings['_visibility'] ?? [];
    $allContinents = ['Africa','Antarctica','Asia','Europe','North America','Oceania','South America'];
    $allDevices = ['desktop','tablet','mobile'];
    $allOs = ['Windows','OS X','Linux','iOS','Android','Chrome OS'];
    $allBrowsers = ['Chrome','Firefox','Safari','Edge','Opera','Brave','Vivaldi','Internet Explorer'];
    $allDays = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];
    $tabItems = collect(($link ?? null)?->settings['biolink']['menu_bar']['items'] ?? [])
        ->filter(fn($i) => is_array($i) && ($i['target'] ?? '') === 'tab' && !empty($i['id'] ?? '') && !empty($i['label'] ?? ''))
        ->values();
    $currentTabId = $block->settings['_tab_id'] ?? '';

    // Sanitize stored slots into a JSON-friendly shape for Alpine.
    $rawSlots = is_array($vis['time_slots'] ?? null) ? $vis['time_slots'] : [];
    $timeSlots = [];
    foreach ($rawSlots as $s) {
        if (!is_array($s)) continue;
        $timeSlots[] = [
            'days'  => array_values(array_filter((array)($s['days'] ?? []), fn($d) => isset($allDays[$d]))),
            'start' => preg_match('/^\d{2}:\d{2}$/', $s['start'] ?? '') ? $s['start'] : '09:00',
            'end'   => preg_match('/^\d{2}:\d{2}$/', $s['end']   ?? '') ? $s['end']   : '17:00',
        ];
    }
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showDisplay: false, openCard: 'schedule' }">
    <button type="button" @click="showDisplay = !showDisplay"
            class="w-full flex items-center justify-between text-sm font-medium py-1.5 group" style="color: var(--text-muted);">
        <span class="flex items-center gap-2">
            <span class="inline-flex w-7 h-7 rounded-lg items-center justify-center" style="background: linear-gradient(135deg, rgba(139,92,246,0.18), rgba(236,72,153,0.12)); border: 1px solid rgba(139,92,246,0.25);">
                <i class="fas fa-sliders-h text-violet-400 text-xs"></i>
            </span>
            <span>Display Settings</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(139,92,246,0.10); color: rgba(196,181,253,0.85);">Schedule · Audience · Device</span>
        </span>
        <i :class="showDisplay ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px] text-white/40 group-hover:text-white/70 transition"></i>
    </button>

    <div x-show="showDisplay" x-cloak x-transition class="mt-3 space-y-2.5">

        @if($tabItems->count() > 0)
        {{-- Tab placement (only when the page has tabs configured) --}}
        <div class="dz-card rounded-xl p-3" style="background: linear-gradient(180deg, rgba(255,255,255,0.025), rgba(255,255,255,0.01)); border: 1px solid var(--border-glass);">
            <label class="flex items-center gap-2 text-xs font-medium mb-2" style="color: var(--text-muted);">
                <i class="fas fa-folder-open text-violet-400 text-[11px]"></i> Show on Page Tab
            </label>
            <select name="settings[_tab_id]" class="{{ $inputClass }}">
                <option value="">Main Page (default — visible when no tab is active)</option>
                @foreach($tabItems as $ti)
                <option value="{{ $ti['id'] }}" {{ $currentTabId === $ti['id'] ? 'selected' : '' }}>{{ $ti['label'] }}</option>
                @endforeach
            </select>
            <p class="text-[10px] mt-1.5" style="color: var(--text-dimmed);">Pick which tab this block belongs to. Configure tabs under Settings → Advanced → Navigation Menu Bar.</p>
        </div>
        @else
        <input type="hidden" name="settings[_tab_id]" value="{{ $currentTabId }}">
        @endif

        {{-- =============== SCHEDULE CARD =============== --}}
        @php $tsId = 'ts_' . substr(md5($block->id . uniqid()), 0, 8); @endphp
        <div class="rounded-xl overflow-hidden" style="background: linear-gradient(180deg, rgba(139,92,246,0.06), rgba(255,255,255,0.01)); border: 1px solid rgba(139,92,246,0.18);"
             x-data="timeSlotsField_{{ $tsId }}()">
            <button type="button" @click="$root.openCard = $root.openCard === 'schedule' ? '' : 'schedule'"
                    class="w-full flex items-center justify-between px-3 py-2.5">
                <span class="flex items-center gap-2 text-xs font-medium" style="color: var(--text-secondary, #d4d4d8);">
                    <i class="fas fa-calendar-alt text-violet-400 text-[11px]"></i>
                    Schedule
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md ml-1" style="background: rgba(139,92,246,0.14); color: rgba(196,181,253,0.85);" x-text="summary"></span>
                </span>
                <i :class="$root.openCard === 'schedule' ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px] text-white/40"></i>
            </button>
            <div x-show="$root.openCard === 'schedule'" x-cloak x-transition class="px-3 pb-3 space-y-3">
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="{{ $labelClass }} flex items-center gap-1.5"><i class="far fa-play-circle text-emerald-400/70 text-[10px]"></i>Goes Live</label>
                        <input type="datetime-local" name="start_date" value="{{ $block->start_date?->format('Y-m-d\TH:i') }}" class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }} flex items-center gap-1.5"><i class="far fa-stop-circle text-rose-400/70 text-[10px]"></i>Expires</label>
                        <input type="datetime-local" name="end_date" value="{{ $block->end_date?->format('Y-m-d\TH:i') }}" class="{{ $inputClass }}">
                    </div>
                </div>
                <p class="text-[10px] -mt-1.5" style="color: var(--text-dimmed);">Leave blank for "always live". Both fields use your account timezone.</p>

                {{-- Active Time Slots --}}
                <div class="rounded-lg p-2.5" style="background: rgba(0,0,0,0.18); border: 1px dashed rgba(139,92,246,0.22);">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-secondary, #d4d4d8);">
                            <i class="far fa-clock text-violet-400 text-[11px]"></i>
                            Active Time Slots
                            <span class="text-[10px] font-normal" style="color: var(--text-dimmed);">(optional)</span>
                        </label>
                        <button type="button" @click="addSlot()"
                                class="text-[10px] px-2 py-1 rounded-md flex items-center gap-1 transition"
                                style="background: rgba(139,92,246,0.18); color: rgba(196,181,253,0.95); border: 1px solid rgba(139,92,246,0.30);">
                            <i class="fas fa-plus text-[9px]"></i> Add slot
                        </button>
                    </div>

                    <template x-if="slots.length === 0">
                        <p class="text-[10px] text-center py-2" style="color: var(--text-dimmed);">
                            No time slots — block is visible at all hours within the schedule window above.
                        </p>
                    </template>

                    <template x-for="(slot, i) in slots" :key="i">
                        <div class="rounded-lg p-2 mb-1.5" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);">
                            {{-- Day chips --}}
                            <div class="flex flex-wrap gap-1 mb-2">
                                @foreach($allDays as $code => $label)
                                <button type="button"
                                        @click="toggleDay(i, '{{ $code }}')"
                                        :class="slots[i].days.includes('{{ $code }}') ? 'ring-1 ring-violet-400/60 text-violet-200' : 'text-white/45 hover:text-white/70'"
                                        :style="slots[i].days.includes('{{ $code }}') ? 'background: rgba(139,92,246,0.22);' : 'background: rgba(255,255,255,0.04);'"
                                        class="text-[10px] font-semibold w-8 h-7 rounded-md transition-all">
                                    {{ $label }}
                                </button>
                                @endforeach
                                <div class="flex-1"></div>
                                <button type="button" @click="presetWeekdays(i)" class="text-[9px] px-2 h-7 rounded-md text-white/40 hover:text-violet-300" style="background: rgba(255,255,255,0.03);" title="Mon–Fri">Wkdays</button>
                                <button type="button" @click="presetWeekend(i)" class="text-[9px] px-2 h-7 rounded-md text-white/40 hover:text-violet-300" style="background: rgba(255,255,255,0.03);" title="Sat–Sun">Wkend</button>
                                <button type="button" @click="presetAllDays(i)" class="text-[9px] px-2 h-7 rounded-md text-white/40 hover:text-violet-300" style="background: rgba(255,255,255,0.03);">All</button>
                            </div>

                            {{-- Time range + remove --}}
                            <div class="flex items-center gap-1.5">
                                <input type="time" x-model="slots[i].start"
                                       :name="'visibility[time_slots][' + i + '][start]'"
                                       class="{{ $inputClass }} text-xs flex-1">
                                <span class="text-white/30 text-[10px]">to</span>
                                <input type="time" x-model="slots[i].end"
                                       :name="'visibility[time_slots][' + i + '][end]'"
                                       class="{{ $inputClass }} text-xs flex-1">
                                <button type="button" @click="removeSlot(i)"
                                        class="w-7 h-7 rounded-md flex items-center justify-center text-white/30 hover:text-rose-400 hover:bg-rose-500/10 transition flex-shrink-0">
                                    <i class="fas fa-times text-[11px]"></i>
                                </button>
                            </div>

                            {{-- Hidden inputs to ship the chosen days back as visibility[time_slots][i][days][] --}}
                            <template x-for="d in slots[i].days" :key="d">
                                <input type="hidden" :name="'visibility[time_slots][' + i + '][days][]'" :value="d">
                            </template>
                        </div>
                    </template>

                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">
                        <i class="fas fa-info-circle text-violet-400/50 mr-1"></i>
                        Block is visible only when the visitor's local time is inside any one of the slots. Across-midnight ranges (e.g. 22:00 → 02:00) are supported.
                    </p>
                </div>
            </div>
        </div>

        {{-- =============== AUDIENCE CARD =============== --}}
        @php $audCount = count($vis['continents'] ?? []) + count($vis['countries'] ?? []) + count($vis['cities'] ?? []) + count($vis['languages'] ?? []); @endphp
        <div class="rounded-xl overflow-hidden" style="background: linear-gradient(180deg, rgba(34,211,238,0.05), rgba(255,255,255,0.01)); border: 1px solid rgba(34,211,238,0.16);">
            <button type="button" @click="openCard = openCard === 'audience' ? '' : 'audience'"
                    class="w-full flex items-center justify-between px-3 py-2.5">
                <span class="flex items-center gap-2 text-xs font-medium" style="color: var(--text-secondary, #d4d4d8);">
                    <i class="fas fa-globe-americas text-cyan-400 text-[11px]"></i>
                    Audience &amp; Location
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md ml-1" style="background: rgba(34,211,238,0.14); color: rgba(165,243,252,0.85);">
                        {{ $audCount > 0 ? $audCount . ' rule' . ($audCount === 1 ? '' : 's') : 'Everyone' }}
                    </span>
                </span>
                <i :class="openCard === 'audience' ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px] text-white/40"></i>
            </button>
            <div x-show="openCard === 'audience'" x-cloak x-transition class="px-3 pb-3 space-y-3">

                <div>
                    <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1.5" style="color: var(--text-muted);">
                        <i class="fas fa-globe text-cyan-400/80 text-[10px]"></i> Continents
                        <span class="text-white/30">·</span>
                        <span class="text-white/40 text-[10px]">{{ count($vis['continents'] ?? []) ?: 'Showing all' }}</span>
                    </label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($allContinents as $c)
                        <label class="flex items-center gap-1.5 text-[11px] cursor-pointer px-2.5 py-1 rounded-md transition" style="color: var(--text-muted); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="checkbox" name="visibility[continents][]" value="{{ $c }}"
                                   {{ in_array($c, $vis['continents'] ?? []) ? 'checked' : '' }}
                                   class="rounded border-white/20 bg-white/5 text-cyan-500 focus:ring-cyan-500/30 w-3 h-3">
                            {{ $c }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-2.5">
                    <div>
                        <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1" style="color: var(--text-muted);">
                            <i class="fas fa-flag text-cyan-400/80 text-[10px]"></i> Countries
                        </label>
                        <input type="text" name="visibility_countries_text"
                               value="{{ implode(', ', $vis['countries'] ?? []) }}"
                               placeholder="US, IN, GB · ISO codes, comma-separated"
                               class="{{ $inputClass }} text-xs"
                               x-data x-init="$watch('$el.value', v => { $el.nextElementSibling.value = v; })">
                        <input type="hidden" name="visibility[countries]" value="{{ implode(',', $vis['countries'] ?? []) }}">
                    </div>
                    <div>
                        <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1" style="color: var(--text-muted);">
                            <i class="fas fa-city text-cyan-400/80 text-[10px]"></i> Cities
                        </label>
                        <input type="text" name="visibility_cities_text"
                               value="{{ implode(', ', $vis['cities'] ?? []) }}"
                               placeholder="Mumbai, London, New York"
                               class="{{ $inputClass }} text-xs"
                               x-data x-init="$watch('$el.value', v => { $el.nextElementSibling.value = v; })">
                        <input type="hidden" name="visibility[cities]" value="{{ implode(',', $vis['cities'] ?? []) }}">
                    </div>
                    <div>
                        <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1" style="color: var(--text-muted);">
                            <i class="fas fa-language text-cyan-400/80 text-[10px]"></i> Browser Languages
                        </label>
                        <input type="text" name="visibility_languages_text"
                               value="{{ implode(', ', $vis['languages'] ?? []) }}"
                               placeholder="en, hi, es, fr"
                               class="{{ $inputClass }} text-xs"
                               x-data x-init="$watch('$el.value', v => { $el.nextElementSibling.value = v; })">
                        <input type="hidden" name="visibility[languages]" value="{{ implode(',', $vis['languages'] ?? []) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- =============== DEVICE CARD =============== --}}
        @php $devCount = count($vis['devices'] ?? []) + count($vis['os'] ?? []) + count($vis['browsers'] ?? []); @endphp
        <div class="rounded-xl overflow-hidden" style="background: linear-gradient(180deg, rgba(244,114,182,0.05), rgba(255,255,255,0.01)); border: 1px solid rgba(244,114,182,0.16);">
            <button type="button" @click="openCard = openCard === 'device' ? '' : 'device'"
                    class="w-full flex items-center justify-between px-3 py-2.5">
                <span class="flex items-center gap-2 text-xs font-medium" style="color: var(--text-secondary, #d4d4d8);">
                    <i class="fas fa-desktop text-pink-400 text-[11px]"></i>
                    Device &amp; Browser
                    <span class="text-[10px] px-1.5 py-0.5 rounded-md ml-1" style="background: rgba(244,114,182,0.14); color: rgba(251,207,232,0.85);">
                        {{ $devCount > 0 ? $devCount . ' rule' . ($devCount === 1 ? '' : 's') : 'All devices' }}
                    </span>
                </span>
                <i :class="openCard === 'device' ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-[10px] text-white/40"></i>
            </button>
            <div x-show="openCard === 'device'" x-cloak x-transition class="px-3 pb-3 space-y-3">

                <div>
                    <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1.5" style="color: var(--text-muted);"><i class="fas fa-laptop text-pink-400/80 text-[10px]"></i> Devices</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($allDevices as $d)
                        <label class="flex items-center gap-1.5 text-[11px] cursor-pointer px-2.5 py-1 rounded-md transition" style="color: var(--text-muted); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="checkbox" name="visibility[devices][]" value="{{ $d }}"
                                   {{ in_array($d, $vis['devices'] ?? []) ? 'checked' : '' }}
                                   class="rounded border-white/20 bg-white/5 text-pink-500 focus:ring-pink-500/30 w-3 h-3">
                            <i class="fas fa-{{ $d === 'desktop' ? 'desktop' : ($d === 'tablet' ? 'tablet-alt' : 'mobile-alt') }} text-[10px]"></i>
                            {{ ucfirst($d) }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1.5" style="color: var(--text-muted);"><i class="fab fa-windows text-pink-400/80 text-[10px]"></i> Operating Systems</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($allOs as $o)
                        <label class="flex items-center gap-1.5 text-[11px] cursor-pointer px-2.5 py-1 rounded-md transition" style="color: var(--text-muted); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="checkbox" name="visibility[os][]" value="{{ $o }}"
                                   {{ in_array($o, $vis['os'] ?? []) ? 'checked' : '' }}
                                   class="rounded border-white/20 bg-white/5 text-pink-500 focus:ring-pink-500/30 w-3 h-3">
                            {{ $o }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-medium flex items-center gap-1.5 mb-1.5" style="color: var(--text-muted);"><i class="fab fa-chrome text-pink-400/80 text-[10px]"></i> Browsers</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($allBrowsers as $b)
                        <label class="flex items-center gap-1.5 text-[11px] cursor-pointer px-2.5 py-1 rounded-md transition" style="color: var(--text-muted); background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                            <input type="checkbox" name="visibility[browsers][]" value="{{ $b }}"
                                   {{ in_array($b, $vis['browsers'] ?? []) ? 'checked' : '' }}
                                   class="rounded border-white/20 bg-white/5 text-pink-500 focus:ring-pink-500/30 w-3 h-3">
                            {{ $b }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function timeSlotsField_{{ $tsId }}() {
    return {
        slots: @js($timeSlots),
        get summary() {
            const n = this.slots.length;
            return n === 0 ? 'Always' : (n + ' time slot' + (n === 1 ? '' : 's'));
        },
        addSlot() {
            this.slots.push({ days: ['mon','tue','wed','thu','fri'], start: '09:00', end: '17:00' });
        },
        removeSlot(i) { this.slots.splice(i, 1); },
        toggleDay(i, d) {
            const arr = this.slots[i].days;
            const idx = arr.indexOf(d);
            if (idx === -1) arr.push(d); else arr.splice(idx, 1);
        },
        presetWeekdays(i) { this.slots[i].days = ['mon','tue','wed','thu','fri']; },
        presetWeekend(i)  { this.slots[i].days = ['sat','sun']; },
        presetAllDays(i)  { this.slots[i].days = ['mon','tue','wed','thu','fri','sat','sun']; },
    };
}
</script>
