{{--
  Reusable searchable country dropdown: emoji flag + country name, storing
  the ISO-2 code in a hidden input. Modeled on common/partials/phone-input.

  Props (PHP vars set before @include):
    $csName        — HTML name for the hidden ISO-2 value (default: "country")
    $csValue       — Existing stored ISO-2 code (default: "")
    $csId          — Optional id prefix (default: "country-select")
    $csClass       — Extra classes on the outer wrapper (default: "")
    $csOptions     — code => label array (default: BillingCountries::options()
                     minus the 'OTHER' sentinel, since most consumers validate
                     size:2)
    $csPlaceholder — Trigger text when nothing is selected (default: "Select country")

  On pick the component dispatches a bubbling `country-picked` event whose
  detail is the ISO-2 code, so a parent Alpine scope can react, e.g.
  `@country-picked="onCountryInput($event.detail)"`.
--}}
@php
    use App\Support\BillingCountries;

    $csName        = $csName        ?? 'country';
    $csValue       = strtoupper(trim((string) ($csValue ?? '')));
    $csId          = $csId          ?? 'country-select';
    $csClass       = $csClass       ?? '';
    $csPlaceholder = $csPlaceholder ?? 'Select country';

    if (!isset($csOptions)) {
        $csOptions = BillingCountries::options();
        unset($csOptions['OTHER']);
    }

    // Emoji flag from a 2-letter ISO code (regional-indicator pair).
    $csFlagFor = function (string $code): string {
        $code = strtoupper($code);
        if (!preg_match('/^[A-Z]{2}$/', $code)) {
            return '🌐';
        }
        return mb_chr(0x1F1E6 + ord($code[0]) - ord('A'), 'UTF-8')
             . mb_chr(0x1F1E6 + ord($code[1]) - ord('A'), 'UTF-8');
    };

    $csList = [];
    foreach ($csOptions as $code => $label) {
        $code = (string) $code;
        $csList[] = [
            'code' => $code,
            'name' => (string) $label,
            'flag' => $code === '' ? '🌐' : $csFlagFor($code),
        ];
    }
    // A stored value outside the option list (legacy free-text entries) still
    // needs to display + submit correctly, so surface it as its own entry.
    if ($csValue !== '' && !array_key_exists($csValue, $csOptions)) {
        array_unshift($csList, ['code' => $csValue, 'name' => $csValue, 'flag' => $csFlagFor($csValue)]);
    }

    $_csUniqId = $csId . '-' . Str::random(6);
@endphp

<script>
(function () {
    var _id = {{ Js::from($_csUniqId) }};
    if (window['_csField_' + _id]) return;
    var countries = @js($csList);
    window['_csField_' + _id] = function () {
        var initial = {{ Js::from($csValue) }};
        function findCountry(code) {
            return countries.find(function (c) { return c.code === code; }) || null;
        }
        return {
            open:     false,
            search:   '',
            selected: findCountry(initial),
            get value() { return this.selected ? this.selected.code : ''; },
            get filtered() {
                var q = this.search.toLowerCase();
                if (!q) return countries;
                return countries.filter(function (c) {
                    return c.name.toLowerCase().includes(q) || c.code.toLowerCase().includes(q);
                });
            },
            pick: function (c) {
                this.selected = c;
                this.open = false;
                this.search = '';
                this.$dispatch('country-picked', c.code);
            },
            onClickOutside: function () { this.open = false; this.search = ''; },
        };
    };
})();
</script>

<div id="{{ $_csUniqId }}-wrap"
     x-data="window['_csField_{{ $_csUniqId }}']()"
     x-on:click.outside="onClickOutside()"
     class="relative {{ $csClass }}">

    {{-- Hidden ISO-2 value submitted with the form --}}
    <input type="hidden" name="{{ $csName }}" :value="value">

    {{-- Trigger button --}}
    <button type="button"
            @click="open = !open"
            class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-left hover:bg-white/5 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500/40"
            style="background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10); color: var(--text-primary);"
            aria-label="Select country"
            :aria-expanded="open">
        <template x-if="selected">
            <span class="flex items-center gap-2 flex-1 min-w-0">
                <span x-text="selected.flag" class="text-base leading-none"></span>
                <span x-text="selected.name" class="truncate text-sm"></span>
            </span>
        </template>
        <template x-if="!selected">
            <span class="flex-1 text-sm opacity-40">{{ $csPlaceholder }}</span>
        </template>
        <svg class="w-3 h-3 opacity-50 transition-transform shrink-0" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Dropdown --}}
    <div x-show="open"
         x-cloak
         class="cs-dropdown absolute z-50 left-0 top-full mt-1 w-full min-w-[16rem] rounded-xl shadow-2xl overflow-hidden"
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
                    :aria-selected="selected && c.code === selected.code"
                    @click="pick(c)"
                    class="flex items-center gap-2.5 px-3 py-2 text-sm cursor-pointer hover:bg-white/8 transition-colors"
                    :class="(selected && c.code === selected.code) ? 'bg-blue-500/15' : ''"
                    style="color: var(--text-primary);">
                    <span x-text="c.flag" class="text-base w-6 text-center"></span>
                    <span x-text="c.name" class="flex-1 truncate"></span>
                    <span x-text="c.code" class="text-xs opacity-50 tabular-nums shrink-0"></span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-4 py-3 text-sm opacity-40" style="color: var(--text-muted);">No countries found</li>
        </ul>
    </div>
</div>

{{-- Light-mode pairs for the trigger + dropdown overlay --}}
<style>
html.light-mode #{{ $_csUniqId }}-wrap > button { background: rgba(0,0,0,.04) !important; border-color: rgba(0,0,0,.12) !important; color: #1e1e2e !important; }
html.light-mode #{{ $_csUniqId }}-wrap input { color: #1e1e2e; }
html.light-mode #{{ $_csUniqId }}-wrap input::placeholder { color: rgba(0,0,0,.25); }
html.light-mode #{{ $_csUniqId }}-wrap .cs-dropdown { background: #fff !important; border-color: rgba(0,0,0,.12) !important; box-shadow: 0 8px 30px rgba(0,0,0,.15); }
html.light-mode #{{ $_csUniqId }}-wrap li { color: #1e1e2e !important; }
html.light-mode #{{ $_csUniqId }}-wrap li:hover { background: rgba(0,0,0,.05) !important; }
html.light-mode #{{ $_csUniqId }}-wrap li[aria-selected=true] { background: rgba(99,102,241,.10) !important; }
</style>
