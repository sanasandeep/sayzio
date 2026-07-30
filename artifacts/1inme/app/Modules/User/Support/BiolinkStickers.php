<?php

namespace App\Modules\User\Support;

use App\Modules\User\Controllers\BiolinkBlockController;

/**
 * Page stickers — freely-placed, tilted emoji/image decorations that float
 * over a biolink page. Stored in settings['biolink']['stickers'] as a flat
 * array of items. This class is the single sanitization source shared by the
 * web save path (BiolinkBlockController::updatePageSettings), the REST API
 * save path (Api\LinkController::update) and the public renderer, so raw
 * client data never reaches the page unbounded.
 *
 * Item shape:
 *   kind     'emoji' | 'image'
 *   value    emoji text (<= 16 chars) or an image URL passing sanitizeUrl
 *            (https?:// or a /f/ vault path)
 *   x, y     position as percentages of the viewport (0-100)
 *   rotation degrees, -180..180
 *   scale    0.4..3 (1 = base size: 36px emoji / 64px image)
 *   layer    'front' (above content, default) | 'back' (behind content)
 *   position_mode 'fixed' (pinned to the viewport, default) | 'scroll'
 *            (placed on a page-height layer so it moves with the content)
 *   animation looping highlight effect: one of ANIMATIONS ('none' default)
 *   loop     repeat count for the animation: one of LOOPS ('infinite' default)
 */
class BiolinkStickers
{
    public const MAX_STICKERS = 10;
    public const MAX_EMOJI_CHARS = 16;
    public const MIN_SCALE = 0.4;
    public const MAX_SCALE = 3.0;

    /** Allowlisted highlight animation effects ('none' = no animation). */
    public const ANIMATIONS = ['none', 'pulse', 'bounce', 'wiggle', 'spin', 'float', 'glow'];

    /** Allowlisted loop counts for the animation. */
    public const LOOPS = ['1', '3', '5', 'infinite'];

    /**
     * Sanitize an untrusted stickers payload (array of items, or a JSON
     * string of one) into the canonical bounded shape. Invalid items are
     * dropped; the list is capped at MAX_STICKERS and reindexed.
     */
    public static function sanitize($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) continue;

            $kind = ($item['kind'] ?? '') === 'image' ? 'image' : 'emoji';

            $value = trim((string) ($item['value'] ?? ''));
            if ($kind === 'emoji') {
                $value = strip_tags($value);
                $value = mb_substr($value, 0, self::MAX_EMOJI_CHARS);
                if ($value === '') continue;
            } else {
                $value = BiolinkBlockController::sanitizeUrl($value);
                if ($value === '') continue;
            }

            $out[] = [
                'kind'     => $kind,
                'value'    => $value,
                'x'        => self::clampFloat($item['x'] ?? 50, 0, 100),
                'y'        => self::clampFloat($item['y'] ?? 50, 0, 100),
                'rotation' => (int) self::clampFloat($item['rotation'] ?? 0, -180, 180),
                'scale'    => self::clampFloat($item['scale'] ?? 1, self::MIN_SCALE, self::MAX_SCALE),
                'layer'    => ($item['layer'] ?? 'front') === 'back' ? 'back' : 'front',
                'position_mode' => ($item['position_mode'] ?? 'fixed') === 'scroll' ? 'scroll' : 'fixed',
                'animation' => in_array($anim = (string) ($item['animation'] ?? 'none'), self::ANIMATIONS, true)
                    ? $anim
                    : 'none',
                'loop'     => in_array($loop = (string) ($item['loop'] ?? 'infinite'), self::LOOPS, true)
                    ? $loop
                    : 'infinite',
            ];

            if (count($out) >= self::MAX_STICKERS) break;
        }

        return $out;
    }

    private static function clampFloat($v, float $min, float $max): float
    {
        $v = is_numeric($v) ? (float) $v : ($min + $max) / 2;
        if (!is_finite($v)) $v = ($min + $max) / 2;
        return round(max($min, min($max, $v)), 2);
    }
}
