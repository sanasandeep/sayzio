<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;

/**
 * Validates a page/card template snapshot's block types and baked
 * design-variant keys *before* it is saved — the same two classes of
 * silent-degradation bug that PageTemplateSeedDesignValidityTest guards
 * the seeders against, but applied to admin-authored snapshots:
 *
 *   1. A baked `settings._style._variant` that no longer resolves via
 *      {@see BlockVariantCatalog::find()} for the block's type. When a
 *      key doesn't resolve the renderer silently falls back to default
 *      styling with no error, stripping the intended look.
 *   2. A block `type` that isn't a real, known type (a typo or a removed
 *      type) — it would render as a blank/unknown block on the public
 *      page.
 *
 * Reuses the exact resolution logic the seeder test relies on:
 * `BlockVariantCatalog::find()` plus the real type set
 * (`BiolinkBlock::TYPES` + `BlockTypeRegistry::newTypes()`).
 */
class TemplateSnapshotValidator
{
    /**
     * Return a list of human-readable issues with a snapshot's designs.
     * An empty array means the snapshot is valid.
     *
     * @param  array<string,mixed>  $snapshot
     * @param  string  $kind  'page' or 'card'
     * @return array<int,string>
     */
    public static function issues(array $snapshot, string $kind = 'page'): array
    {
        // A card snapshot is a single block at the root (with children);
        // a page snapshot carries a `blocks` array.
        $blocks = $kind === 'card'
            ? [$snapshot]
            : ($snapshot['blocks'] ?? []);

        if (!is_array($blocks)) {
            return [];
        }

        $valid = self::validBlockTypes();
        $issues = [];

        foreach (self::flatten($blocks) as $block) {
            $type = $block['type'] ?? null;
            if (!is_string($type) || $type === '') {
                $issues[] = 'A block is missing its type.';
                continue;
            }

            if (!in_array($type, $valid, true)) {
                $issues[] = "Unknown block type \"{$type}\".";
                continue;
            }

            $variant = $block['settings']['_style']['_variant'] ?? null;
            if (is_string($variant) && $variant !== ''
                && BlockVariantCatalog::find($type, $variant) === null) {
                $issues[] = "Design variant \"{$variant}\" does not resolve for "
                    . "block type \"{$type}\" and would silently fall back to "
                    . 'default styling.';
            }
        }

        // Placement-aware render-gap check: a known type still renders blank if
        // it lacks a renderer branch in the placement (page-root vs container-
        // child) it occupies. The two renderers cover different type sets, so a
        // valid type alone does NOT guarantee it renders where it is used.
        foreach (BlockRenderCoverage::renderGaps($blocks) as $gap) {
            $issues[] = $gap;
        }

        return array_values(array_unique($issues));
    }

    /**
     * Return a copy of the snapshot with every baked design-variant key
     * (`settings._style._variant`) that no longer resolves for its block
     * type removed. This is the surgical "strip the offending stale
     * variant keys" repair: it leaves all other styling/content intact
     * and only unsets keys that {@see issues()} would flag as silently
     * falling back to default styling.
     *
     * It cannot fix unknown block types (those need a re-capture), so
     * callers should re-run {@see issues()} afterwards to confirm.
     *
     * @param  array<string,mixed>  $snapshot
     * @param  string  $kind  'page' or 'card'
     * @return array<string,mixed>
     */
    public static function stripStaleVariants(array $snapshot, string $kind = 'page'): array
    {
        if ($kind === 'card') {
            return self::stripBlock($snapshot);
        }

        if (isset($snapshot['blocks']) && is_array($snapshot['blocks'])) {
            $snapshot['blocks'] = array_map(
                fn($block) => is_array($block) ? self::stripBlock($block) : $block,
                $snapshot['blocks']
            );
        }

        return $snapshot;
    }

    /**
     * Strip a single block's stale variant key (and recurse into its
     * children), returning the cleaned block.
     *
     * @param  array<string,mixed>  $block
     * @return array<string,mixed>
     */
    private static function stripBlock(array $block): array
    {
        $type = $block['type'] ?? null;
        $variant = $block['settings']['_style']['_variant'] ?? null;

        if (is_string($type) && is_string($variant) && $variant !== ''
            && BlockVariantCatalog::find($type, $variant) === null) {
            unset($block['settings']['_style']['_variant']);
        }

        if (!empty($block['children']) && is_array($block['children'])) {
            $block['children'] = array_map(
                fn($child) => is_array($child) ? self::stripBlock($child) : $child,
                $block['children']
            );
        }

        return $block;
    }

    /**
     * Flatten a block tree (containers carry `children`) into a flat list.
     *
     * @param  array<int,mixed>  $blocks
     * @return array<int,array<string,mixed>>
     */
    private static function flatten(array $blocks): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $flat[] = $block;
            if (!empty($block['children']) && is_array($block['children'])) {
                $flat = array_merge($flat, self::flatten($block['children']));
            }
        }
        return $flat;
    }

    /**
     * Full set of real block types: model TYPES (incl. back-compat
     * aliases the seeders legitimately use, e.g. link_big / profile_card_v1)
     * plus the registry's new types.
     *
     * @return array<int,string>
     */
    private static function validBlockTypes(): array
    {
        static $types = null;
        if ($types === null) {
            $types = array_merge(
                array_keys(BiolinkBlock::TYPES),
                array_keys(BlockTypeRegistry::newTypes())
            );
        }
        return $types;
    }
}
