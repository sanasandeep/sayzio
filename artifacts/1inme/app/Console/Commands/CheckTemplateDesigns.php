<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Services\PersonaCatalog;
use App\Modules\User\Support\BlockRenderCoverage;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\BlockVariantCatalog;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use Illuminate\Console\Command;
use ReflectionMethod;

/**
 * Lightweight, DB-free validator for the page-template seeders' baked designs.
 *
 * Mirrors PageTemplateSeedDesignValidityTest (tests/Feature) so anyone can
 * validate template designs in seconds without booting the heavy PHPUnit suite
 * (each test boots ~16s and cross-region RDS makes migrate:fresh impractical).
 *
 * It catches three classes of silently-degrading bug:
 *   1. A baked design-variant key that no longer resolves via
 *      BlockVariantCatalog::find() for the block type it's applied to. When a
 *      key doesn't resolve the seeder falls back to default styling with NO
 *      error, so a stale key silently strips the look.
 *   2. A block type used in a seeded snapshot that isn't a real, known block
 *      type — a typo or a removed type would render as a blank/unknown block.
 *   3. A first-class block type (BiolinkBlock::TYPES) that has NO top-level
 *      renderer on the public biolink page. Top-level blocks render through a
 *      long inline @if/@elseif chain in common/biolink.blade.php that has no
 *      catch-all @else, so any type missing a branch there falls through and
 *      renders as a blank wrapper with no error (this is exactly how the
 *      buy_me_coffee block shipped blank). A type counts as covered when it is
 *      matched by an inline branch OR — only if the chain actually delegates
 *      unmatched types to it — by the partial dispatch table in
 *      common/partials/biolink-block-render.blade.php.
 *
 * Both seeders bake snapshots purely in PHP (no DB rows needed), so this
 * command reflects into their builders and validates the in-memory snapshots
 * directly — keeping it fast and DB-independent. The renderer-coverage check
 * statically parses the two Blade views. Exits non-zero on any failure so it
 * can be wired into a registered validation step.
 */
class CheckTemplateDesigns extends Command
{
    protected $signature = 'templates:check-designs';

    protected $description = 'Validate page-template seeder designs (variant-kit keys + snapshot block types/variants) without a DB.';

    /**
     * The link/big-link design variant declared on a kit is applied to BOTH
     * `link` and `link_big` blocks by the seeders, so a kit's link key must
     * resolve for both types.
     */
    private const LINK_TYPES = ['link', 'link_big'];

    /** @var array<int,string> */
    private array $failures = [];

    public function handle(): int
    {
        $this->checkStarterVariantKits();
        $this->checkPersonaVariantKits();
        $this->checkStarterSnapshots();
        $this->checkPersonaSnapshots();
        $this->checkTopLevelRenderers();

        if (! empty($this->failures)) {
            $this->newLine();
            $this->error(count($this->failures) . ' design problem(s) found:');
            foreach ($this->failures as $msg) {
                $this->line('  • ' . $msg);
            }
            return self::FAILURE;
        }

        $this->info('All page-template designs resolve cleanly — variant keys and block types are valid.');
        return self::SUCCESS;
    }

    private function checkStarterVariantKits(): void
    {
        $kits = $this->invokePrivate(new StarterPageTemplatesSeeder(), 'variantKits');

        foreach ($kits as $name => $kit) {
            $this->variantResolves($kit['ptype'], $kit['pvar'], "starter kit '{$name}' profile variant");
            foreach (self::LINK_TYPES as $linkType) {
                $this->variantResolves($linkType, $kit['link'], "starter kit '{$name}' link variant on {$linkType}");
            }
        }

        $this->info('Checked ' . count($kits) . ' starter variant kit(s).');
    }

    private function checkPersonaVariantKits(): void
    {
        $kits = $this->invokePrivate(new ExpandedPageTemplateLibrarySeeder(), 'variantKits');

        foreach ($kits as $i => $kit) {
            $this->variantResolves($kit['ptype'], $kit['pvar'], "persona kit #{$i} profile variant");
            foreach (self::LINK_TYPES as $linkType) {
                $this->variantResolves($linkType, $kit['link'], "persona kit #{$i} link variant on {$linkType}");
            }
        }

        $this->info('Checked ' . count($kits) . ' persona variant kit(s).');
    }

    private function checkStarterSnapshots(): void
    {
        $templates = $this->invokePrivate(new StarterPageTemplatesSeeder(), 'templates');

        if (empty($templates)) {
            // Legacy starter blueprints were retired; an empty list is the
            // expected state until the new template generation lands.
            $this->info('Starter seeder has no blueprints (retired) — nothing to check.');
            return;
        }

        foreach ($templates as $tpl) {
            $this->snapshotValid($tpl['slug'], $tpl['snapshot']);
        }

        $this->info('Checked ' . count($templates) . ' starter snapshot(s).');
    }

