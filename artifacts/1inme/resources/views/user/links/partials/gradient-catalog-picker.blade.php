@php
    /**
     * Preset gradient catalog for the Colour tab's gradient builder.
     *
     * Lives INSIDE the bgSettings() Alpine scope (rendered from the page
     * background card), so it can mutate `gradientStops`, `gradientType` and
     * `gradientAngle` directly when the user picks a preset. Also writes the
     * chosen preset id into a hidden input so the server can re-render the
     * highlight on edit and surface the same preset elsewhere.
     *
     * Task #6233 -- this was the last picker still drawing its own geometry.
     * It declared `aspect-square` with a fixed `grid-cols-3/4/5`, which made
     * its swatches roughly twice the size of every other background swatch
     * on the same panel, in the wrong shape, in a grid that went ragged
     * whenever the aspect utility did not apply. It now uses the shared
     * .bg-swatch-grid / .bg-lib-swatch / .bg-lib-chip rules from the
     * background card, so it sizes and reads like everything else and cannot
     * drift again.
     *
     * These presets are STARTING POINTS for the builder above, not finished
     * backgrounds -- picking one loads its stops so they can be edited. The
     * heading says so, because the chip names (Neon, Abstract, Dark) also
     * exist as categories in the Style library and would otherwise look like
     * the same thing in two places.
     */
    use App\Modules\User\Support\GradientCatalog;
    $gradientPresets  = GradientCatalog::all();
    $gradientCats     = GradientCatalog::CATEGORIES;
    $selectedPresetId = $bs['gradient_preset_id'] ?? '';

    $gradientCatCounts = [];
    foreach ($gradientPresets as $p) {
        $gradientCatCounts[$p['category']] = ($gradientCatCounts[$p['category']] ?? 0) + 1;
    }
@endphp

<div class="rounded-xl p-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
     x-data="{ presetCat: 'all', presetSearch: '', presetId: @js($selectedPresetId) }">

    <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
        <label class="block text-xs font-medium" style="color: var(--text-muted);">
            Start from a preset <span class="opacity-60">{{ count($gradientPresets) }}</span>
        </label>
        <input type="text" x-model="presetSearch" placeholder="Search all {{ count($gradientPresets) }}…"
               class="text-[11px] px-2 py-1 rounded-md flex-1 max-w-[190px]"
               style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-primary);">
    </div>

    <div class="bg-lib-chips mb-2">
        <button type="button" @click="presetCat = 'all'"
                class="bg-lib-chip" :class="presetCat === 'all' ? 'is-on' : ''">
            All <span class="bg-lib-n">{{ count($gradientPresets) }}</span>
        </button>
        @foreach($gradientCats as $catKey => $catLabel)
            @if(($gradientCatCounts[$catKey] ?? 0) > 0)
            <button type="button" @click="presetCat = '{{ $catKey }}'"
                    class="bg-lib-chip" :class="presetCat === '{{ $catKey }}' ? 'is-on' : ''">
                {{ $catLabel }} <span class="bg-lib-n">{{ $gradientCatCounts[$catKey] }}</span>
            </button>
            @endif
        @endforeach
    </div>

    <input type="hidden" name="gradient_preset_id" :value="presetId">

    <div class="bg-swatch-grid max-h-[300px] overflow-y-auto pr-1">
        @foreach($gradientPresets as $p)
        @php $css = GradientCatalog::toCss($p); @endphp
        <button type="button"
                title="{{ $p['name'] }}"
                x-show="(presetCat === 'all' || presetCat === '{{ $p['category'] }}')
                        && (!presetSearch || {{ Illuminate\Support\Js::from(mb_strtolower($p['name'])) }}.includes(presetSearch.toLowerCase()))"
                @click="
                    gradientStops = @js($p['stops']);
                    gradientType  = {{ Illuminate\Support\Js::from($p['type']) }};
                    gradientAngle = {{ (int) $p['angle'] }};
                    presetId      = {{ Illuminate\Support\Js::from($p['id']) }};
                    $nextTick(() => $dispatch('change'));
                "
                :class="presetId === {{ Illuminate\Support\Js::from($p['id']) }} ? 'is-picked' : ''"
                class="bg-lib-swatch">
            <span class="bg-lib-fill" style="background: {{ $css }};"></span>
            <span class="bg-lib-tick" aria-hidden="true"><i class="fas fa-check"></i></span>
        </button>
        @endforeach
    </div>

    <p class="text-[10px] mt-1.5" style="color: var(--text-dimmed);">
        Picking one loads its colours into the stops above, where you can change them.
    </p>
</div>
