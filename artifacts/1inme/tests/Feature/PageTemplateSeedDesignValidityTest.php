<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Services\PersonaCatalog;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\BlockVariantCatalog;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the page-template seeders against silently-degrading designs.
 *
 * Two classes of bug this catches:
 *   1. A baked design-variant key that no longer resolves via
 *      BlockVariantCatalog::find() for the block type it's applied to.
 *      When a key doesn't resolve the seeder falls back to default
 *      styling with NO error (variantStyle() only stamps `_variant`
 *      when find() succeeds), so a stale key silently strips the look.
 *      This is exactly how glass_pill / shadow_floating / neo_brutalist
 *      slipped through for link/link_big.
 *   2. A block type used in a seeded snapshot that isn't a real,
 *      known block type — a typo or a removed type would render as a
 *      blank/unknown block on the public page.
 *
 * Both seeders bake snapshots purely in PHP (no DB rows needed to build
 * them), so this test reflects into their builders and validates the
 * in-memory snapshots directly — keeping it fast and DB-independent.
 */
class PageTemplateSeedDesignValidityTest extends TestCase
{
    /**
     * The link/big-link design variant declared on a kit is applied to
     * BOTH `link` and `link_big` blocks by the seeders, so a kit's link
     * key must resolve for both types.
     */
    private const LINK_TYPES = ['link', 'link_big'];

    public function test_starter_variant_kit_keys_all_resolve(): void
    {
        $kits = $this->invokePrivate(new StarterPageTemplatesSeeder(), 'variantKits');

        foreach ($kits as $name => $kit) {
            $this->assertVariantResolves(
                $kit['ptype'],
                $kit['pvar'],
                "starter kit '{$name}' profile variant"
            );
            foreach (self::LINK_TYPES as $linkType) {
                $this->assertVariantResolves(
                    $linkType,
                    $kit['link'],
                    "starter kit '{$name}' link variant on {$linkType}"
                );
            }
        }
    }

    public function test_persona_variant_kit_keys_all_resolve(): void
    {
        $kits = $this->invokePrivate(new ExpandedPageTemplateLibrarySeeder(), 'variantKits');

        foreach ($kits as $i => $kit) {
            $this->assertVariantResolves(
                $kit['ptype'],
                $kit['pvar'],
                "persona kit #{$i} profile variant"
            );
            foreach (self::LINK_TYPES as $linkType) {
                $this->assertVariantResolves(
                    $linkType,
                    $kit['link'],
                    "persona kit #{$i} link variant on {$linkType}"
                );
            }
        }
    }

    public function test_starter_snapshots_use_valid_types_and_resolvable_variants(): void
    {
        $seeder = new StarterPageTemplatesSeeder();
        $templates = $this->invokePrivate($seeder, 'templates');

        // The legacy starter blueprints were retired, so an empty list is
        // the expected state until the new template generation lands. Any
        // blueprint that DOES exist must still validate.
        $this->assertIsArray($templates);

        foreach ($templates as $tpl) {
            $this->assertSnapshotValid($tpl['slug'], $tpl['snapshot']);
        }
    }

    public function test_persona_snapshots_use_valid_types_and_resolvable_variants(): void
    {
        $seeder = new ExpandedPageTemplateLibrarySeeder();
        $personas = PersonaCatalog::all();
        $this->assertNotEmpty($personas, 'no personas configured to build blueprints for');

        // The legacy persona blueprints were retired, so zero blueprints per
        // persona is the expected state until the new template generation
        // lands. Any blueprint that DOES exist must still validate.
        foreach ($personas as $persona) {
            $blueprints = $seeder->blueprintsFor($persona);
            $this->assertIsArray($blueprints);
            foreach ($blueprints as $bp) {
                $slug = 'persona-' . $persona['slug'] . '-' . $bp['key'];
                $this->assertSnapshotValid($slug, $bp['snapshot']);
            }
        }
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    /**
     * Walk every block (and nested container children) in a snapshot and
     * assert the type is real and any baked `_style._variant` resolves.
     *
     * @param  array<string,mixed>  $snapshot
     */
    private function assertSnapshotValid(string $slug, array $snapshot): void
    {
        $blocks = $snapshot['blocks'] ?? [];
        $this->assertIsArray($blocks, "snapshot '{$slug}' has no blocks array");

        foreach ($this->flattenBlocks($blocks) as $block) {
            $type = $block['type'] ?? null;
            $this->assertNotNull($type, "snapshot '{$slug}' has a block with no type");
            $this->assertContains(
                $type,
                $this->validBlockTypes(),
                "snapshot '{$slug}' uses unknown block type '{$type}'"
            );

            $variant = $block['settings']['_style']['_variant'] ?? null;
            if ($variant !== null && $variant !== '') {
                $this->assertVariantResolves(
                    $type,
                    $variant,
                    "snapshot '{$slug}' baked variant on '{$type}'"
                );
            }
        }
    }

    /**
     * Flatten a block tree (containers carry `children`) into a flat list.
     *
     * @param  array<int,array<string,mixed>>  $blocks
     * @return array<int,array<string,mixed>>
     */
    private function flattenBlocks(array $blocks): array
    {
        $flat = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $flat[] = $block;
            if (!empty($block['children']) && is_array($block['children'])) {
                $flat = array_merge($flat, $this->flattenBlocks($block['children']));
            }
        }
        return $flat;
    }

    private function assertVariantResolves(string $type, string $key, string $context): void
    {
        $this->assertNotNull(
            BlockVariantCatalog::find($type, $key),
            "{$context}: variant key '{$key}' does not resolve via "
            . "BlockVariantCatalog::find('{$type}', '{$key}') — it would silently "
            . 'fall back to default styling.'
        );
    }

    /**
     * Full set of real block types: model TYPES (incl. back-compat
     * aliases the seeders legitimately use, e.g. link_big / profile_card_v1)
     * plus the registry's NEW_TYPES.
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
