<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Support\Collection;

/**
 * One library over every ready-made background look.
 *
 * The Appearance panel used to offer six separate pickers -- Presets, Mesh,
 * Pattern, Tiles, Torn Paper, Template -- which all asked the same question:
 * pick a ready-made look. Because each was built on a different day over a
 * different catalog, the same words appeared at two levels: "Mesh" was a
 * picker AND a chip inside Template, "Patterns" was a picker AND a chip AND a
 * preset group, and "Gradients" was a chip inside Template while the Gradient
 * type sat over in Colour. Grouping the pickers made that legible; it did not
 * make it go away, because six pickers over six catalogs is the duplication.
 *
 * This class is the fix. It merges all six sources into one list and gives
 * every entry a category drawn from what the look IS, not from which PHP
 * class happens to hold it. So "Mesh" is one chip over 110 looks from two
 * sources, and no name appears twice.
 *
 * WHAT IS SAVED DOES NOT CHANGE. Every entry keeps the `type` and `value`
 * its own picker wrote, so choosing a look still sets the same
 * `background_type` plus the same key field it always did. No migration, and
 * the public renderer is untouched.
 *
 * Task #6234 -- the 166 gradient PRESETS joined too. They were browsed in
 * the Colour tab, behind a chip row of their own, which made Colour and
 * Style read as the same feature twice and put "Neon" and "Abstract" in
 * both. There is one place to browse now. Colour keeps a short quick-start
 * strip and is otherwise a builder. A gradient entry carries its stops, so
 * picking one in the library loads it into that builder, editable -- which
 * the two separate surfaces never did.
 *
 * `thumb` describes how to paint the swatch, because the sources paint
 * differently: a template needs its generated class, a preset carries inline
 * CSS, tiles are a four-up of gradients, a torn look is clipped sheets of
 * paper over a backdrop.
 */
class BackgroundLibrary
{
    /**
     * Unified categories, in the order the chips appear.
     *
     * These describe the LOOK. `illustrated` is the old `svg` template
     * category under a name that means something to whoever is choosing.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'gradients'   => 'Gradients',
        'mesh'        => 'Mesh',
        'patterns'    => 'Patterns',
        'abstract'    => 'Abstract',
        'animated'    => 'Animated',
        'illustrated' => 'Illustrated',
        'neon'        => 'Neon',
        'tiles'       => 'Tiles',
        'torn'        => 'Torn paper',
    ];

    /** Template's stored category => the library category it belongs to. */
    private const TEMPLATE_CATEGORY = [
        'animated' => 'animated',
        'gradient' => 'gradients',
        'mesh'     => 'mesh',
        'pattern'  => 'patterns',
        'svg'      => 'illustrated',
        'neon'     => 'neon',
    ];

    /** Preset group => library category. */
    private const PRESET_CATEGORY = [
        'patterns' => 'patterns',
        'abstract' => 'abstract',
    ];

