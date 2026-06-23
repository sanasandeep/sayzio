<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;
use RuntimeException;

/**
 * Knows which block types actually have a renderer branch in each of the two
 * independent biolink rendering placements, so placement-specific blank-render
 * gaps can be detected *before* a template/snapshot ships instead of silently.
 *
 * There are TWO renderers with different type coverage:
 *
 *   1. TOP-LEVEL — the inline @if/@elseif chain in
 *      resources/views/common/biolink.blade.php renders blocks that sit at the
 *      root of a page. The chain ends in a bare @endif with NO else fallback,
 *      so a top-level block whose type has no branch renders as *nothing* — a
 *      silent blank.
 *
 *   2. CARD-CHILD — the $__blockPartials dispatch table in
 *      resources/views/common/partials/biolink-block-render.blade.php renders
 *      the children of a container (card / grid / grid_auto) block. A child
 *      whose type isn't in the table falls through to a generic "unknown block"
 *      placeholder instead of its real content.
 *
 * A type listed in BiolinkBlock::TYPES is NOT guaranteed to render in both (or
 * either) placement — e.g. buy_me_coffee is child-only, while image_slider /
 * one_time_offer are top-level-only. This class derives coverage by reading the
 * actual blade renderers rather than a hand-maintained list, so the coverage it
 * reports can never silently drift from what really renders.
 */
class BlockRenderCoverage
{
    /** @var array{literals:array<string,bool>,prefixes:array<int,string>,containers:bool}|null */
    private static ?array $topLevel = null;

    /** @var array<string,bool>|null */
    private static ?array $child = null;

    /**
     * True if a block of this type has a dedicated branch in the top-level
     * inline renderer (page-root placement).
     */
    public static function rendersTopLevel(string $type): bool
    {
        $cov = self::topLevelCoverage();

        if (isset($cov['literals'][$type])) {
            return true;
        }

        foreach ($cov['prefixes'] as $prefix) {
            if (str_starts_with($type, $prefix)) {
                return true;
            }
        }

        return $cov['containers'] && BiolinkBlock::isContainerType($type);
    }

    /**
     * True if a block of this type has a partial in the card-child dispatch
     * table (container-child placement).
     */
    public static function rendersAsChild(string $type): bool
    {
        return isset(self::childCoverage()[$type]);
    }

    /**
     * Walk a snapshot's block tree and return human-readable render-gap issues:
     * any *known* block type that has no renderer branch in the placement it
     * actually occupies. Root blocks are top-level; anything inside a `children`
     * array is a container child.
     *
     * Unknown types are intentionally NOT reported here (they're a different
     * class of bug reported separately by the callers' type check), so this only
     * surfaces real types that would silently render blank.
     *
     * @param  array<int,mixed>  $blocks  root-level blocks (top-level placement)
     * @return array<int,string>
     */
    public static function renderGaps(array $blocks): array
    {
        $issues = [];
        self::walk($blocks, false, $issues);

        return array_values(array_unique($issues));
    }

    /**
     * Placement check for a FLAT list of persisted block rows (the shape the
     * DB hands back), as opposed to the nested tree {@see renderGaps()} takes.
     * Each row needs `id`, `type` and `parent_id`; placement is derived from
     * the parent — a row whose parent is a container block (card/grid/
     * grid_auto) is a card-child, everything else is top-level. This is what
     * lets the same blank-render gap be detected in live, user-built pages,
     * not just templates.
     *
     * Returns one descriptor per gap-affected row:
     * `{id, type, placement: 'child'|'top-level', message}`. Unknown/missing
     * types are skipped (a different, separately-reported class of bug), so
     * this only surfaces real types that would silently render blank.
     *
     * @param  array<int,array{id?:mixed,type?:mixed,parent_id?:mixed}>  $rows
     * @return array<int,array{id:mixed,type:string,placement:string,message:string}>
     */
    public static function flatRowGaps(array $rows): array
    {
        $typeById = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $typeById[$row['id']] = is_string($row['type'] ?? null) ? $row['type'] : null;
        }

        $known = self::knownTypes();
        $issues = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = $row['type'] ?? null;
            if (! is_string($type) || $type === '' || ! isset($known[$type])) {
                continue;
            }

            $parentId = $row['parent_id'] ?? null;
            $parentType = ($parentId !== null && array_key_exists($parentId, $typeById))
                ? $typeById[$parentId]
                : null;
            $isChild = $parentId !== null && BiolinkBlock::isContainerType($parentType);

