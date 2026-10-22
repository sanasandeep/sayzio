<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * The navigation surfaces an admin can tone, and the presets they can take.
 *
 * WHY THIS IS A CLASS
 *
 * The preset list used to be typed out in four places - the controller's
 * validation rule, the JS palette map, the admin form and the user's Design
 * page - and nothing held them together. Adding a preset meant finding all
 * four, and a missed one failed quietly: validation would reject a value the
 * form offered, or the form would offer a value the JS had no palette for and
 * the surface would silently fall back to light.
 *
 * THE RAIL AND THE PANEL ARE TWO SURFACES, NOT ONE
 *
 * Rail navigation puts a 72px icon rail beside a panel. Until now both read
 * `sidebar`, so they always carried the same tone and a dark rail with a light
 * panel - the thing the layout is for - was not expressible.
 *
 * `sidebar` therefore becomes two areas. Anything already stored under the old
 * key seeds BOTH of them, so an admin who had chosen a dark sidebar keeps a
 * dark rail and a dark panel rather than being reset to the theme. The same
 * migration runs for a user's saved preferences, in JS, for the same reason.
 */
class NavigationAppearance
{
    public const SETTING = 'navigation_appearance';

    /** The old single key, read once to seed the two that replaced it. */
    public const LEGACY_AREA = 'sidebar';

    /** area => label, in the order the pickers show them. */
    public const AREAS = [
        'rail'   => 'Icon rail',
        'panel'  => 'Panel',
        'topbar' => 'Top bar',
    ];

    /**
     * preset => [label, surface, ink, muted].
     *
     * 'theme' carries no palette: it means "leave the surface alone and let
     * the page theme own it", which is why it is not in PALETTES below.
     */
    public const PRESETS = [
        'theme'           => 'Page theme',
        'light'           => 'Light',
        'dark'            => 'Dark',
        'gradient-light'  => 'Light gradient',
        'gradient-medium' => 'Medium gradient',
        'gradient-dark'   => 'Dark gradient',
    ];

    /**
     * preset => token => value. The one place a preset's colours live.
     *
     * WHY THIS GREW FROM A THREE-TUPLE
     *
     * The same facts were written down in three places: here, as literals
     * in the preset rules of navigation-appearance.blade.php, and again as
     * hand-written swatch CSS on the user's Design page. Nothing held them
     * together, and two of the three had already drifted.
     *
     * Measured on the live Design page: the Dark swatch painted #111827 --
     * the navy from before the preset was neutralised, two colour changes
     * stale. And this array still said #18181b after the preset itself
     * moved to #0b0a11, so the palette was a generation behind the CSS it
     * is supposed to describe. Neither drift broke anything loudly; the
     * picker simply showed a colour the product had stopped using.
     *
     * So the blade now emits the preset rules from these values and the
     * swatches are generated from them too. Adding a preset is one entry.
     *
     * The gradient presets carry only the three tokens the shared
     * `[appearance^="gradient-"]` rule needs; it derives line, active and
     * active-ink from the ink by colour-mix, which a literal cannot do.
     * `tokens()` is what tells the two kinds apart.
     *
     * Shared with the JS by @json().
     */
    public const PALETTES = [
        'light' => [
            'surface' => '#ffffff', 'ink' => '#172033', 'muted' => '#566176',
            'control' => '#ffffff', 'line' => '#dce2ed',
            'active' => '#e8edff', 'active-ink' => '#2948d5',
        ],
        'dark' => [
            'surface' => '#0b0a11', 'ink' => '#f3f1f7', 'muted' => '#9b97ac',
            'control' => '#100f17', 'line' => '#191823',
            'active' => '#201f2e', 'active-ink' => '#f3f1f7',
        ],
        'gradient-light' => [
            'surface' => 'linear-gradient(135deg,#edf4ff 0%,#f0eaff 55%,#e4f6f5 100%)',
            'ink' => '#172033', 'muted' => '#49556c', 'control' => '#f2f1fc',
        ],
        'gradient-medium' => [
            'surface' => 'linear-gradient(135deg,#4f46b5 0%,#6751bb 55%,#296d88 100%)',
            'ink' => '#ffffff', 'muted' => '#ede9fe', 'control' => '#352f70',
        ],
        'gradient-dark' => [
            'surface' => 'linear-gradient(135deg,#111c38 0%,#2b1d48 55%,#12383c 100%)',
            'ink' => '#ffffff', 'muted' => '#d9e1f2', 'control' => '#171f35',
        ],
    ];

    /** The tokens every solid preset defines, in the order the rule writes them. */
    public const TOKENS = ['control', 'surface', 'ink', 'muted', 'line', 'active', 'active-ink'];

    /** The presets whose ink is light, so the dark logo is the readable one. */
    public const DARK_PRESETS = ['dark', 'gradient-medium', 'gradient-dark'];

    /**
     * The admin defaults, with the legacy `sidebar` key folded into rail and
     * panel. Always returns a value for every area.
     *
     * @return array{rail:string, panel:string, topbar:string, logo:bool}
     */
    public static function defaults(): array
    {
        $stored = AppSetting::get(self::SETTING, []);
        $stored = is_array($stored) ? $stored : [];

        $legacy = self::normalise($stored[self::LEGACY_AREA] ?? null);
        $out = ['logo' => ($stored['logo'] ?? false) === true];

        foreach (array_keys(self::AREAS) as $area) {
            $own = self::normalise($stored[$area] ?? null);
            // rail and panel inherit the old sidebar choice; topbar never did.
            $seed = $area === 'topbar' ? null : $legacy;
            $out[$area] = $own ?? $seed ?? 'theme';
        }

        return $out;
    }

    /** A preset name, or null when the value is not one we ship. */
    public static function normalise(mixed $value): ?string
    {
        if ($value === 'color') {
            // An old alias. It was the only gradient before there were three.
            return 'gradient-medium';
        }

        return (is_string($value) && isset(self::PRESETS[$value])) ? $value : null;
    }

    /** The `in:` rule body, so validation cannot drift from the catalogue. */
    public static function rule(): string
    {
        return 'in:' . implode(',', array_keys(self::PRESETS));
    }

    /** Whether a preset wants the dark logo on top of it. */
    public static function wantsDarkLogo(string $preset): bool
    {
        return in_array($preset, self::DARK_PRESETS, true);
    }

    /**
     * A preset's full token set, or [] when it does not define one.
     *
     * A preset either states all seven tokens and is emitted as literals,
     * or states only surface/ink/muted and lets the shared gradient rule
     * derive the rest. Asking this question here means the blade does not
     * have to know which presets are which.
     *
     * @return array<string, string>
     */
    public static function tokens(string $preset): array
    {
        $palette = self::PALETTES[$preset] ?? [];

        foreach (self::TOKENS as $token) {
            if (!isset($palette[$token])) {
                return [];
            }
        }

        return $palette;
    }

    /**
     * What a preset looks like in a picker swatch.
     *
     * The surface alone, because that is what the preset paints. 'theme'
     * has no palette -- it means "leave the surface to the page theme" --
     * so it gets a split of the two grounds a theme can be, built from the
     * two solid presets rather than from a pair of literals that can go
     * stale the way the last pair did.
     */
    public static function swatch(string $preset): string
    {
        if ($preset === 'theme' || $preset === '') {
            return 'linear-gradient(90deg,' . self::PALETTES['light']['surface'] . ' 50%,'
                . self::PALETTES['dark']['surface'] . ' 50%)';
        }

        return self::PALETTES[$preset]['surface'] ?? 'transparent';
    }
}