    /**
     * Every look, from every source.
     *
     * @param  Collection|null  $templates  pass the already-loaded active
     *         templates to avoid a second query; loaded here if omitted.
     * @return list<array{type: string, value: string, label: string, category: string, thumb: array}>
     */
    public static function items(?Collection $templates = null): array
    {
        $templates ??= BgTemplate::active()->get();

        $items = [];

        foreach ($templates as $tpl) {
            $stored  = $tpl->category ?: 'pattern';
            $preview = (string) $tpl->preview_color;

            $items[] = [
                'type'     => 'template',
                'value'    => (string) $tpl->id,
                'label'    => (string) $tpl->name,
                'category' => self::TEMPLATE_CATEGORY[$stored] ?? 'patterns',
                'thumb'    => [
                    'kind'  => 'class',
                    'class' => 'bg-thumb-'.$tpl->slug,
                    // A preview_color holding a declaration (e.g. an SVG data
                    // URI) is not a colour; the generated class paints it.
                    'ground' => str_contains($preview, ':') ? '#0f172a' : $preview,
                ],
            ];
        }

        foreach (BgPresetCatalog::pickerPresets() as $key => $preset) {
            $items[] = [
                'type'     => 'preset',
                'value'    => $key,
                'label'    => $preset['label'],
                'category' => self::PRESET_CATEGORY[$preset['group']] ?? 'abstract',
                'thumb'    => ['kind' => 'css', 'css' => $preset['css']],
            ];
        }

        foreach (MeshGradientCatalog::all() as $key => $mesh) {
            $items[] = [
                'type'     => 'mesh',
                'value'    => $key,
                'label'    => $mesh['label'],
                'category' => 'mesh',
                'thumb'    => ['kind' => 'css', 'css' => (string) MeshGradientCatalog::css($key)],
            ];
        }

        foreach (PatternCatalog::all() as $key => $pattern) {
            $items[] = [
                'type'     => 'pattern',
                'value'    => $key,
                'label'    => $pattern['label'],
                'category' => 'patterns',
                'thumb'    => ['kind' => 'css', 'css' => $pattern['css']],
            ];
        }

        foreach (TilesBgCatalog::palettes() as $key => $palette) {
            $items[] = [
                'type'     => 'tiles',
                'value'    => $key,
                'label'    => $palette['label'],
                'category' => 'tiles',
                'thumb'    => ['kind' => 'tiles', 'tiles' => array_slice($palette['tiles'], 0, 4)],
            ];
        }

        // The gradient presets. Their mood categories (Warm, Cool, Pastel,
        // Monochrome, Tropical, Metal, Classic...) are NOT chips -- that
        // second chip row is what made this look like a second library --
        // but they ride along in `search`, so typing "pastel" still finds
        // them. Every one lands under Gradients.
        foreach (GradientCatalog::all() as $preset) {
            $mood = GradientCatalog::CATEGORIES[$preset['category']] ?? '';

            $items[] = [
                'type'     => 'gradient',
                'value'    => (string) $preset['id'],
                'label'    => (string) $preset['name'],
                'category' => 'gradients',
                'search'   => mb_strtolower(trim($preset['name'].' '.$mood)),
                'thumb'    => ['kind' => 'css', 'css' => 'background: '.GradientCatalog::toCss($preset)],
                // Carried so a pick can load the builder rather than just
                // paint a fixed background.
                'gradient' => [
                    'stops' => $preset['stops'],
                    'type'  => $preset['type'],
                    'angle' => (int) $preset['angle'],
                ],
            ];
        }

        // A torn LOOK is a tear shape plus a paper/backdrop colourway, which
        // is what PRESETS already holds. Choosing one sets four fields, so it
        // carries them rather than a single key.
        foreach (TornStyleCatalog::presets() as $key => $combo) {
            // A colourway is two colours. An admin-made row that arrives
            // with fewer repeats what it has rather than reading past the
            // end of the array.
            $backdrop = array_values((array) $combo['backdrop']);
            if ($backdrop === []) {
                continue;
            }
            $backdrop[1] ??= $backdrop[0];

            $items[] = [
                'type'     => 'torn',
                'value'    => $key,
                'label'    => $combo['label'],
                'category' => 'torn',
                'thumb'    => [
                    'kind'     => 'torn',
                    'paper'    => $combo['paper'],
                    'backdrop' => $backdrop,
                    'sheets'   => TornStyleCatalog::sheets($combo['style']),
                ],
                'torn' => [
                    'style'     => $combo['style'],
                    'paper'     => $combo['paper'],
                    'backdrop'  => $backdrop[0],
                    'backdrop2' => $backdrop[1],
                ],
            ];
        }

        // Anything an admin has hidden comes out of the LIBRARY and stays in
        // the renderer, so a page that already chose it keeps rendering it.
        // (Templates are filtered upstream by BgTemplate::active().)
        $hidden = [];
        foreach (['preset', 'gradient', 'mesh', 'pattern', 'tiles', 'torn'] as $kind) {
            foreach (CatalogOverrides::hidden($kind) as $key => $_) {
                $hidden[$kind.'/'.$key] = true;
            }
        }
        if ($hidden !== []) {
            $items = array_values(array_filter(
                $items,
                fn ($item) => ! isset($hidden[$item['type'].'/'.$item['value']])
            ));
        }

        // Everything is searchable by its own name unless a source gave
        // richer words.
        foreach ($items as $i => $item) {
            $items[$i]['search'] = $item['search'] ?? mb_strtolower($item['label']);
        }

        return $items;
    }

    /**
     * Category key => how many looks are in it, skipping the empty ones so a
     * chip never promises nothing.
     *
     * @param  list<array>  $items
     * @return array<string, int>
     */
    public static function counts(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $counts[$item['category']] = ($counts[$item['category']] ?? 0) + 1;
        }

        return array_filter(array_map(
            fn ($key) => $counts[$key] ?? 0,
            array_combine(array_keys(self::CATEGORIES), array_keys(self::CATEGORIES))
        ));
    }

    /**
     * Which library entry a link is currently using, or null.
     *
     * Read from the saved settings rather than from a stored library id: the
     * library is a view over the same fields the six pickers always wrote, so
     * a page saved before it existed selects correctly.
     *
     * @param  array  $bs  the biolink settings array
     */
    public static function selectedValue(array $bs): ?string
    {
        return match ($bs['background_type'] ?? null) {
            'gradient' => ($bs['gradient_preset_id'] ?? '') ?: null,
            'template' => isset($bs['bg_template_id']) ? (string) $bs['bg_template_id'] : null,
            'preset'   => ($bs['bg_preset_key'] ?? '') ?: null,
            'mesh'     => ($bs['mesh_preset'] ?? '') ?: null,
            'pattern'  => ($bs['pattern_preset'] ?? '') ?: null,
            'tiles'    => ($bs['tiles_palette'] ?? '') ?: null,
            // Torn stores its parts, not the combo key, so match the parts
            // back to a combo -- and show nothing when the colours have been
            // hand-edited away from every preset, which is honest.
            'torn'     => self::matchTornCombo($bs),
            default    => null,
        };
    }

    private static function matchTornCombo(array $bs): ?string
    {
        foreach (TornStyleCatalog::presets() as $key => $combo) {
            if (($bs['torn_style'] ?? TornStyleCatalog::DEFAULT) === $combo['style']
                && strcasecmp($bs['torn_paper_color'] ?? '', $combo['paper']) === 0
                && strcasecmp($bs['torn_backdrop_color'] ?? '', $combo['backdrop'][0]) === 0
                && strcasecmp($bs['torn_backdrop_color2'] ?? '', $combo['backdrop'][1]) === 0
            ) {
                return $key;
            }
        }

        return null;
    }
}
