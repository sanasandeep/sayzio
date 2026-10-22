<?php

namespace App\Modules\Common\Support;

/**
 * A background treatment, split into a GEOMETRY and a COLOURWAY.
 *
 * WHY SPLIT THEM
 *
 * The twelve shipped patterns each hardcode both: `pattern_honeycomb_amber`
 * is the honeycomb shape AND `#451a03` with amber ink, inseparably. That is
 * right for a published link page, which is a standalone design. It is wrong
 * for app chrome, where a background's job is to dress the theme rather than
 * replace it -- applied to the side panel, that preset paints a brown column
 * beside a violet-black page, and `pattern_stripes_sand` paints a cream one
 * in dark mode with the nav's own light ink left on top of it.
 *
 * So a geometry here names a shape and nothing else, writing {ink} where the
 * old presets wrote a literal, and a colourway supplies the base and the ink.
 * The twelve keys keep working: LEGACY maps each to its geometry plus the
 * colours it always had, so a page that already chose one renders exactly as
 * before.
 *
 * NOT EVERY SHAPE FITS EVERY SURFACE
 *
 * This is the part that cannot be left to whoever fills in the admin form.
 * The icon rail is 56px wide. A 12px crosshatch across it is four repeats,
 * which reads as noise rather than as a texture, and no choice of colour
 * rescues it. The top bar is wide but short, so a 48px tile is clipped
 * before it resolves. Each surface therefore declares the tile range it can
 * carry, and `allows()` is the one place that question is answered -- for
 * the picker, for validation, and for the renderer.
 *
 * COLOURS ARE NOT PASSED THROUGH
 *
 * Admin rows reach this class through CatalogOverrides, so every colour is
 * checked against `COLOUR` before it reaches a stylesheet. An unparseable
 * value drops the whole treatment rather than emitting a broken rule: a
 * half-written `background-image` is a silent empty box, which is the
 * failure this codebase keeps rediscovering.
 */
class SurfaceTreatment
{
    /** Hex, rgb()/rgba() with numeric arguments, or a bare CSS colour word. */
    private const COLOUR = '/^(#[0-9a-f]{3}|#[0-9a-f]{6}|#[0-9a-f]{8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)|transparent|[a-z]{3,20})$/i';

