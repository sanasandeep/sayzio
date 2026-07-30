<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Str;

/**
 * Admin-managed additions/overrides to the hardcoded block design
 * catalogs (Task #6045). Stored in `app_settings`, merged at read time:
 *
 * - `block_variants.custom`     — list of admin-created Designs-gallery
 *   variants: { key (adm_*), name, tags[], shape, types[] (empty = all
 *   block types), style{}, preview{}, sort, enabled }.
 * - `block_variants.overrides`  — { variantKey: {hidden: true} } — hides
 *   built-in OR custom variants from the gallery. Built-ins can only be
 *   hidden, never deleted, so already-styled blocks keep rendering.
 * - `block_theme_templates.custom`    — { key (adm_*): {label, icon,
 *   preview_bg, preview_text, style{}, sort, enabled} } merged into
 *   BiolinkBlock::BLOCK_TEMPLATES for the global Block Theme presets.
 * - `block_theme_templates.overrides` — { templateKey: {hidden: true} }.
 * - `block_variants.revision`   — int bumped on every write; folded into
 *   BlockVariantCatalog::version() so edited variants re-apply to
 *   existing blocks without a deploy.
 */
class AdminBlockDesigns
{
    public const VARIANTS_KEY = 'block_variants.custom';
    public const VARIANT_OVERRIDES_KEY = 'block_variants.overrides';
    public const TEMPLATES_KEY = 'block_theme_templates.custom';
    public const TEMPLATE_OVERRIDES_KEY = 'block_theme_templates.overrides';
    public const REVISION_KEY = 'block_variants.revision';

    /** Custom keys are always prefixed so they can never collide with built-ins. */
    public const KEY_PREFIX = 'adm_';

    /* ------------------------------------------------------------------ */
    /* Revision                                                            */
    /* ------------------------------------------------------------------ */

    public static function revision(): int
    {
        return max(0, (int) AppSetting::get(self::REVISION_KEY, 0));
    }

    public static function bumpRevision(): void
    {
        AppSetting::put(self::REVISION_KEY, self::revision() + 1);
    }

    /* ------------------------------------------------------------------ */
    /* Designs-gallery variants                                            */
    /* ------------------------------------------------------------------ */

    /** @return array<int,array> raw custom variant entries, sorted. */
    public static function customVariants(): array
    {
        $raw = AppSetting::get(self::VARIANTS_KEY, []);
        $list = is_array($raw) ? array_values(array_filter($raw, 'is_array')) : [];
        usort($list, fn ($a, $b) => (int) ($a['sort'] ?? 0) <=> (int) ($b['sort'] ?? 0));
        return $list;
    }

    /** Custom variants applicable to a block type (enabled only unless $includeDisabled). */
    public static function customVariantsForType(string $type, bool $includeDisabled = false): array
    {
        $canonical = BlockTypeRegistry::canonical($type);
        $out = [];
        foreach (self::customVariants() as $v) {
            if (!$includeDisabled && empty($v['enabled'])) continue;
            $types = array_values(array_filter((array) ($v['types'] ?? [])));
            if ($types !== [] && !in_array($type, $types, true) && !in_array($canonical, $types, true)) {
                continue;
            }
            $out[] = self::variantForCatalog($v);
        }
        return $out;
    }

    /** Shape a stored entry into the catalog contract used by the gallery. */
    public static function variantForCatalog(array $v): array
    {
        return [
            'key'     => (string) ($v['key'] ?? ''),
            'name'    => (string) ($v['name'] ?? ''),
            'tags'    => array_values(array_filter((array) ($v['tags'] ?? []))),
            'shape'   => (string) ($v['shape'] ?? '') ?: null,
            'style'   => is_array($v['style'] ?? null) ? $v['style'] : [],
            'preview' => is_array($v['preview'] ?? null) ? $v['preview'] : [],
        ];
    }

    public static function findCustomVariant(string $key): ?array
    {
        foreach (self::customVariants() as $v) {
            if (($v['key'] ?? '') === $key) return $v;
        }
        return null;
    }

