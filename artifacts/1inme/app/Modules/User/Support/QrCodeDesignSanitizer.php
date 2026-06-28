<?php

namespace App\Modules\User\Support;

/**
 * Single source of truth for QR design sanitization + defaults.
 *
 * Shared by the web builder (User\QrCodeController) and the REST API
 * (Api\QrCodeController) so both surfaces validate the same design tree
 * against the same catalog IDs and can never drift apart.
 */
class QrCodeDesignSanitizer
{
    public static function sanitize(array $d): array
    {
        $defaults = self::defaultDesign();
        $hex   = fn ($v, $fallback) => is_string($v) && preg_match('/^#[0-9A-Fa-f]{6}$/', $v) ? $v : $fallback;
        $clamp = fn ($v, $min, $max, $fb) => is_numeric($v) ? max($min, min($max, (float) $v)) : $fb;
        $oneOf = fn ($v, array $allowed, $fb) => in_array($v, $allowed, true) ? $v : $fb;

        $dotIds   = QrCodeCatalog::dotIds();
        $outerIds = QrCodeCatalog::outerEyeIds();
        $innerIds = QrCodeCatalog::innerEyeIds();

        return [
            'size'                => (int) $clamp($d['size'] ?? null, 100, 2000, $defaults['size']),
            'margin'              => (int) $clamp($d['margin'] ?? null, 0, 80, $defaults['margin']),
            'error_correction'    => $oneOf($d['error_correction'] ?? null, ['L', 'M', 'Q', 'H'], $defaults['error_correction']),
            'fg_color'            => $hex($d['fg_color']            ?? null, $defaults['fg_color']),
            'bg_color'            => $hex($d['bg_color']            ?? null, $defaults['bg_color']),
            'transparent_bg'      => (bool) ($d['transparent_bg'] ?? false),
            'dot_style'           => $oneOf($d['dot_style']           ?? null, $dotIds,   $defaults['dot_style']),
            'corner_square_style' => $oneOf($d['corner_square_style'] ?? null, $outerIds, $defaults['corner_square_style']),
            'corner_square_color' => $hex($d['corner_square_color'] ?? null, $defaults['corner_square_color']),
            'corner_dot_style'    => $oneOf($d['corner_dot_style']    ?? null, $innerIds, $defaults['corner_dot_style']),
            'corner_dot_color'    => $hex($d['corner_dot_color']    ?? null, $defaults['corner_dot_color']),
            'eyes_per_corner'     => (bool) ($d['eyes_per_corner'] ?? false),
            'eye_corners'         => self::sanitizeEyeCorners($d['eye_corners'] ?? [], $defaults, $hex, $oneOf, $outerIds, $innerIds),
            'qr_rotation'         => (int) $oneOf((int) ($d['qr_rotation'] ?? 0), [0, 90, 180, 270], 0),
            'drop_shadow'         => (bool) ($d['drop_shadow'] ?? false),
            'gradient'            => self::sanitizeGradient($d['gradient'] ?? [], $hex),
            'eye_outer_gradient'  => self::sanitizeGradient($d['eye_outer_gradient'] ?? [], $hex),
            'eye_inner_gradient'  => self::sanitizeGradient($d['eye_inner_gradient'] ?? [], $hex),
            'bg_gradient'         => self::sanitizeGradient($d['bg_gradient'] ?? [], $hex),
            'hide_dots_behind_logo' => (bool) ($d['hide_dots_behind_logo'] ?? true),
            'logo_center'         => self::sanitizeLogo($d['logo_center']     ?? [], $defaults['logo_center'], $clamp),
            'logo_background'     => self::sanitizeLogo($d['logo_background'] ?? [], $defaults['logo_background'], $clamp),
            'logo_foreground'     => self::sanitizeLogo($d['logo_foreground'] ?? [], $defaults['logo_foreground'], $clamp),
            'frame'               => self::sanitizeFrame($d['frame'] ?? [], $defaults['frame'], $hex),
            'ai_art'              => self::sanitizeAiArt($d['ai_art'] ?? []),
        ];
    }

    /**
     * AI Artistic QR metadata. Holds the Replicate-generated artwork URL
     * (a stored UserFile), the prompt/style that produced it, the encoded
     * destination, and the last scannability snapshot. The public renderer
     * uses `image_url` to show the artwork instead of the SVG QR.
     */
    private static function sanitizeAiArt($a): array
    {
        if (!is_array($a)) $a = [];
        $url = is_string($a['image_url'] ?? null) && filter_var($a['image_url'], FILTER_VALIDATE_URL)
            ? mb_substr($a['image_url'], 0, 2000) : null;
        $scan = null;
        if (is_array($a['scan'] ?? null)) {
            $scan = [
                'score' => (int) max(0, min(100, (int) ($a['scan']['score'] ?? 0))),
                'level' => is_string($a['scan']['level'] ?? null) ? mb_substr($a['scan']['level'], 0, 16) : null,
            ];
        }
        return [
            'enabled'   => (bool) ($a['enabled'] ?? false) && $url !== null,
            'image_url' => $url,
            'prompt'    => is_string($a['prompt'] ?? null) ? mb_substr(trim($a['prompt']), 0, 600) : null,
            'style'     => is_string($a['style'] ?? null) ? mb_substr($a['style'], 0, 60) : null,
            'data'      => is_string($a['data'] ?? null) ? mb_substr($a['data'], 0, 2048) : null,
            'scan'      => $scan,
            'provider'  => 'replicate',
        ];
    }