    /**
     * geometry => [label, image, size, position, tile].
     *
     * `image` is a background-image with {ink} standing in for the mark
     * colour; null means the geometry paints the base alone. `tile` is the
     * repeat in pixels, and 0 means the geometry does not repeat -- which is
     * what makes it safe on a narrow surface.
     *
     * `position` is not decoration. Checker and zigzag are each two or four
     * copies of ONE gradient, and the offsets are the entire difference
     * between a checkerboard and a set of stripes. Dropping them renders
     * something that still looks deliberate, which is how a regression like
     * that survives review.
     *
     * @var array<string, array{label:string, image:?string, size:?string, position:?string, tile:int}>
     */
    public const GEOMETRIES = [
        'plain' => ['label' => 'Plain', 'image' => null, 'size' => null, 'position' => null, 'tile' => 0],

        'wash' => ['position' => null, 'label' => 'Wash', 'tile' => 0, 'size' => null,
            'image' => 'linear-gradient(165deg, {ink}, transparent 62%)'],

        'sheen' => ['position' => null, 'label' => 'Sheen', 'tile' => 0, 'size' => null,
            'image' => 'radial-gradient(120% 80% at 50% 0%, {ink}, transparent 70%)'],

        'dots' => ['position' => null, 'label' => 'Dots', 'tile' => 22, 'size' => '22px 22px',
            'image' => 'radial-gradient({ink} 1.5px, transparent 1.5px)'],

        'grid' => ['position' => null, 'label' => 'Grid', 'tile' => 32, 'size' => '32px 32px',
            'image' => 'linear-gradient({ink} 1px, transparent 1px), linear-gradient(90deg, {ink} 1px, transparent 1px)'],

        'stripes' => ['position' => null, 'label' => 'Stripes', 'tile' => 28, 'size' => null,
            'image' => 'repeating-linear-gradient(45deg, {ink} 0px, {ink} 12px, transparent 12px, transparent 28px)'],

        'crosshatch' => ['position' => null, 'label' => 'Crosshatch', 'tile' => 12, 'size' => null,
            'image' => 'repeating-linear-gradient(45deg, {ink} 0px, {ink} 1px, transparent 1px, transparent 12px), repeating-linear-gradient(-45deg, {ink} 0px, {ink} 1px, transparent 1px, transparent 12px)'],

        'diamonds' => ['position' => null, 'label' => 'Diamonds', 'tile' => 30, 'size' => '30px 30px',
            'image' => 'linear-gradient(135deg, {ink} 25%, transparent 25%), linear-gradient(225deg, {ink} 25%, transparent 25%), linear-gradient(315deg, {ink} 25%, transparent 25%), linear-gradient(45deg, {ink} 25%, transparent 25%)'],

        'checker' => ['label' => 'Checker', 'tile' => 36, 'size' => '36px 36px', 'position' => '0 0, 18px 18px',
            'image' => 'linear-gradient(45deg, {ink} 25%, transparent 25%, transparent 75%, {ink} 75%), linear-gradient(45deg, {ink} 25%, transparent 25%, transparent 75%, {ink} 75%)'],

        'waves' => ['position' => null, 'label' => 'Waves', 'tile' => 24, 'size' => null,
            'image' => 'repeating-radial-gradient(circle at 0 0, transparent 0, transparent 22px, {ink} 22px, {ink} 24px)'],

        'zigzag' => ['label' => 'Zigzag', 'tile' => 28, 'size' => '28px 28px', 'position' => '14px 0, 14px 0, 0 0, 0 0',
            'image' => 'linear-gradient(135deg, {ink} 25%, transparent 25%), linear-gradient(225deg, {ink} 25%, transparent 25%), linear-gradient(45deg, {ink} 25%, transparent 25%), linear-gradient(315deg, {ink} 25%, transparent 25%)'],

        'honeycomb' => ['position' => null, 'label' => 'Honeycomb', 'tile' => 48, 'size' => '48px 28px',
            'image' => 'radial-gradient(circle farthest-side at 0% 50%, transparent 23.5%, {ink} 24%, {ink} 26%, transparent 26.5%), radial-gradient(circle farthest-side at 100% 50%, transparent 23.5%, {ink} 24%, {ink} 26%, transparent 26.5%)'],
    ];

    /**
     * The tile range each surface can carry, in pixels.
     *
     * A non-repeating geometry (tile 0) is allowed everywhere; the range
     * applies only to geometries that repeat. The icon rail's empty range is
     * deliberate and measured: at 56px nothing repeats enough times to read
     * as texture.
     *
     * @var array<string, array{label:string, min:int, max:int}>
     */
    public const SURFACES = [
        'rail'   => ['label' => 'Icon rail',       'min' => 0,  'max' => 0],
        'panel'  => ['label' => 'Side panel',      'min' => 20, 'max' => 48],
        'topbar' => ['label' => 'Top bar',         'min' => 20, 'max' => 32],
        'page'   => ['label' => 'Page background', 'min' => 12, 'max' => 64],
        'hero'   => ['label' => 'Hero card',       'min' => 12, 'max' => 64],
    ];

    /**
     * The surfaces whose colourway must come from the page theme.
     *
     * Chrome sits against the app's own ramp all day. A free colourway there
     * is how you get a sidebar that ignores dark mode, which is the whole
     * complaint this started from.
     */
    public const THEME_BOUND = ['rail', 'panel', 'topbar'];

