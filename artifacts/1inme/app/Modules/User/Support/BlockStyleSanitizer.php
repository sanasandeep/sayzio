<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Single source of truth for validating a block `_style` payload.
 *
 * Extracted from BiolinkBlockController::sanitizeBlockStyle() (Task #6045)
 * so both the user editor (form save / variant apply) and the admin
 * Block Designs manager sanitize style payloads through the exact same
 * allowlist — admin-entered variant/template payloads can never drift
 * from what the editor itself would accept.
 *
 * Every branch is fail-closed: unknown keys, out-of-range numbers and
 * malformed colors are silently dropped, never errored.
 */
class BlockStyleSanitizer
{
    /**
     * Allowed `link_layout` values (Task #6054: shared with the admin
     * Block Designs visual style editor so its dropdown can never
     * drift from what sanitize() accepts). Empty string = default
     * button render and is never persisted.
     */
    public const LINK_LAYOUTS = [
        'plain_text', 'image_cover', 'action_row', 'text_divider',
        'icon_left', 'icon_right', 'icon_both', 'icon_only',
        'icon_circle_left', 'icon_circle_right', 'icon_box',
        'image_left', 'image_right', 'image_top',
        'image_overhang_top', 'image_overhang_left',
        'image_icon_rounded', 'image_icon_square', 'image_icon_circle',
        'title_desc_row', 'image_cover_square',
        'taped_note',
        'arrow_hex', 'arrow_hex_round', 'numbered_list', 'side_accent_tab',
        'icon_top', 'offset_frame', 'torn_tape',
        'arrow_chip_left',
        // Task #6588 — screenshot-inspired styles.
        'edge_bleed_bar', 'double_border',
        // Task #6602 — four more screenshot-inspired styles.
        'sparkle_pill', 'notched_bar', 'speech_bubble', 'riveted_plaque',
    ];

    public static function sanitize(array $input): array
    {
        $enums = [
            'font_style' => ['normal', 'italic'],
            'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            // Per-side border styles (Task #6038) — same vocabulary as the
            // shorthand; empty = fall back to the shorthand at render time.
            'border_top_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'border_right_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'border_bottom_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'border_left_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'shadow_type' => ['none', 'soft', 'hard', 'neon', 'glow', 'neumorphic', 'inset'],
            'shadow_preset' => ['none', 'soft', 'medium', 'strong'],
            'glass_preset' => ['off', 'light', 'heavy'],
            'display_mode' => ['card', 'content'],
            'effect' => ['none', 'glass', 'gradient_border'],
            // Per-block layout switch for link-family blocks. Empty
            // string is the default (existing button render); since the
            // foreach skips empty values, only non-default picks
            // ('plain_text' / 'image_cover') will ever be persisted —
            // which is exactly what we want.
            'link_layout' => self::LINK_LAYOUTS,
        ];
        $numericBounds = [
            'font_size' => [8, 72],
            'bg_opacity' => [0, 100],
            'border_width' => [0, 10],
            'border_radius' => [0, 999],
            // Advanced borders (Task #6038): per-corner radius + per-side widths.
            'border_radius_tl' => [0, 999],
            'border_radius_tr' => [0, 999],
            'border_radius_bl' => [0, 999],
            'border_radius_br' => [0, 999],
            'border_top_width' => [0, 10],
            'border_right_width' => [0, 10],
            'border_bottom_width' => [0, 10],
            'border_left_width' => [0, 10],
            'shadow_x' => [-50, 50],
            'shadow_y' => [-50, 50],
            'shadow_blur' => [0, 100],
            'shadow_spread' => [-20, 50],
            'glass_blur' => [0, 100],
            'glass_opacity' => [0, 100],
            'padding' => [0, 60],
            'padding_top' => [0, 200],
            'padding_bottom' => [0, 200],
            'padding_left' => [0, 200],
            'padding_right' => [0, 200],
            'margin_top' => [-100, 200],
            'margin_bottom' => [-100, 200],
            'margin_left' => [-100, 200],
            'margin_right' => [-100, 200],
            'grid_span' => [1, 12],
            'stack_mobile' => [0, 1],
            'grid_span_md' => [1, 12],
            'grid_row_span' => [1, 6],
            'grid_row_span_md' => [1, 6],
            // Block/card preset background transparency (Task #5970).
            'bg_preset_opacity' => [0, 100],
        ];
        // Decorative avatar frame for profile cards (Task #5910). Strict
        // enum from the catalog — unknown keys are silently dropped so a
        // bad value can never break the public page.
        $enums['_avatar_frame'] = AvatarFrameCatalog::keys();
        // Hero-photo decorations for image blocks (Task #5922).
        $enums['_photo_mask'] = ['arch', 'torn'];
        $enums['_photo_frame'] = ['concentric_arch'];
        $numericBounds['_photo_frame_strokes'] = [2, 5];
        // Text-block tilt in degrees (Task #5954) — clamped so a tilted
        // heading/paragraph can never rotate off the page.
        $numericBounds['_tilt'] = [BiolinkBlock::TILT_MIN, BiolinkBlock::TILT_MAX];
        // Profile-card cover effects (Task #6585). Blur in px; overlay
        // opacity in percent. Like `_tilt`, the editor's range inputs
        // always submit a value, so 0 is dropped below instead of being
        // stamped onto every profile-card save.
        $numericBounds['_cover_blur'] = [0, 40];
        $numericBounds['_cover_overlay_opacity'] = [0, 100];
        // Heading shape accents (Task #5938) — strict enums; the shape
        // token list shares the accent branch below with `_photo_accents`.
        $enums['_heading_accent_placement'] = AccentShapeCatalog::HEADING_PLACEMENTS;
        $enums['_heading_accent_size'] = AccentShapeCatalog::HEADING_SIZES;
        $colorKeys = [
            'text_color', 'bg_color', 'border_color', 'shadow_color', '_avatar_frame_color',
            'border_top_color', 'border_right_color', 'border_bottom_color', 'border_left_color',
            '_photo_frame_color', '_photo_banner_bg', '_photo_banner_text_color', '_photo_accent_color',
            '_heading_accent_color', '_cover_overlay_color',
            // Countdown block color overrides (rich countdown redesign).
            '_countdown_digit_color', '_countdown_label_color', '_countdown_box_bg',
            '_countdown_cta_bg', '_countdown_cta_text',
        ];
        $fontWeightKeys = ['font_weight'];
        $fontFamilyKeys = ['font_family'];
        $urlKeys = ['bg_image'];

        $allowed = array_keys(BiolinkBlock::STYLE_DEFAULTS);
        $result = [];
        foreach ($allowed as $key) {
            if (!isset($input[$key]) || $input[$key] === '') continue;
            $val = is_string($input[$key]) ? trim($input[$key]) : $input[$key];

            if (isset($enums[$key])) {
                if (in_array($val, $enums[$key], true)) $result[$key] = $val;
            } elseif (isset($numericBounds[$key])) {
                if (is_numeric($val)) {
                    // 0° tilt means "level" — the range input always submits
                    // a value, so drop the default instead of stamping
                    // `_tilt: 0` onto every heading/paragraph save.
                    if (in_array($key, ['_tilt', '_cover_blur', '_cover_overlay_opacity'], true) && (float) $val === 0.0) continue;
                    $result[$key] = max($numericBounds[$key][0], min($numericBounds[$key][1], (float) $val));
                }
            } elseif (in_array($key, $colorKeys, true)) {
                if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $val)) {
                    $result[$key] = $val;
                } elseif ($key === 'bg_color' && is_string($val) && strlen($val) <= 500
                    && preg_match('/^(linear|radial|conic)-gradient\([^;{}<>"\'`]+\)$/i', $val)
                ) {
                    // Task #1041: allow CSS gradients on `bg_color` so curated
                    // cover/profile variants (e.g. cover_aurora) round-trip.
                    // We forbid `;{}<>"\'\`` so the value can never break out
                    // of the inline style attribute it ends up in.
                    $result[$key] = $val;
                }
            } elseif (in_array($key, $fontWeightKeys, true)) {
                if (preg_match('/^(300|400|500|600|700|800|900)$/', (string) $val)) {
                    $result[$key] = (string) $val;
                }
            } elseif (in_array($key, $fontFamilyKeys, true)) {
                // Allow Google Font names plus a "custom:<family>" prefix for
                // user-uploaded fonts. The colon is the only structural
                // delimiter we accept; anything else (quotes, parens, semis)
                // would be unsafe inside a CSS font-family declaration.
                $safe = preg_replace('/[^a-zA-Z0-9 :_\-]/', '', substr((string) $val, 0, 80));
                if ($safe !== '') $result[$key] = trim($safe);
            } elseif (in_array($key, $urlKeys, true)) {
                // Task #6044: accept absolute http(s) URLs OR relative /f/
                // vault paths so vault-hosted images work as block
                // backgrounds without an absolute host prefix.
                if (filter_var($val, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $val)) {
                    $result[$key] = substr($val, 0, 500);
                } elseif (is_string($val) && preg_match('#^/f/[A-Za-z0-9._/\-]+$#', $val)) {
                    $result[$key] = substr($val, 0, 500);
                }
            } elseif ($key === 'bg_preset_key') {
                // Catalog preset background for blocks & card containers
                // (Task #5970). Only real, non-torn catalog keys persist —
                // torn composites need full-page layers and are excluded at
                // block level. Unknown keys are silently dropped so a bad
                // value can never emit broken CSS on the public page.
                $safe = preg_replace('/[^a-z0-9_]/', '', substr((string) $val, 0, 60));
                if ($safe !== ''
                    && BgPresetCatalog::findByKey($safe)
                    && !BgPresetCatalog::isTorn($safe)
                ) {
                    $result[$key] = $safe;
                }
            } elseif ($key === '_template') {
                // Merged catalog (Task #6045): built-in BLOCK_TEMPLATES plus
                // enabled admin-defined theme presets.
                $validTemplates = array_keys(BiolinkBlock::blockTemplates(true));
                if (in_array($val, $validTemplates, true)) {
                    $result[$key] = $val;
                }
            } elseif ($key === '_variant') {
                // Variant key is opaque; we accept any short slug-shaped
                // string. If the catalog later drops it, the renderer just
                // falls back to whatever's in _style. This keeps old pages
                // visually stable across catalog versions.
                $safe = preg_replace('/[^a-z0-9_\-]/i', '', substr((string) $val, 0, 60));
                if ($safe !== '') $result[$key] = $safe;
            } elseif ($key === '_variant_version') {
                $n = (int) $val;
                if ($n >= 0 && $n < 100000) $result[$key] = $n;
            } elseif ($key === '_photo_banner_text') {
                // Plain-text banner label — strip tags, collapse whitespace,
                // hard cap the length. Rendered through Blade's {{ }} so it
                // is escaped again on output.
                $safe = trim(preg_replace('/\s+/', ' ', strip_tags((string) $val)) ?? '');
                if ($safe !== '') $result[$key] = mb_substr($safe, 0, 60);
            } elseif (in_array($key, ['_photo_accents', '_heading_accents'], true)) {
                // Comma-separated accent-shape tokens; unknown tokens are
                // dropped, order preserved, duplicates removed. Allowlist
                // comes from the shared AccentShapeCatalog.
                $raw = is_array($val) ? implode(',', array_map('strval', $val)) : (string) $val;
                $tokens = AccentShapeCatalog::parseTokens($raw);
                if (!empty($tokens)) $result[$key] = implode(',', $tokens);
            } elseif ($key === '_photo_stickers') {
                // Custom sticker overlays (Task #5939). The editor submits a
                // JSON string; templates/variants may carry a plain array.
                // Every entry must reference an image file OWNED by the
                // current workspace owner — foreign/missing/non-image refs
                // fail closed (the entry is dropped, never an error). The
                // public `url` is re-derived server-side from the file row
                // so a tampered client URL can never be persisted.
                $clean = PhotoStickerSanitizer::sanitize($val);
                if ($clean !== []) $result[$key] = $clean;
            } elseif ($key === '_photo_text_stickers') {
                // Text overlays on image blocks (Task #5954). JSON string
                // from the editor or plain array from templates/variants;
                // every field is validated + clamped, invalid entries are
                // dropped silently.
                $clean = self::sanitizePhotoTextStickers($val);
                if ($clean !== []) $result[$key] = $clean;
            } elseif (in_array($key, ['_animation', '_gallery_layout', '_social_set', '_profile_layout', '_window_chrome', '_ltg_layout', '_ltg_align'], true)) {
                // Opaque slug-shaped variant metadata hooks (Task #1041).
                // The renderer is free to ignore unknown values; we only
                // bound the character set + length so they're safe to
                // emit as CSS class suffixes / data attributes later.
                $safe = preg_replace('/[^a-z0-9_\-]/i', '', substr((string) $val, 0, 40));
                if ($safe !== '') $result[$key] = $safe;
            }
        }
        return $result;
    }

    /**
     * Task #5954 — validate text overlay entries for image blocks.
     * Accepts a JSON string (editor hidden input) or an array
     * (templates/variants). Text is plain-text only (tags stripped,
     * length capped); fonts pass the same character allowlist as
     * per-block `font_family`; colors must match the strict color
     * regex; every numeric field is clamped. Invalid entries are
     * dropped silently — the sanitizer never errors.
     */
    public static function sanitizePhotoTextStickers(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($list) || $list === []) return [];

        $clean = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) continue;

            $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($entry['text'] ?? ''))) ?? '');
            if ($text === '') continue; // text is the one required field
            $text = mb_substr($text, 0, 80);

            $font = '';
            if (!empty($entry['font'])) {
                // Same allowlist as font_family: letters/digits/spaces plus
                // the "custom:" prefix delimiter — safe inside a CSS
                // font-family declaration.
                $font = trim((string) preg_replace('/[^a-zA-Z0-9 :_\-]/', '', substr((string) $entry['font'], 0, 80)));
            }

            $color = (string) ($entry['color'] ?? '');
            if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#ffffff';

            $pos = (string) ($entry['pos'] ?? 'top_right');
            if (!in_array($pos, BiolinkBlock::PHOTO_STICKER_POSITIONS, true)) $pos = 'top_right';

            $clean[] = [
                'text'   => $text,
                'font'   => $font,
                'color'  => $color,
                'pos'    => $pos,
                'size'   => max(10, min(64, (int) ($entry['size'] ?? 20))),
                'rotate' => max(-180, min(180, (int) ($entry['rotate'] ?? 0))),
                'dx'     => max(-80, min(80, (int) ($entry['dx'] ?? 0))),
                'dy'     => max(-80, min(80, (int) ($entry['dy'] ?? 0))),
            ];
            if (count($clean) >= BiolinkBlock::PHOTO_TEXT_STICKER_MAX) break;
        }

        return $clean;
    }
}