            if ($isChild && ! self::rendersAsChild($type)) {
                $issues[] = [
                    'id' => $row['id'] ?? null,
                    'type' => $type,
                    'placement' => 'child',
                    'message' => 'is inside a container but has no card-child renderer, so it renders '
                        . 'as a generic placeholder instead of its real content.',
                ];
            } elseif (! $isChild && ! self::rendersTopLevel($type)) {
                $issues[] = [
                    'id' => $row['id'] ?? null,
                    'type' => $type,
                    'placement' => 'top-level',
                    'message' => 'is at the page root but has no top-level renderer branch, '
                        . 'so it renders blank.',
                ];
            }
        }

        return $issues;
    }

    /**
     * @param  array<int,mixed>  $blocks
     * @param  array<int,string>  $issues
     */
    private static function walk(array $blocks, bool $isChild, array &$issues): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if (is_string($type) && $type !== '' && isset(self::knownTypes()[$type])) {
                if ($isChild && ! self::rendersAsChild($type)) {
                    $issues[] = "Block type \"{$type}\" is used inside a container but has no "
                        . 'card-child renderer, so it would render as a generic placeholder '
                        . 'instead of its real content. Add it to the $__blockPartials dispatch '
                        . 'table in common/partials/biolink-block-render.blade.php.';
                } elseif (! $isChild && ! self::rendersTopLevel($type)) {
                    $issues[] = "Block type \"{$type}\" is used at the page root but has no "
                        . 'top-level renderer branch, so it would render blank. Add an @elseif '
                        . 'branch for it in common/biolink.blade.php.';
                }
            }

            if (! empty($block['children']) && is_array($block['children'])) {
                self::walk($block['children'], true, $issues);
            }
        }
    }

    /**
     * Parse the top-level inline renderer for every type literal compared
     * against $block->type, every str_starts_with() prefix rule, and whether an
     * isContainerType() branch exists.
     *
     * @return array{literals:array<string,bool>,prefixes:array<int,string>,containers:bool}
     */
    private static function topLevelCoverage(): array
    {
        if (self::$topLevel !== null) {
            return self::$topLevel;
        }

        $literals = [];
        $prefixes = [];
        $containers = false;

        $source = self::readView('common/biolink.blade.php');

        foreach (preg_split('/\R/', $source) as $line) {
            // Only branch directives that switch on the block type matter; this
            // skips styling helpers like `$skipWrap = in_array($block->type, ...)`.
            if (! str_contains($line, '$block->type')) {
                continue;
            }
            if (! str_contains($line, '@if(') && ! str_contains($line, '@elseif(')) {
                continue;
            }

            // $block->type === 'x' / == 'x'  (and the reversed 'x' === $block->type)
            if (preg_match_all('/\$block->type\s*===?\s*\'([^\']+)\'/', $line, $m)) {
                foreach ($m[1] as $t) {
                    $literals[$t] = true;
                }
            }
            if (preg_match_all('/\'([^\']+)\'\s*===?\s*\$block->type/', $line, $m)) {
                foreach ($m[1] as $t) {
                    $literals[$t] = true;
                }
            }

            // in_array($block->type, ['x', 'y', ...])
            if (preg_match_all('/in_array\(\s*\$block->type\s*,\s*\[([^\]]*)\]/', $line, $m)) {
                foreach ($m[1] as $arr) {
                    if (preg_match_all('/\'([^\']+)\'/', $arr, $mm)) {
                        foreach ($mm[1] as $t) {
                            $literals[$t] = true;
                        }
                    }
                }
            }

            // str_starts_with($block->type, 'prefix')
            if (preg_match_all('/str_starts_with\(\s*\$block->type\s*,\s*\'([^\']+)\'/', $line, $m)) {
                foreach ($m[1] as $p) {
                    $prefixes[] = $p;
                }
            }

            // isContainerType($block->type) — covers every CONTAINER_TYPES entry
            if (str_contains($line, 'isContainerType(')) {
                $containers = true;
            }
        }

        return self::$topLevel = [
            'literals' => $literals,
            'prefixes' => array_values(array_unique($prefixes)),
            'containers' => $containers,
        ];
    }

    /**
     * Parse the card-child dispatch table's $__blockPartials array keys.
     *
     * @return array<string,bool>
     */
    private static function childCoverage(): array
    {
        if (self::$child !== null) {
            return self::$child;
        }

        $types = [];
        $source = self::readView('common/partials/biolink-block-render.blade.php');

        if (preg_match('/\$__blockPartials\s*=\s*\[(.*?)\];/s', $source, $m)) {
            // Keys sit before `=>`; values (partial paths) sit after and are ignored.
            if (preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $m[1], $mm)) {
                foreach ($mm[1] as $t) {
                    $types[$t] = true;
                }
            }
        }

        return self::$child = $types;
    }

    /**
     * Full set of real block types (model TYPES + registry new types), used to
     * limit render-gap reporting to genuine types.
     *
     * @return array<string,bool>
     */
    private static function knownTypes(): array
    {
        static $types = null;
        if ($types === null) {
            $types = array_fill_keys(array_merge(
                array_keys(BiolinkBlock::TYPES),
                array_keys(BlockTypeRegistry::newTypes())
            ), true);
        }

        return $types;
    }

    /**
     * Read a blade view by its path relative to resources/views, resolving the
     * location from this file so it works without a booted application (the
     * snapshot validator runs in pure PHPUnit unit tests).
     */
    private static function readView(string $relative): string
    {
        $base = realpath(__DIR__ . '/../../../../resources/views')
            ?: (__DIR__ . '/../../../../resources/views');
        $path = $base . '/' . ltrim($relative, '/');

        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new RuntimeException("BlockRenderCoverage: cannot read view '{$relative}' at '{$path}'.");
        }

        return $contents;
    }
}
