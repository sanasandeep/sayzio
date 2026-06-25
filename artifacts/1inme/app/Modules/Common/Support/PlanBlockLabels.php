<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Support\Str;

/**
 * Shared resolution for a plan's `block_types_allowed` feature value into
 * human-readable biolink-block labels. Used by both the public pricing page
 * and the in-app upgrade page so the two surfaces stay in sync.
 */
class PlanBlockLabels
{
    /**
     * Resolve a biolink block-type slug to a human-readable label.
     * Friendly names come from the live block registry (the full TYPES map,
     * including consolidated "_alias" entries like `paragraph`), so allowlists
     * normalized to real types read straight from the registry. Any genuinely
     * unknown slug degrades to a humanized version rather than a raw token.
     *
     * @param  array<string, mixed>|null  $types  Optional pre-fetched type map (avoids re-fetching in loops).
     */
    public static function label(string $slug, ?array $types = null): string
    {
        $types ??= BiolinkBlock::TYPES;
        $label = $types[$slug]['label'] ?? null;
        if ($label) {
            return $label;
        }
        $clean = preg_replace('/_v\d+$/', '', $slug);

        return Str::title(str_replace(['_', '-'], ' ', $clean));
    }

    /**
     * Resolve a plan's `block_types_allowed` feature value into a sorted,
     * de-duplicated list of human-readable labels.
     *
     * Returns null when every block is allowed (`*`) or when the value is
     * empty / not a list — callers should branch on {@see self::isAll()}
     * first to distinguish "all blocks" from "no blocks".
     *
     * Friendly / legacy allowlist slugs are canonicalized to real block types
     * first ({@see BlockTypeRegistry::canonicalizeAllowlist()}), so the labels
     * shown match exactly what editor gating enforces.
     *
     * @return list<string>|null
     */
    public static function labelsFor($value): ?array
    {
        if (!is_array($value) || count($value) === 0) {
            return null;
        }

        $value = BlockTypeRegistry::canonicalizeAllowlist($value);
        $types = BiolinkBlock::TYPES;
        $labels = [];
        foreach ($value as $slug) {
            if (!is_string($slug) || $slug === '') {
                continue;
            }
            $labels[self::label($slug, $types)] = true;
        }
        $labels = array_keys($labels);
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        return count($labels) ? $labels : null;
    }

    /**
     * Whether the feature value grants every biolink block type.
     */
    public static function isAll($value): bool
    {
        return $value === '*';
    }
}
