@php
    /**
     * Preset gradient catalog grid for the appearance settings page.
     *
     * Lives INSIDE the bgSettings() Alpine scope (rendered from
     * appearance.blade.php), so it can mutate `gradientStops`, `gradientType`
     * and `gradientAngle` directly when the user picks a preset. Also writes
     * the chosen preset id into a hidden input so the server can re-render
     * the highlight on edit and surface the same preset elsewhere.
     */
    use App\Modules\User\Support\GradientCatalog;
    $gradientPresets = GradientCatalog::all();
    $gradientCats = GradientCatalog::CATEGORIES;
    $selectedPresetId = $bs['gradient_preset_id'] ?? '';
@endphp

<div class="rounded-xl p-3" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);"
     x-data="{ presetCat: 'featured', presetSearch: '', presetId: '{{ addslashes($selectedPresetId) }}' }">
    <div class="flex items-center justify-between mb-2">
        <p class="text-[11px] font-bold uppercase tracking-wider" style="color: var(--text-muted);">
            <i class="fas fa-th text-[10px] mr-1"></i>Preset Gradients ({{ count($gradientPresets) }})
        </p>
        <input type="text" x-model="presetSearch" placeholder="Search…"
               class="text-[11px] px-2 py-1 rounded"
               style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-primary); width: 130px;">
    </div>

    <div class="flex flex-wrap gap-1 mb-3">
        @foreach($gradientCats as $catKey => $catLabel)
        <button type="button" @click="presetCat = '{{ $catKey }}'"
                :class="presetCat === '{{ $catKey }}' ? 'bg-violet-600 text-white' : ''"
                class="text-[10px] font-semibold px-2 py-1 rounded"
                style="background: var(--bg-glass); color: var(--text-faint);">{{ $catLabel }}</button>
        @endforeach
    </div>

    <input type="hidden" name="gradient_preset_id" :value="presetId">

    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 max-h-[280px] overflow-y-auto pr-1">
        @foreach($gradientPresets as $p)
        @php $css = GradientCatalog::toCss($p); @endphp
        <button type="button"
                x-show="(presetCat === '{{ $p['category'] }}' || presetSearch !== '') && (presetSearch === '' || '{{ strtolower($p['name']) }}'.includes(presetSearch.toLowerCase()))"
                @click="
                    gradientStops = @js($p['stops']);
                    gradientType = '{{ $p['type'] }}';
                    gradientAngle = {{ $p['angle'] }};
                    presetId = '{{ $p['id'] }}';
                "
                :class="presetId === '{{ $p['id'] }}' ? 'ring-2 ring-violet-400' : ''"
                class="aspect-square rounded-lg relative overflow-hidden hover:scale-105 transition-transform"
                style="background: {{ $css }}; border: 1px solid var(--border-glass);"
                title="{{ $p['name'] }}">
            <span class="absolute inset-x-0 bottom-0 text-[8px] font-semibold py-0.5 px-1 truncate"
                  style="background: rgba(0,0,0,0.55); color: #fff;">{{ $p['name'] }}</span>
        </button>
        @endforeach
    </div>
    <p class="text-[10px] mt-2" style="color: var(--text-dimmed);">
        Pick a preset to load its colors, or fine-tune the stops above.
    </p>
</div>
