<?php

namespace App\Modules\User\Support;

/**
 * Shared vocabulary for the decorative accent shapes (Task #5922 photo
 * accents, Task #5938 heading accents). Both the image block's collage
 * accents and the heading block's shape accents render from this single
 * SVG set so the two features can never drift apart, and the sanitizer
 * allowlists derive from keys() so a new shape only needs to be added
 * here (plus the mobile renderer's mirror).
 */
class AccentShapeCatalog
{
    /**
     * Each shape: `viewBox` + base `w`/`h` (px), `mode` = fill|stroke,
     * optional stroke attributes, and `body` — the inner SVG markup.
     * Bodies are static trusted markup (no user input is ever
     * interpolated into them); the accent color is applied on the
     * <svg> element by the shared partial.
     */
    public const SHAPES = [
        'starburst' => [
            'viewBox' => '0 0 100 100', 'w' => 54, 'h' => 54, 'mode' => 'fill',
            'body' => '<path d="M50 0 L56 33 L75 7 L63 38 L96 22 L67 44 L100 50 L67 56 L96 78 L63 62 L75 93 L56 67 L50 100 L44 67 L25 93 L37 62 L4 78 L33 56 L0 50 L33 44 L4 22 L37 38 L25 7 L44 33 Z"/>',
        ],
        'dots' => [
            'viewBox' => '0 0 90 90', 'w' => 76, 'h' => 76, 'mode' => 'fill',
            'body' => '<circle cx="78" cy="10" r="6"/><circle cx="58" cy="18" r="4.5"/><circle cx="76" cy="30" r="4"/><circle cx="44" cy="10" r="3.5"/><circle cx="62" cy="38" r="3.2"/><circle cx="82" cy="46" r="3"/><circle cx="48" cy="28" r="2.6"/><circle cx="70" cy="54" r="2.4"/><circle cx="34" cy="20" r="2.2"/><circle cx="56" cy="50" r="2"/><circle cx="84" cy="62" r="2"/><circle cx="42" cy="42" r="1.8"/><circle cx="66" cy="68" r="1.6"/><circle cx="78" cy="76" r="1.4"/>',
        ],
        'squiggle' => [
            'viewBox' => '0 0 120 40', 'w' => 84, 'h' => 28, 'mode' => 'stroke',
            'stroke_width' => 5, 'linecap' => 'round',
            'body' => '<path d="M5 30 Q20 5 35 25 T65 22 T95 24 T115 15"/>',
        ],
        'ring' => [
            'viewBox' => '0 0 60 60', 'w' => 46, 'h' => 46, 'mode' => 'stroke',
            'stroke_width' => 6,
            'body' => '<circle cx="30" cy="30" r="24"/>',
        ],
        'blob' => [
            'viewBox' => '0 0 100 100', 'w' => 58, 'h' => 58, 'mode' => 'fill',
            'body' => '<path d="M83 45 C90 62 78 84 58 88 C38 92 16 82 12 62 C8 42 22 20 44 14 C66 8 76 28 83 45 Z"/>',
        ],
    ];

    /** Editor-facing labels, shared by the image + heading style drawers. */
    public const LABELS = [
        'starburst' => 'Starburst',
        'dots'      => 'Dot cluster',
        'squiggle'  => 'Squiggle',
        'ring'      => 'Ring',
        'blob'      => 'Blob',
    ];

    /** Heading accent placement allowlist (sanitizer + editor + renderer). */
    public const HEADING_PLACEMENTS = ['behind_left', 'behind_right', 'top_left', 'top_right'];

    /** Heading accent size allowlist mapped to a scale factor at render. */
    public const HEADING_SIZES = ['sm', 'md', 'lg'];

    public const HEADING_SIZE_SCALES = ['sm' => 0.7, 'md' => 1.0, 'lg' => 1.5];

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::SHAPES);
    }

    /**
     * Parse a stored comma-separated accent token string into the ordered,
     * de-duplicated list of known shapes. Unknown tokens are dropped.
     *
     * @return string[]
     */
    public static function parseTokens(string $raw): array
    {
        return array_values(array_unique(array_intersect(
            array_filter(array_map('trim', explode(',', strtolower($raw)))),
            self::keys()
        )));
    }
}
