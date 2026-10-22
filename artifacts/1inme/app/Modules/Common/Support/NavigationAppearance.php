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

    /** preset => [surface, ink, muted]. Shared with the JS by @json(). */
    public const PALETTES = [
        'light'           => ['#ffffff', '#172033', '#566176'],
        'dark'            => ['#111827', '#f8fafc', '#cbd5e1'],
        'gradient-light'  => ['linear-gradient(135deg,#edf4ff 0%,#f0eaff 55%,#e4f6f5 100%)', '#172033', '#49556c'],
        'gradient-medium' => ['linear-gradient(135deg,#4f46b5 0%,#6751bb 55%,#296d88 100%)', '#ffffff', '#ede9fe'],
        'gradient-dark'   => ['linear-gradient(135deg,#111c38 0%,#2b1d48 55%,#12383c 100%)', '#ffffff', '#d9e1f2'],
    ];

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
}
