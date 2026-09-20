@php
    /**
     * Quick starts for the Colour tab's gradient builder.
     *
     * This used to be the whole preset catalog -- 166 gradients behind a
     * chip row of eleven mood categories, inside a card, under the builder.
     * That is what "these cats and design seems repeate" was pointing at:
     * Colour and Style each opened on a chip row over a swatch grid, so they
     * read as one feature shown twice, and two of the chip names (Neon,
     * Abstract) appeared in BOTH rows over different sets of gradients.
     *
     * There is one place to browse now, and it is the Style library, where
     * all 166 live as ordinary Gradients entries alongside every other
     * ready-made look. Picking one there loads it into this builder, so
     * nothing is lost by moving them -- it gained the edit step the separate
     * surfaces never had.
     *
     * What stays here is what a BUILDER needs: a short row of starting
     * points for someone who just wants to begin. One row, no chips, no
     * grid -- a different shape from the library, because it does a
     * different job.
     *
     * Lives INSIDE the bgSettings() Alpine scope, so it assigns
     * `gradientStops`, `gradientType`, `gradientAngle` and
     * `gradientPresetId` directly. The hidden input for the preset id is at
     * the top of the card, not here: the library writes that field too.
     */
    use App\Modules\User\Support\GradientCatalog;

    $allPresets  = GradientCatalog::all();
    $quickStarts = array_values(array_filter(
        $allPresets,
        fn ($p) => $p['category'] === 'featured'
    ));
@endphp

<div>
    {{-- No "scroll →" hint: twelve of these fit a desktop panel without
         scrolling, and a hint for a scrollbar that isn't there is noise. --}}
    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Quick starts</label>

    <div class="bg-quick-strip">
        @foreach($quickStarts as $p)
        <button type="button"
                title="{{ $p['name'] }}"
                @click="
                    gradientStops    = @js($p['stops']);
                    gradientType     = {{ Illuminate\Support\Js::from($p['type']) }};
                    gradientAngle    = {{ (int) $p['angle'] }};
                    gradientPresetId = {{ Illuminate\Support\Js::from($p['id']) }};
                    $nextTick(() => $dispatch('change'));
                "
                :class="gradientPresetId === {{ Illuminate\Support\Js::from($p['id']) }} ? 'is-picked' : ''"
                class="bg-lib-swatch">
            <span class="bg-lib-fill" style="background: {{ GradientCatalog::toCss($p) }};"></span>
            <span class="bg-lib-tick" aria-hidden="true"><i class="fas fa-check"></i></span>
        </button>
        @endforeach
    </div>

    {{-- Says where the rest went, and takes you there filtered. Without
         this line the 166 presets would just look deleted. --}}
    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">
        All {{ count($allPresets) }} ready-made gradients live in
        <button type="button" class="font-semibold underline underline-offset-2" style="color:#90acff;"
                @click="activeGroup = 'style'; window.dispatchEvent(new CustomEvent('bg-browse', { detail: 'gradients' }))">Style &rarr; Gradients</button>.
        Picking one there loads it into these stops.
    </p>
</div>
