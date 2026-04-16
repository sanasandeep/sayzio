@php
    $vis = $block->settings['_visibility'] ?? [];
    $allContinents = ['Africa','Antarctica','Asia','Europe','North America','Oceania','South America'];
    $allDevices = ['desktop','tablet','mobile'];
    $allOs = ['Windows','macOS','Linux','iOS','Android','ChromeOS','Other'];
    $allBrowsers = ['Chrome','Firefox','Safari','Edge','Opera','Samsung Internet','Other'];
@endphp

<div class="mt-4 pt-4" style="border-top: 1px solid var(--border-subtle);" x-data="{ showDisplay: false }">
    <button type="button" @click="showDisplay = !showDisplay"
            class="w-full flex items-center justify-between text-sm font-medium py-1" style="color: var(--text-muted);">
        <span><i class="fas fa-sliders-h mr-2 text-violet-400"></i>Display Settings</span>
        <i :class="showDisplay ? 'fa-chevron-up' : 'fa-chevron-down'" class="fas text-xs"></i>
    </button>

    <div x-show="showDisplay" x-cloak x-transition class="mt-3 space-y-4">

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="{{ $labelClass }}">Schedule Start</label>
                <input type="datetime-local" name="start_date" value="{{ $block->start_date?->format('Y-m-d\TH:i') }}" class="{{ $inputClass }}">
            </div>
            <div>
                <label class="{{ $labelClass }}">Schedule End</label>
                <input type="datetime-local" name="end_date" value="{{ $block->end_date?->format('Y-m-d\TH:i') }}" class="{{ $inputClass }}">
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fas fa-globe text-violet-400"></i> Continents
                <span class="text-violet-400/60">({{ count($vis['continents'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-1.5">
                @foreach($allContinents as $c)
                <label class="flex items-center gap-2 text-xs cursor-pointer px-2 py-1.5 rounded-lg" style="color: var(--text-muted); background: var(--bg-glass-input);">
                    <input type="checkbox" name="visibility[continents][]" value="{{ $c }}"
                           {{ in_array($c, $vis['continents'] ?? []) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/30">
                    {{ $c }}
                </label>
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fas fa-flag text-violet-400"></i> Countries
                <span class="text-violet-400/60">({{ count($vis['countries'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2">
                <input type="text" name="visibility_countries_text"
                       value="{{ implode(', ', $vis['countries'] ?? []) }}"
                       placeholder="e.g. US, IN, GB (ISO codes, comma-separated)"
                       class="{{ $inputClass }} text-xs"
                       x-data
                       x-init="$watch('$el.value', v => { let h = $el.closest('div').querySelector('input[type=hidden]'); h.value = v; })"
                >
                <input type="hidden" name="visibility[countries]" value="{{ implode(',', $vis['countries'] ?? []) }}">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Leave empty = show to all countries</p>
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fas fa-city text-violet-400"></i> Cities
                <span class="text-violet-400/60">({{ count($vis['cities'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2">
                <input type="text" name="visibility_cities_text"
                       value="{{ implode(', ', $vis['cities'] ?? []) }}"
                       placeholder="e.g. Mumbai, London, New York"
                       class="{{ $inputClass }} text-xs"
                       x-data
                       x-init="$watch('$el.value', v => { let h = $el.closest('div').querySelector('input[type=hidden]'); h.value = v; })"
                >
                <input type="hidden" name="visibility[cities]" value="{{ implode(',', $vis['cities'] ?? []) }}">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Leave empty = show in all cities</p>
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fas fa-desktop text-violet-400"></i> Devices
                <span class="text-violet-400/60">({{ count($vis['devices'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2 flex flex-wrap gap-2">
                @foreach($allDevices as $d)
                <label class="flex items-center gap-2 text-xs cursor-pointer px-3 py-1.5 rounded-lg" style="color: var(--text-muted); background: var(--bg-glass-input);">
                    <input type="checkbox" name="visibility[devices][]" value="{{ $d }}"
                           {{ in_array($d, $vis['devices'] ?? []) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/30">
                    <i class="fas fa-{{ $d === 'desktop' ? 'desktop' : ($d === 'tablet' ? 'tablet-alt' : 'mobile-alt') }} text-xs"></i>
                    {{ ucfirst($d) }}
                </label>
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fab fa-windows text-violet-400"></i> Operating Systems
                <span class="text-violet-400/60">({{ count($vis['os'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-1.5">
                @foreach($allOs as $o)
                <label class="flex items-center gap-2 text-xs cursor-pointer px-2 py-1.5 rounded-lg" style="color: var(--text-muted); background: var(--bg-glass-input);">
                    <input type="checkbox" name="visibility[os][]" value="{{ $o }}"
                           {{ in_array($o, $vis['os'] ?? []) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/30">
                    {{ $o }}
                </label>
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fab fa-chrome text-violet-400"></i> Browsers
                <span class="text-violet-400/60">({{ count($vis['browsers'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2 grid grid-cols-2 gap-1.5">
                @foreach($allBrowsers as $b)
                <label class="flex items-center gap-2 text-xs cursor-pointer px-2 py-1.5 rounded-lg" style="color: var(--text-muted); background: var(--bg-glass-input);">
                    <input type="checkbox" name="visibility[browsers][]" value="{{ $b }}"
                           {{ in_array($b, $vis['browsers'] ?? []) ? 'checked' : '' }}
                           class="rounded border-white/20 bg-white/5 text-violet-500 focus:ring-violet-500/30">
                    {{ $b }}
                </label>
                @endforeach
            </div>
        </div>

        <div x-data="{ open: false }">
            <button type="button" @click="open = !open" class="text-xs font-medium flex items-center gap-1.5" style="color: var(--text-muted);">
                <i class="fas fa-language text-violet-400"></i> Browser Languages
                <span class="text-violet-400/60">({{ count($vis['languages'] ?? []) ?: 'All' }})</span>
            </button>
            <div x-show="open" x-cloak class="mt-2">
                <input type="text" name="visibility_languages_text"
                       value="{{ implode(', ', $vis['languages'] ?? []) }}"
                       placeholder="e.g. en, hi, es, fr"
                       class="{{ $inputClass }} text-xs"
                       x-data
                       x-init="$watch('$el.value', v => { let h = $el.closest('div').querySelector('input[type=hidden]'); h.value = v; })"
                >
                <input type="hidden" name="visibility[languages]" value="{{ implode(',', $vis['languages'] ?? []) }}">
                <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">ISO language codes, comma-separated. Leave empty = show for all</p>
            </div>
        </div>

    </div>
</div>
