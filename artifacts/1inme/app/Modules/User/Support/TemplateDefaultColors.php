<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Link;

/**
 * Template default colors (Task #6039). Stored on the biolink settings of
 * a template draft (`settings.biolink.template_default_colors`) and
 * carried into pages created from the template via the snapshot. Shared
 * by the web editor and the mobile REST API so blocks added from either
 * client are seeded identically (Task #6042).
 */
class TemplateDefaultColors
{
    /** Only these keys are recognised. */
    public const KEYS = [
        'text_color', 'bg_color', 'border_color', 'accent_color', 'accent_text_color',
    ];

    /**
     * Button-like block types where the accent pair (accent background +
     * text-on-accent) replaces the general text/background defaults.
     */
    public const ACCENT_BLOCK_TYPES = ['link', 'link_big', 'cta_button'];

    /**
     * Read the link's template default colors, keeping only known keys with
     * valid hex values. Read-side validation keeps unsanitized snapshot
     * merges harmless.
     */
    public static function colorsFor(Link $link): array
    {
        $raw = $link->settings['biolink']['template_default_colors'] ?? null;
        if (!is_array($raw)) return [];
        $clean = [];
        foreach (self::KEYS as $k) {
            $v = $raw[$k] ?? null;
            if (is_string($v) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }

    /**
     * Map the template default colors onto per-block `_style` keys for a
     * new block of the given type. Empty defaults = inherit (no key set).
     */
    public static function styleFor(Link $link, string $type): array
    {
        $colors = self::colorsFor($link);
        if ($colors === []) return [];
        $style = [];
        foreach (['text_color', 'bg_color', 'border_color'] as $k) {
            if (isset($colors[$k])) $style[$k] = $colors[$k];
        }
        if (in_array($type, self::ACCENT_BLOCK_TYPES, true)) {
            if (isset($colors['accent_color'])) $style['bg_color'] = $colors['accent_color'];
            if (isset($colors['accent_text_color'])) $style['text_color'] = $colors['accent_text_color'];
        }
        return $style;
    }
}