    /**
     * Create or update a custom variant. The style payload MUST already be
     * sanitized (BlockStyleSanitizer::sanitize) by the caller. Returns the
     * persisted entry (a fresh adm_* key is minted on create).
     */
    public static function saveVariant(array $entry): array
    {
        $list = self::customVariants();
        $key = (string) ($entry['key'] ?? '');
        if ($key === '' || !str_starts_with($key, self::KEY_PREFIX)) {
            $key = self::KEY_PREFIX . Str::lower(Str::random(10));
        }
        $style = is_array($entry['style'] ?? null) ? $entry['style'] : [];
        $clean = [
            'key'     => $key,
            'name'    => mb_substr(trim((string) ($entry['name'] ?? '')), 0, 60),
            'tags'    => array_values(array_intersect(
                array_map('strval', (array) ($entry['tags'] ?? [])),
                array_keys(BlockVariantCatalog::TAGS)
            )),
            'shape'   => in_array($entry['shape'] ?? '', BlockVariantCatalog::SHAPES, true)
                ? (string) $entry['shape'] : '',
            'types'   => array_values(array_filter(array_map(
                fn ($t) => preg_replace('/[^a-z0-9_]/', '', (string) $t),
                (array) ($entry['types'] ?? [])
            ))),
            'style'   => $style,
            'preview' => self::derivePreview($style),
            'enabled' => !empty($entry['enabled']),
            'sort'    => 0,
        ];

        $found = false;
        foreach ($list as $i => $v) {
            if (($v['key'] ?? '') === $key) {
                $clean['sort'] = (int) ($v['sort'] ?? $i);
                $list[$i] = $clean;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $clean['sort'] = $list === [] ? 0 : (max(array_map(fn ($v) => (int) ($v['sort'] ?? 0), $list)) + 1);
            $list[] = $clean;
        }

        AppSetting::put(self::VARIANTS_KEY, array_values($list));
        self::bumpRevision();
        return $clean;
    }

    /** Delete a CUSTOM variant. Built-ins are never deletable. */
    public static function deleteVariant(string $key): bool
    {
        if (!str_starts_with($key, self::KEY_PREFIX)) return false;
        $list = self::customVariants();
        $filtered = array_values(array_filter($list, fn ($v) => ($v['key'] ?? '') !== $key));
        if (count($filtered) === count($list)) return false;
        AppSetting::put(self::VARIANTS_KEY, $filtered);
        // Also drop any hidden override for the deleted key.
        $ov = self::variantOverrides();
        if (isset($ov[$key])) {
            unset($ov[$key]);
            AppSetting::put(self::VARIANT_OVERRIDES_KEY, $ov);
        }
        self::bumpRevision();
        return true;
    }

    /** Move a custom variant one slot up or down in the ordered list. */
    public static function moveVariant(string $key, string $direction): void
    {
        $list = self::customVariants();
        foreach ($list as $i => $v) {
            if (($v['key'] ?? '') !== $key) continue;
            $j = $direction === 'up' ? $i - 1 : $i + 1;
            if ($j < 0 || $j >= count($list)) return;
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            foreach ($list as $n => &$entry) $entry['sort'] = $n;
            unset($entry);
            AppSetting::put(self::VARIANTS_KEY, array_values($list));
            self::bumpRevision();
            return;
        }
    }

    /** @return array<string,array{hidden?:bool}> */
    public static function variantOverrides(): array
    {
        $raw = AppSetting::get(self::VARIANT_OVERRIDES_KEY, []);
        return is_array($raw) ? $raw : [];
    }

    /** @return string[] variant keys hidden from the Designs gallery. */
    public static function hiddenVariantKeys(): array
    {
        return array_keys(array_filter(self::variantOverrides(), fn ($o) => !empty($o['hidden'])));
    }

    public static function setVariantHidden(string $key, bool $hidden): void
    {
        $ov = self::variantOverrides();
        if ($hidden) {
            $ov[$key] = ['hidden' => true];
        } else {
            unset($ov[$key]);
        }
        AppSetting::put(self::VARIANT_OVERRIDES_KEY, $ov);
        self::bumpRevision();
    }

    /**
     * Derive the compact preview hints the gallery thumbnails use from a
     * sanitized style payload — same fields built-in variants declare.
     */
    public static function derivePreview(array $style): array
    {
        $preview = [
            'bg'   => (string) ($style['bg_color'] ?? '#1a1a2e') ?: '#1a1a2e',
            'text' => (string) ($style['text_color'] ?? '#ffffff') ?: '#ffffff',
        ];
        if (isset($style['border_radius'])) $preview['radius'] = (int) $style['border_radius'];
        if (!empty($style['border_color']) && ($style['border_style'] ?? 'none') !== 'none') {
            $preview['border'] = (string) $style['border_color'];
            if (($style['border_style'] ?? '') === 'dashed') $preview['dashed'] = true;
        }
        if (($style['shadow_type'] ?? 'none') !== 'none' && !empty($style['shadow_color'])) {
            $x = (int) ($style['shadow_x'] ?? 0);
            $y = (int) ($style['shadow_y'] ?? 4);
            $blur = (int) ($style['shadow_blur'] ?? 12);
            $preview['shadow'] = "{$x}px {$y}px {$blur}px {$style['shadow_color']}";
        }
        $family = (string) ($style['font_family'] ?? '');
        if ($family !== '' && preg_match('/playfair|serif|garamond|lora|merriweather/i', $family)) {
            $preview['serif'] = true;
        }
        return $preview;
    }

    /* ------------------------------------------------------------------ */
    /* Global Block Theme presets                                          */
    /* ------------------------------------------------------------------ */

    /** @return array<string,array> custom template presets keyed by adm_* key. */
    public static function customTemplates(): array
    {
        $raw = AppSetting::get(self::TEMPLATES_KEY, []);
        if (!is_array($raw)) return [];
        $entries = array_filter($raw, 'is_array');
        uasort($entries, fn ($a, $b) => (int) ($a['sort'] ?? 0) <=> (int) ($b['sort'] ?? 0));
        return $entries;
    }

    /**
     * Create or update a custom theme preset. Style must be pre-sanitized.
     * Returns the persisted key.
     */
    public static function saveTemplate(?string $key, array $entry): string
    {
        $all = self::customTemplates();
        if ($key === null || $key === '' || !str_starts_with($key, self::KEY_PREFIX)) {
            $key = self::KEY_PREFIX . Str::lower(Str::random(10));
        }
        $style = is_array($entry['style'] ?? null) ? $entry['style'] : [];
        $icon = preg_replace('/[^a-z0-9\-]/', '', (string) ($entry['icon'] ?? ''));
        $preview = self::derivePreview($style);
        $existing = $all[$key] ?? null;
        $all[$key] = [
            'label'        => mb_substr(trim((string) ($entry['label'] ?? '')), 0, 40),
            'icon'         => $icon !== '' ? (str_starts_with($icon, 'fa-') ? $icon : 'fa-' . $icon) : 'fa-swatchbook',
            'preview_bg'   => $preview['bg'],
            'preview_text' => $preview['text'],
            'style'        => $style,
            'enabled'      => !empty($entry['enabled']),
            'sort'         => $existing !== null
                ? (int) ($existing['sort'] ?? 0)
                : ($all === [] ? 0 : (max(array_map(fn ($t) => (int) ($t['sort'] ?? 0), $all)) + 1)),
        ];
        AppSetting::put(self::TEMPLATES_KEY, $all);
        self::bumpRevision();
        return $key;
    }

    public static function deleteTemplate(string $key): bool
    {
        if (!str_starts_with($key, self::KEY_PREFIX)) return false;
        $all = self::customTemplates();
        if (!isset($all[$key])) return false;
        unset($all[$key]);
        AppSetting::put(self::TEMPLATES_KEY, $all);
        $ov = self::templateOverrides();
        if (isset($ov[$key])) {
            unset($ov[$key]);
            AppSetting::put(self::TEMPLATE_OVERRIDES_KEY, $ov);
        }
        self::bumpRevision();
        return true;
    }

    /** @return array<string,array{hidden?:bool}> */
    public static function templateOverrides(): array
    {
        $raw = AppSetting::get(self::TEMPLATE_OVERRIDES_KEY, []);
        return is_array($raw) ? $raw : [];
    }

    /** @return string[] template keys hidden from the Block Theme picker. */
    public static function hiddenTemplateKeys(): array
    {
        return array_keys(array_filter(self::templateOverrides(), fn ($o) => !empty($o['hidden'])));
    }

    public static function setTemplateHidden(string $key, bool $hidden): void
    {
        $ov = self::templateOverrides();
        if ($hidden) {
            $ov[$key] = ['hidden' => true];
        } else {
            unset($ov[$key]);
        }
        AppSetting::put(self::TEMPLATE_OVERRIDES_KEY, $ov);
        self::bumpRevision();
    }
}