    private function checkPersonaSnapshots(): void
    {
        $seeder = new ExpandedPageTemplateLibrarySeeder();
        $personas = PersonaCatalog::all();

        if (empty($personas)) {
            $this->failures[] = 'no personas configured to build blueprints for';
            return;
        }

        $checked = 0;
        foreach ($personas as $persona) {
            foreach ($seeder->blueprintsFor($persona) as $bp) {
                $slug = 'persona-' . $persona['slug'] . '-' . $bp['key'];
                $this->snapshotValid($slug, $bp['snapshot']);
                $checked++;
            }
        }

        if ($checked === 0) {
            // Legacy persona blueprints were retired; an empty library is
            // the expected state until the new template generation lands.
            $this->info('Persona seeder has no blueprints (retired) — nothing to check.');
            return;
        }

        $this->info('Checked ' . $checked . ' persona snapshot(s).');
    }

    /**
     * Assert every first-class block type (BiolinkBlock::TYPES) has a renderer
     * that actually fires for a TOP-LEVEL block on the public biolink page.
     *
     * The public page renders top-level blocks via a long inline @if/@elseif
     * chain in common/biolink.blade.php. That chain has NO catch-all @else, so
     * a type without a branch silently falls through and renders as a blank
     * wrapper. The partial dispatch table in
     * common/partials/biolink-block-render.blade.php only renders a type at top
     * level if the chain explicitly delegates unmatched blocks to it (passing
     * the top-level `$block`, as opposed to the card/grid child loop which
     * delegates `$childBlock`). We detect that delegation and only credit the
     * partial table when it exists, so this check models real reachability
     * instead of assuming a fallthrough that isn't there.
     */
    private function checkTopLevelRenderers(): void
    {
        $publicView  = resource_path('views/common/biolink.blade.php');
        $partialView = resource_path('views/common/partials/biolink-block-render.blade.php');

        if (! is_file($publicView)) {
            $this->failures[] = "public biolink view not found at {$publicView}";
            return;
        }
        if (! is_file($partialView)) {
            $this->failures[] = "partial block-render view not found at {$partialView}";
            return;
        }

        $publicSrc  = (string) file_get_contents($publicView);
        $partialSrc = (string) file_get_contents($partialView);

        [$inlineExact, $inlinePrefixes] = $this->parseInlineRenderBranches($publicSrc);

        // The partial table is reachable at TOP LEVEL only when the inline
        // chain delegates the top-level $block to it (the child-container loop
        // delegates $childBlock and does NOT count). \b after $block keeps
        // $childBlock from matching.
        $fallsThroughToPartial = (bool) preg_match(
            '/biolink-block-render\'[^)]*\'block\'\s*=>\s*\$block\b/',
            $publicSrc
        );

        // Since Task #2042 the partial is the single source of truth: it
        // renders a type via the $__blockPartials dispatch map OR via its own
        // inline @if/@elseif chain (the branches that used to live here in
        // common/biolink.blade.php). Credit BOTH so a top-level block that
        // delegates to the partial is covered by either path.
        $partialExact    = [];
        $partialPrefixes = [];
        if ($fallsThroughToPartial) {
            [$partialInlineExact, $partialPrefixes] = $this->parseInlineRenderBranches($partialSrc);
            $partialExact = array_merge(
                $this->parsePartialDispatchKeys($partialSrc),
                $partialInlineExact
            );
        }

        $exact    = array_merge($inlineExact, $partialExact);
        $prefixes = array_merge($inlinePrefixes, $partialPrefixes);

        $covered = function (string $type) use ($exact, $prefixes): bool {
            if (in_array($type, $exact, true)) {
                return true;
            }
            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with($type, $prefix)) {
                    return true;
                }
            }
            return false;
        };

        $missing = 0;
        foreach (array_keys(BiolinkBlock::TYPES) as $type) {
            if ($covered($type)) {
                continue;
            }
            $missing++;
            $where = $fallsThroughToPartial
                ? "no @if/@elseif branch in common/biolink.blade.php and no entry in the "
                    . "common/partials/biolink-block-render.blade.php dispatch table"
                : "no @if/@elseif branch in common/biolink.blade.php (and the inline chain has no "
                    . "catch-all @else delegating unmatched top-level blocks to "
                    . "common/partials/biolink-block-render.blade.php)";
            $this->failures[] = "block type '{$type}' has no top-level public renderer: {$where} — "
                . 'it would render as a blank wrapper on public biolink pages.';
        }

        if ($missing === 0) {
            $this->info('Checked ' . count(BiolinkBlock::TYPES)
                . ' block type(s): all have a top-level public renderer '
                . ($fallsThroughToPartial
                    ? '(inline chain + partial dispatch fallthrough).'
                    : '(inline chain — no partial fallthrough present).'));
        }
    }

    /**
     * Statically extract the block types matched by the public page's inline
     * @if/@elseif render chain. Only @if/@elseif directive lines are scanned so
     * that @php helper arrays (skipWrap / btnLike lists) — which reference
     * $block->type but do NOT render anything — never count as coverage.
     *
     * Returns [exactTypes, prefixes]; a type is covered if it appears in
     * exactTypes or starts with one of the prefixes (e.g. str_starts_with
     * matches profile_card → profile_card_v1..v4).
     *
     * @return array{0: array<int,string>, 1: array<int,string>}
     */
    private function parseInlineRenderBranches(string $src): array
    {
        $exact    = [];
        $prefixes = [];

        foreach (preg_split('/\R/', $src) ?: [] as $line) {
            if (! preg_match('/@(?:if|elseif)\s*\(/', $line)) {
                continue;
            }

            // isContainerType($block->type) renders the card / grid / grid_auto
            // container family.
            if (str_contains($line, 'isContainerType($block->type)')) {
                $exact = array_merge($exact, BiolinkBlock::CONTAINER_TYPES);
            }

            // $block->type === 'x'
            if (preg_match_all('/\$block->type\s*===\s*\'([a-z0-9_]+)\'/', $line, $m)) {
                $exact = array_merge($exact, $m[1]);
            }

            // in_array($block->type, ['a', 'b', ...])
            if (preg_match_all('/in_array\(\s*\$block->type\s*,\s*\[([^\]]*)\]/', $line, $lists)) {
                foreach ($lists[1] as $listBody) {
                    if (preg_match_all('/\'([a-z0-9_]+)\'/', $listBody, $mm)) {
                        $exact = array_merge($exact, $mm[1]);
                    }
                }
            }

            // str_starts_with($block->type, 'prefix')
            if (preg_match_all('/str_starts_with\(\s*\$block->type\s*,\s*\'([a-z0-9_]+)\'/', $line, $m)) {
                $prefixes = array_merge($prefixes, $m[1]);
            }
        }

        return [
            array_values(array_unique($exact)),
            array_values(array_unique($prefixes)),
        ];
    }

    /**
     * Keys of the $__blockPartials dispatch map in
     * common/partials/biolink-block-render.blade.php (entries shaped
     * "type" => 'common.blocks.partial-name').
     *
     * @return array<int,string>
     */
    private function parsePartialDispatchKeys(string $src): array
    {
        $keys = [];
        if (preg_match_all('/"([a-z0-9_]+)"\s*=>\s*\'common\.blocks\./', $src, $m)) {
            $keys = $m[1];
        }
        return array_values(array_unique($keys));
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    /**
     * Walk every block (and nested container children) in a snapshot and
     * record any unknown block type or any baked `_style._variant` that fails
     * to resolve.
     *
     * @param array<string,mixed> $snapshot
     */
    private function snapshotValid(string $slug, array $snapshot): void
    {
        $blocks = $snapshot['blocks'] ?? [];
        if (! is_array($blocks)) {
            $this->failures[] = "snapshot '{$slug}' has no blocks array";
            return;
        }

        foreach ($this->flattenBlocks($blocks) as $block) {
            $type = $block['type'] ?? null;
            if ($type === null) {
                $this->failures[] = "snapshot '{$slug}' has a block with no type";
                continue;
            }
            if (! in_array($type, $this->validBlockTypes(), true)) {
                $this->failures[] = "snapshot '{$slug}' uses unknown block type '{$type}'";
                continue;
            }

            $variant = $block['settings']['_style']['_variant'] ?? null;
            if ($variant !== null && $variant !== '') {
                $this->variantResolves($type, $variant, "snapshot '{$slug}' baked variant on '{$type}'");
            }
        }

        // Placement-aware render-gap check: a known type still renders blank if
        // it lacks a branch in the placement (page-root vs container-child) it
        // actually occupies in this snapshot. Type-membership above does NOT
        // catch this — the two renderers cover different type sets.
        foreach (BlockRenderCoverage::renderGaps($blocks) as $gap) {
            $this->failures[] = "snapshot '{$slug}': {$gap}";
        }
    }

    /**
     * Flatten a block tree (containers carry `children`) into a flat list.
     *
     * @param array<int,array<string,mixed>> $blocks
     * @return array<int,array<string,mixed>>
     */
    private function flattenBlocks(array $blocks): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $flat[] = $block;
            if (! empty($block['children']) && is_array($block['children'])) {
                $flat = array_merge($flat, $this->flattenBlocks($block['children']));
            }
        }
        return $flat;
    }

    private function variantResolves(string $type, string $key, string $context): void
    {
        if (BlockVariantCatalog::find($type, $key) === null) {
            $this->failures[] = "{$context}: variant key '{$key}' does not resolve via "
                . "BlockVariantCatalog::find('{$type}', '{$key}') — it would silently "
                . 'fall back to default styling.';
        }
    }

    /**
     * Full set of real block types: model TYPES (incl. back-compat aliases the
     * seeders legitimately use, e.g. link_big / profile_card_v1) plus the
     * registry's NEW_TYPES.
     *
     * @return array<int,string>
     */
    private function validBlockTypes(): array
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

    /**
     * @return mixed
     */
    private function invokePrivate(object $object, string $method)
    {
        $ref = new ReflectionMethod($object, $method);
        return $ref->invoke($object);
    }
}
