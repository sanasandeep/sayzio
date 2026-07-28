<?php

namespace App\Modules\User\Support;

/**
 * Decorative avatar frame shapes for biolink profile cards (Task #5910).
 *
 * Single server-side source of truth for the frame catalog: keys, labels
 * and the inline-SVG markup rendered behind circular avatars on the public
 * page and in the editor's swatch picker. The mobile app mirrors the KEYS
 * (see artifacts/1inme-mobile/lib/avatarFrames.ts) and re-implements the
 * shapes with react-native-svg, so any key added here must be mirrored
 * there or mobile silently renders no frame.
 *
 * Selection is stored in the block's `_style._avatar_frame` (plus optional
 * `_avatar_frame_color`), validated by sanitizeBlockStyle() against keys();
 * unknown values are stripped on save and ignored at render time.
 *
 * Geometry: every frame is drawn on a 120x120 viewBox with the avatar
 * assumed to sit as a centered circle of radius ~44 (the render wrapper
 * insets the SVG -18% around the avatar, so 120/1.36 ≈ 88px ≈ avatar
 * diameter). Frames stay within the viewBox so nothing is clipped.
 */
class AvatarFrameCatalog
{
    /** Frame keys → human labels, in picker display order. */
    public const FRAMES = [
        'starburst'   => 'Starburst',
        'scalloped'   => 'Scalloped',
        'zigzag'      => 'Sunburst',
        'wavy'        => 'Wavy Blob',
        'double_ring' => 'Double Ring',
        'dotted_ring' => 'Dotted Ring',
        'petal'       => 'Petal Bloom',
    ];

    /** @return string[] valid frame keys (excluding the '' = none default) */
    public static function keys(): array
    {
        return array_keys(self::FRAMES);
    }

    public static function isValid(?string $key): bool
    {
        return is_string($key) && isset(self::FRAMES[$key]);
    }

    /**
     * Inline SVG markup for a frame, tinted by $color. Returns null for
     * unknown/empty keys or unsafe colors so callers can simply skip
     * rendering. The color is strictly validated here (defense in depth —
     * the sanitizer already restricts it) because the output is echoed raw.
     */
    public static function svg(?string $key, string $color): ?string
    {
        if (!self::isValid($key)) {
            return null;
        }
        if (!preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $color)) {
            return null;
        }

        $body = match ($key) {
            'starburst'   => self::starPolygon(16, 46.0, 58.0, $color),
            'zigzag'      => self::starPolygon(28, 45.0, 56.0, $color),
            'scalloped'   => self::scallops($color),
            'wavy'        => self::wavyBlob($color),
            'double_ring' => '<circle cx="60" cy="60" r="48" fill="none" stroke="' . $color . '" stroke-width="3"/>'
                . '<circle cx="60" cy="60" r="55" fill="none" stroke="' . $color . '" stroke-width="1.6"/>',
            'dotted_ring' => self::dots($color),
            'petal'       => self::petals($color),
        };

        return '<svg viewBox="0 0 120 120" width="100%" height="100%" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $body . '</svg>';
    }

    /** Closed star polygon alternating between inner and outer radii. */
    private static function starPolygon(int $points, float $rInner, float $rOuter, string $color): string
    {
        $pts = [];
        $steps = $points * 2;
        for ($i = 0; $i < $steps; $i++) {
            $r = ($i % 2 === 0) ? $rOuter : $rInner;
            $a = M_PI * 2 * $i / $steps - M_PI / 2;
            $pts[] = round(60 + $r * cos($a), 2) . ',' . round(60 + $r * sin($a), 2);
        }

        return '<polygon points="' . implode(' ', $pts) . '" fill="' . $color . '"/>';
    }

    /** Flower ring of overlapping circles around the avatar. */
    private static function scallops(string $color): string
    {
        $out = '';
        $n = 12;
        for ($i = 0; $i < $n; $i++) {
            $a = M_PI * 2 * $i / $n - M_PI / 2;
            $cx = round(60 + 48 * cos($a), 2);
            $cy = round(60 + 48 * sin($a), 2);
            $out .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="11" fill="' . $color . '"/>';
        }

        return $out;
    }

    /** Organic wobbly blob (smooth radial sine wave). */
    private static function wavyBlob(string $color): string
    {
        $steps = 72;
        $d = '';
        for ($i = 0; $i <= $steps; $i++) {
            $a = M_PI * 2 * $i / $steps;
            $r = 51 + 6 * sin($a * 6);
            $x = round(60 + $r * cos($a), 2);
            $y = round(60 + $r * sin($a), 2);
            $d .= ($i === 0 ? 'M' : 'L') . $x . ' ' . $y;
        }
        $d .= 'Z';

        return '<path d="' . $d . '" fill="' . $color . '"/>';
    }

    /** Ring of evenly spaced dots. */
    private static function dots(string $color): string
    {
        $out = '';
        $n = 20;
        for ($i = 0; $i < $n; $i++) {
            $a = M_PI * 2 * $i / $n - M_PI / 2;
            $cx = round(60 + 52 * cos($a), 2);
            $cy = round(60 + 52 * sin($a), 2);
            $out .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="3.4" fill="' . $color . '"/>';
        }

        return $out;
    }

    /** Bloom of rotated petal ellipses behind the avatar. */
    private static function petals(string $color): string
    {
        $out = '';
        $n = 8;
        for ($i = 0; $i < $n; $i++) {
            $deg = round(360 * $i / $n, 2);
            $out .= '<ellipse cx="60" cy="14" rx="11" ry="19" fill="' . $color
                . '" transform="rotate(' . $deg . ' 60 60)"/>';
        }

        return $out;
    }
}
