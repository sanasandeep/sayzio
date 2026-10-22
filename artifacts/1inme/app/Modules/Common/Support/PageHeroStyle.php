<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * The page-hero look, chosen once by an admin and applied to every page that
 * renders `user.partials.page-hero`.
 *
 * Shaped after navigation_appearance: an AppSetting array, a dataset attribute
 * on <html>, and CSS custom properties. Unlike that one there is no per-user
 * override - the admin's choice is the whole story - so no JS is needed and
 * the attribute is rendered server-side.
 *
 * A style is a LAYOUT plus a COLOURWAY:
 *
 *   'rings'  - concentric arcs in the corner, over a soft bloom
 *   'fields' - four tinted colour bands across the card
 *
 * Both paint onto .page-hero-deco, the wrapper that already exists for clipped
 * decorations. Nothing here touches overflow on .page-hero itself: it is
 * deliberately not clipped, because clipping it cut off the Export dropdown.
 *
 * The bands are TINTS, not the saturated colours from the design boards. The
 * hero title is ink and the card is the reading surface, so a full-strength
 * band underneath it would not be legible.
 */
class PageHeroStyle
{
    public const SETTING = 'page_hero_appearance';

    /** Leaves the existing grid lattice alone. */
    public const FALLBACK = 'rings-violet';

    /** Role => [light, dark], from theme-styles.blade.php. */
    private const RAMP = [
        'primary' => ['#3d6bff', '#7d9bff'],
        'indigo'  => ['#6e61ff', '#9c92ff'],
        'pink'    => ['#d76dff', '#e29bff'],
        'info'    => ['#1b84ff', '#67e8f9'],
        'teal'    => ['#14b8a6', '#2dd4bf'],
        'success' => ['#17c653', '#34d399'],
        'warning' => ['#f6c000', '#fbbf24'],
        'danger'  => ['#f8285a', '#fb7185'],
    ];

    private const COLOURWAYS = [
        'violet'  => ['label' => 'Violet',  'roles' => ['indigo', 'pink', 'primary', 'info']],
        'aqua'    => ['label' => 'Aqua',    'roles' => ['info', 'teal', 'primary', 'success']],
        'warm'    => ['label' => 'Warm',    'roles' => ['warning', 'danger', 'pink', 'indigo']],
        'green'   => ['label' => 'Green',   'roles' => ['success', 'teal', 'info', 'warning']],
        'magenta' => ['label' => 'Magenta', 'roles' => ['danger', 'pink', 'indigo', 'primary']],
    ];

    private const LAYOUTS = [
        'rings'  => 'Arc rings',
        'fields' => 'Colour fields',
    ];

    /**
     * @return array<string, array{label:string, layout:string, colourway:string,
     *     light:array<int,string>, dark:array<int,string>}>
     */
    public static function options(): array
    {
        static $options = null;
        if ($options !== null) {
            return $options;
        }

        $options = [];
        foreach (self::LAYOUTS as $layout => $layoutLabel) {
            foreach (self::COLOURWAYS as $way => $meta) {
                $options["{$layout}-{$way}"] = [
                    'label'     => "{$meta['label']} - {$layoutLabel}",
                    'layout'    => $layout,
                    'colourway' => $way,
                    'light'     => array_map(fn (string $r): string => self::RAMP[$r][0], $meta['roles']),
                    'dark'      => array_map(fn (string $r): string => self::RAMP[$r][1], $meta['roles']),
                ];
            }
        }

        return $options;
    }

    /** The admin's choice, or the fallback when unset or no longer valid. */
    public static function current(): string
    {
        $stored = AppSetting::get(self::SETTING, []);
        $key = is_array($stored) ? ($stored['style'] ?? null) : $stored;

        return (is_string($key) && isset(self::options()[$key])) ? $key : self::FALLBACK;
    }

    /** Persist a choice, ignoring anything not in the catalogue. */
    public static function store(?string $key): void
    {
        if (is_string($key) && isset(self::options()[$key])) {
            AppSetting::put(self::SETTING, ['style' => $key]);
        }
    }

    /**
     * White or ink on the lead hue, whichever measures higher.
     *
     * Not a 4.5 threshold with an ink fallback: on #6e61ff that picks ink at
     * 4.13 over white at 4.36, the worse of the two.
     */
    public static function onLead(string $hex): string
    {
        return self::contrast('#ffffff', $hex) >= self::contrast('#071437', $hex) ? '#ffffff' : '#071437';
    }

    /** WCAG contrast ratio between two hex colours. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $out = 0.0;
        foreach ([[0, 0.2126], [2, 0.7152], [4, 0.0722]] as [$offset, $weight]) {
            $v = hexdec(substr($hex, $offset, 2)) / 255;
            $v = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            $out += $v * $weight;
        }

        return $out;
    }

    /** hex + alpha -> rgba(), for the tints the CSS partial emits. */
    public static function tint(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        return sprintf(
            'rgba(%d,%d,%d,%s)',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            rtrim(rtrim(number_format($alpha, 3, '.', ''), '0'), '.')
        );
    }
}