    /**
     * Sanitize the 3 per-corner eye configs (TL, TR, BL). Each corner may
     * override the global outer/inner shape and color. Missing corners fall
     * back to the global design values so the renderer always has a value.
     */
    private static function sanitizeEyeCorners(array $corners, array $defaults, callable $hex, callable $oneOf, array $outerIds, array $innerIds): array
    {
        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $c = is_array($corners[$i] ?? null) ? $corners[$i] : [];
            $out[] = [
                'outer_style' => $oneOf($c['outer_style'] ?? null, $outerIds, $defaults['corner_square_style']),
                'inner_style' => $oneOf($c['inner_style'] ?? null, $innerIds, $defaults['corner_dot_style']),
                'outer_color' => $hex($c['outer_color'] ?? null, $defaults['corner_square_color']),
                'inner_color' => $hex($c['inner_color'] ?? null, $defaults['corner_dot_color']),
            ];
        }
        return $out;
    }

    private static function sanitizeLogo(array $l, array $defaults, callable $clamp): array
    {
        return [
            'url'      => is_string($l['url'] ?? null) && $l['url'] !== '' ? mb_substr($l['url'], 0, 2000) : null,
            'show'     => (bool) ($l['show'] ?? false),
            'size'     => (float) $clamp($l['size']     ?? null, 0.02, 1.0, $defaults['size']),
            'x'        => (float) $clamp($l['x']        ?? null, 0,    100, $defaults['x']),
            'y'        => (float) $clamp($l['y']        ?? null, 0,    100, $defaults['y']),
            'opacity'  => (float) $clamp($l['opacity']  ?? null, 0,    1,   $defaults['opacity']),
            'rotation' => (int)   $clamp($l['rotation'] ?? null, -360, 360, $defaults['rotation']),
        ];
    }

    private static function sanitizeGradient(array $g, callable $hex): array
    {
        return [
            'enabled' => (bool) ($g['enabled'] ?? false),
            'type'    => in_array($g['type'] ?? null, ['linear', 'radial'], true) ? $g['type'] : 'linear',
            'from'    => $hex($g['from'] ?? null, '#000000'),
            'to'      => $hex($g['to']   ?? null, '#5b8def'),
            'angle'   => (int) max(0, min(360, (int) ($g['angle'] ?? 0))),
        ];
    }

    private static function sanitizeFrame(array $f, array $defaults, callable $hex): array
    {
        $frameIds = QrCodeCatalog::frameIds();
        $fonts    = QrCodeCatalog::fonts();
        return [
            'template'   => in_array($f['template'] ?? null, $frameIds, true) ? $f['template'] : 'none',
            'text'       => is_string($f['text'] ?? null) ? mb_substr($f['text'], 0, 60) : 'SCAN ME',
            'font'       => in_array($f['font'] ?? null, $fonts, true) ? $f['font'] : 'Inter',
            'bg_color'   => $hex($f['bg_color']   ?? null, '#000000'),
            'text_color' => $hex($f['text_color'] ?? null, '#ffffff'),
        ];
    }

    public static function defaultDesign(): array
    {
        return [
            'size' => 400, 'margin' => 4, 'error_correction' => 'M',
            'fg_color' => '#071437', 'bg_color' => '#ffffff', 'transparent_bg' => false,
            'dot_style' => 'rounded',
            'corner_square_style' => 'extra-rounded', 'corner_square_color' => '#071437',
            'corner_dot_style' => 'dot',              'corner_dot_color' => '#071437',
            'eyes_per_corner' => false,
            'eye_corners' => array_fill(0, 3, [
                'outer_style' => 'extra-rounded', 'inner_style' => 'dot',
                'outer_color' => '#071437', 'inner_color' => '#071437',
            ]),
            'qr_rotation' => 0, 'drop_shadow' => false,
            'gradient'            => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'eye_outer_gradient'  => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'eye_inner_gradient'  => ['enabled' => false, 'type' => 'linear', 'from' => '#071437', 'to' => '#5b8def', 'angle' => 45],
            'bg_gradient'         => ['enabled' => false, 'type' => 'linear', 'from' => '#ffffff', 'to' => '#e2e8f0', 'angle' => 180],
            'hide_dots_behind_logo' => true,
            'logo_center'     => ['url' => null, 'show' => false, 'size' => 0.25, 'x' => 50, 'y' => 50, 'opacity' => 1.0, 'rotation' => 0],
            'logo_background' => ['url' => null, 'show' => false, 'size' => 1.0,  'x' => 50, 'y' => 50, 'opacity' => 0.3, 'rotation' => 0],
            'logo_foreground' => ['url' => null, 'show' => false, 'size' => 0.2,  'x' => 80, 'y' => 80, 'opacity' => 1.0, 'rotation' => 0],
            'frame' => [
                'template' => 'none', 'text' => 'SCAN ME', 'font' => 'Inter',
                'bg_color' => '#071437', 'text_color' => '#ffffff',
            ],
            'ai_art' => [
                'enabled' => false, 'image_url' => null, 'prompt' => null,
                'style' => null, 'data' => null, 'scan' => null, 'provider' => 'replicate',
            ],
        ];
    }
}
