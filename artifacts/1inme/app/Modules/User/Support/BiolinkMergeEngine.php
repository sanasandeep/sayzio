<?php

namespace App\Modules\User\Support;

/**
 * Deterministic mail-merge engine for biolink page blueprints.
 *
 * A "blueprint" is a page snapshot of the same shape TemplateService produces:
 * `['biolink' => [...], 'blocks' => [...]]`. Authors embed `{{token}}`
 * placeholders anywhere inside string values (block copy, links, image URLs,
 * theme strings). This engine scans those tokens and substitutes per-row
 * values into a fresh copy of the snapshot. There is NO AI — substitution is
 * a pure, predictable string replace.
 *
 * Token syntax: `{{ name }}` where name is letters/numbers/underscore/dash/dot.
 * Matching is case-insensitive; names are normalized to lowercase.
 */
class BiolinkMergeEngine
{
    /** Placeholder pattern — tolerant of surrounding whitespace. */
    private const TOKEN_RE = '/\{\{\s*([A-Za-z0-9_.\-]+)\s*\}\}/';

    /**
     * Unique, lowercased list of `{{token}}` names referenced anywhere in the
     * snapshot's string values (recursively). Order is stable (first-seen).
     *
     * @return string[]
     */
    public static function extractTokens(array $snapshot): array
    {
        $tokens = [];
        self::walkStrings($snapshot, function (string $s) use (&$tokens) {
            if (preg_match_all(self::TOKEN_RE, $s, $m)) {
                foreach ($m[1] as $t) {
                    $tokens[strtolower(trim($t))] = true;
                }
            }
        });
        return array_keys($tokens);
    }

    /**
     * Return a deep copy of $snapshot with every `{{token}}` replaced by the
     * matching value from $values (keys matched case-insensitively). Unknown
     * tokens collapse to an empty string so a missing column never leaks the
     * literal placeholder onto the public page.
     */
    public static function substitute(array $snapshot, array $values): array
    {
        $norm = [];
        foreach ($values as $k => $v) {
            $norm[strtolower(trim((string) $k))] = (is_scalar($v) || $v === null) ? (string) $v : '';
        }

        return self::mapStrings($snapshot, function (string $s) use ($norm) {
            return preg_replace_callback(self::TOKEN_RE, function ($mm) use ($norm) {
                $key = strtolower(trim($mm[1]));
                return array_key_exists($key, $norm) ? $norm[$key] : '';
            }, $s);
        });
    }

    /**
     * Unique list of block `type` slugs used by the snapshot, including the
     * children of container blocks. Used to plan-gate the blueprint once
     * (rather than per row) before any pages are created.
     *
     * @return string[]
     */
    public static function collectBlockTypes(array $snapshot): array
    {
        $types = [];
        $walk = function ($blocks) use (&$types, &$walk) {
            foreach ((array) $blocks as $b) {
                if (!is_array($b)) {
                    continue;
                }
                if (!empty($b['type'])) {
                    $types[(string) $b['type']] = true;
                }
                if (!empty($b['children'])) {
                    $walk($b['children']);
                }
            }
        };
        $walk($snapshot['blocks'] ?? []);
        return array_keys($types);
    }

    /** Recursively invoke $fn on every string scalar inside $node. */
    private static function walkStrings($node, callable $fn): void
    {
        if (is_string($node)) {
            $fn($node);
            return;
        }
        if (is_array($node)) {
            foreach ($node as $v) {
                self::walkStrings($v, $fn);
            }
        }
    }

    /**
     * Recursively rebuild $node, mapping every string scalar through $fn and
     * leaving non-string scalars (ints/bools/null) untouched.
     */
    private static function mapStrings($node, callable $fn)
    {
        if (is_string($node)) {
            return $fn($node);
        }
        if (is_array($node)) {
            $out = [];
            foreach ($node as $k => $v) {
                $out[$k] = self::mapStrings($v, $fn);
            }
            return $out;
        }
        return $node;
    }
}