    /**
     * The twelve shipped pattern keys, as geometry plus the colours they
     * always carried. Backwards compatibility, not a recommendation: these
     * are the fixed looks, kept so nothing that already chose one changes.
     *
     * @var array<string, array{0:string, 1:string, 2:string}>
     */
    public const LEGACY = [
        'pattern_dots_dark'        => ['dots', '#111827', 'rgba(148,163,184,0.35)'],
        'pattern_dots_light'       => ['dots', '#f8fafc', 'rgba(71,85,105,0.35)'],
        'pattern_grid_dark'        => ['grid', '#0f172a', 'rgba(148,163,184,0.16)'],
        'pattern_grid_light'       => ['grid', '#ffffff', 'rgba(59,130,246,0.14)'],
        'pattern_stripes_indigo'   => ['stripes', '#1e1b4b', 'rgba(129,140,248,0.16)'],
        'pattern_stripes_sand'     => ['stripes', '#fef3c7', 'rgba(217,119,6,0.14)'],
        'pattern_zigzag_teal'      => ['zigzag', '#042f2e', 'rgba(45,212,191,0.22)'],
        'pattern_waves_blue'       => ['waves', '#0c4a6e', 'rgba(125,211,252,0.16)'],
        'pattern_checker_mono'     => ['checker', '#18181b', 'rgba(161,161,170,0.14)'],
        'pattern_crosshatch_rose'  => ['crosshatch', '#4c0519', 'rgba(251,113,133,0.14)'],
        'pattern_honeycomb_amber'  => ['honeycomb', '#451a03', 'rgba(245,158,11,0.18)'],
        'pattern_diamonds_violet'  => ['diamonds', '#2e1065', 'rgba(167,139,250,0.16)'],
    ];

    /** Whether a surface can carry a geometry at a size that still reads. */
    public static function allows(string $surface, string $geometry): bool
    {
        $s = self::SURFACES[$surface] ?? null;
        $g = self::GEOMETRIES[$geometry] ?? null;

        if ($s === null || $g === null) {
            return false;
        }

        // Non-repeating geometries have no tile to fit, so they fit anywhere.
        return $g['tile'] === 0 || ($g['tile'] >= $s['min'] && $g['tile'] <= $s['max']);
    }

    /**
     * The geometries a surface may be offered, in catalogue order.
     *
     * @return array<string, string> geometry => label
     */
    public static function offered(string $surface): array
    {
        $out = [];

        foreach (self::GEOMETRIES as $key => $meta) {
            if (self::allows($surface, $key)) {
                $out[$key] = $meta['label'];
            }
        }

        return $out;
    }

    /**
     * The CSS declarations for one treatment, or null when it cannot be
     * rendered -- an unknown geometry, or a colour that did not parse.
     *
     * Returning null rather than a partial rule is the point: a background
     * with an image and no colour, or a malformed image, paints an empty box
     * and reports nothing.
     */
    public static function css(string $geometry, string $base, string $ink): ?string
    {
        $meta = self::GEOMETRIES[$geometry] ?? null;

        if ($meta === null || !self::isColour($base) || !self::isColour($ink)) {
            return null;
        }

        $out = ['background-color: ' . $base];

        if ($meta['image'] !== null) {
            $out[] = 'background-image: ' . str_replace('{ink}', $ink, $meta['image']);
        }

        if ($meta['size'] !== null) {
            $out[] = 'background-size: ' . $meta['size'];
        }

        if ($meta['position'] !== null) {
            $out[] = 'background-position: ' . $meta['position'];
        }

        return implode(';', $out);
    }

    /** The CSS for one of the twelve shipped keys, or null if it is not one. */
    public static function legacyCss(string $key): ?string
    {
        $row = self::LEGACY[$key] ?? null;

        return $row === null ? null : self::css($row[0], $row[1], $row[2]);
    }

    /**
     * A colour this class is willing to put in a stylesheet.
     *
     * Deliberately narrow. Admin rows reach here from the database, and the
     * catalogues' own rule is that CSS is resolved server-side and never
     * taken from client input; anything with a semicolon, a brace or a url()
     * in it is not a colour and does not belong in a declaration.
     */
    public static function isColour(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && preg_match(self::COLOUR, $value) === 1;
    }

    /** The `in:` rule bodies, so validation cannot drift from the catalogue. */
    public static function geometryRule(): string
    {
        return 'in:' . implode(',', array_keys(self::GEOMETRIES));
    }

    public static function surfaceRule(): string
    {
        return 'in:' . implode(',', array_keys(self::SURFACES));
    }
}
