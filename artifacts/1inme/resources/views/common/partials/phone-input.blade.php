{{--
  Reusable phone-input component: country-code dropdown + number entry.

  Props (PHP vars set before @include):
    $phoneInputName    — HTML name for the hidden combined value (default: "phone")
    $phoneInputValue   — Existing stored value, e.g. "+91 9876543210" (default: "")
    $phoneInputId      — Optional id prefix (default: "phone-input")
    $phoneInputClass   — Extra classes on the outer wrapper (default: "")
    $phoneInputSize    — "lg" (profile settings) or "sm" (compact). Default: "lg"
--}}
@php
    use App\Support\CountryDialCodes;
    $phoneInputName   = $phoneInputName  ?? 'phone';
    $phoneInputValue  = $phoneInputValue ?? '';
    $phoneInputId     = $phoneInputId    ?? 'phone-input';
    $phoneInputClass  = $phoneInputClass ?? '';
    $phoneInputSize   = $phoneInputSize  ?? 'lg';
    [$_piDial, $_piNum] = CountryDialCodes::parse($phoneInputValue);
    $_piCountries = CountryDialCodes::all();
    $_piUniqId    = $phoneInputId . '-' . Str::random(6);
    $isSm = $phoneInputSize === 'sm';
@endphp

<script>
(function () {
    var _id = {{ Js::from($_piUniqId) }};
    if (window['_piCodes_' + _id]) return;
    window['_piCodes_' + _id] = @js($_piCountries);
    window['_piField_' + _id] = function () {
        var initialDial = {{ Js::from($_piDial) }};
        var initialNum  = {{ Js::from($_piNum)  }};
        var codes       = window['_piCodes_' + _id];
        function findCountry(dial) {
            return codes.find(function (c) { return c.dial === dial; }) || codes[0];
        }
        return {
            open:     false,
            search:   '',
            selected: findCountry(initialDial),
            number:   initialNum,
            get combined() {
                var n = this.number.trim();
                if (!n) return '';
                return this.selected.dial + ' ' + n;
            },
            get filtered() {
                var q = this.search.toLowerCase();
                if (!q) return codes;
                return codes.filter(function (c) {
                    return c.name.toLowerCase().includes(q) || c.dial.includes(q) || c.code.toLowerCase().includes(q);
                });
            },
            pick: function (c) {
                this.selected = c;
                this.open = false;
                this.search = '';
                this.$nextTick(function () { document.getElementById(_id + '-num').focus(); });
            },
            onClickOutside: function () { this.open = false; this.search = ''; },
        };
    };
})();
</script>

<div id="{{ $_piUniqId }}-wrap"
     x-data="window['_piField_{{ $_piUniqId }}']()"
     x-on:click.outside="onClickOutside()"
     class="relative flex {{ $isSm ? 'rounded-lg' : 'rounded-xl' }} {{ $phoneInputClass }}"
     style="background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10);">

    {{-- Country trigger button --}}
    <button type="button"
            @click="open = !open"
            class="flex items-center gap-1.5 shrink-0 rounded-l-[inherit] {{ $isSm ? 'px-2 py-2 text-xs' : 'px-3 py-2.5 text-sm' }} border-r border-white/10 hover:bg-white/5 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/40"
            style="color: var(--text-primary);"
            aria-label="Select country code"
            :aria-expanded="open">
        <span x-text="selected.flag" class="text-base leading-none"></span>
        <span x-text="selected.dial" class="font-medium tabular-nums"></span>
        <svg class="w-3 h-3 opacity-50 transition-transform" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Number input --}}
    <input type="tel"
           id="{{ $_piUniqId }}-num"
           x-model="number"
           autocomplete="tel-national"
           placeholder="{{ $isSm ? '555 0100' : 'e.g. 98765 43210' }}"
           class="flex-1 min-w-0 bg-transparent rounded-r-[inherit] {{ $isSm ? 'px-2 py-2 text-sm' : 'px-3 py-2.5' }} outline-none placeholder-white/20 focus:ring-2 focus:ring-inset focus:ring-blue-500/40"
           style="color: var(--text-primary);">

    {{-- Hidden combined value submitted with the form --}}
    <input type="hidden" name="{{ $phoneInputName }}" :value="combined">

    {{-- Dropdown --}}
    <div x-show="open"
         x-cloak
         class="absolute z-50 left-0 top-full mt-1 w-72 rounded-xl shadow-2xl overflow-hidden"
         style="background: var(--bg-card, #1e1e2e); border: 1px solid rgba(255,255,255,.12);">

        {{-- Search --}}
        <div class="p-2 border-b border-white/10">
            <input type="text"
                   x-model="search"
                   x-ref="searchInput"
                   x-init="$watch('open', v => v && $nextTick(() => $refs.searchInput.focus()))"
                   placeholder="Search country…"
                   class="w-full px-3 py-1.5 text-sm rounded-lg bg-white/5 border border-white/10 outline-none placeholder-white/30 focus:border-blue-500/50"
                   style="color: var(--text-primary);">
        </div>

        {{-- List --}}
        <ul class="overflow-y-auto max-h-56 py-1" role="listbox">
            <template x-for="c in filtered" :key="c.code">
                <li role="option"
                    :aria-selected="c.code === selected.code"
                    @click="pick(c)"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm cursor-pointer hover:bg-white/8 transition-colors"
                    :class="c.code === selected.code ? 'bg-blue-500/15' : ''"
                    style="color: var(--text-primary);">
                    <span x-text="c.flag" class="text-base w-6 text-center"></span>
                    <span x-text="c.name" class="flex-1 truncate"></span>
                    <span x-text="c.dial" class="text-xs opacity-50 tabular-nums shrink-0"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm opacity-40" style="color: var(--text-muted);">No countries found</li>
        </ul>
    </div>
</div>

{{-- Light-mode pairs for the dropdown overlay --}}
<style>
html.light-mode #{{ $_piUniqId }}-wrap { background: rgba(0,0,0,.04) !important; border-color: rgba(0,0,0,.12) !important; }
html.light-mode #{{ $_piUniqId }}-wrap button { color: #1e1e2e; }
html.light-mode #{{ $_piUniqId }}-wrap input { color: #1e1e2e; }
html.light-mode #{{ $_piUniqId }}-wrap input::placeholder { color: rgba(0,0,0,.25); }
html.light-mode #{{ $_piUniqId }}-wrap .shadow-2xl { background: #fff !important; border-color: rgba(0,0,0,.12) !important; box-shadow: 0 8px 30px rgba(0,0,0,.15); }
html.light-mode #{{ $_piUniqId }}-wrap li { color: #1e1e2e !important; }
html.light-mode #{{ $_piUniqId }}-wrap li:hover { background: rgba(0,0,0,.05) !important; }
html.light-mode #{{ $_piUniqId }}-wrap li[aria-selected=true] { background: rgba(99,102,241,.10) !important; }
</style>
