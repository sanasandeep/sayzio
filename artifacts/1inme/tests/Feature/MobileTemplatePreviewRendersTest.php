<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use Database\Seeders\CardTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile-side sibling of {@see TemplatePreviewRendersTest}.
 *
 * Admins/creators can browse and apply the same template library from the
 * Expo app over /api/v1, which renders and serializes the stored snapshots
 * through a DIFFERENT path than the web admin preview route:
 *
 *   - Page: GET /api/v1/links/{id}/page-templates/{template}
 *       Builds an unsaved preview link via TemplateService::buildPreviewLink()
 *       (the same sanitizer applyPage uses) and flattens the active block tree
 *       into the public {id,type,sort_order,parent_id,settings} payload.
 *   - Card: POST /api/v1/links/{id}/card-templates/apply
 *       Inserts the card sub-tree via TemplateService::applyCardToLink()
 *       (insertBlockTree + sanitizer) and returns the freshly-created tree.
 *
 * Both paths throw InvalidArgumentException on an unknown/unsupported block
 * type (buildPreviewBlock / insertBlockTree) — which, unlike the web preview
 * route, is NOT caught here, so a renderer-incompatible snapshot surfaces as
 * an HTTP 500 rather than a readable fallback. Either way, a template whose
 * stored snapshot blanks out its blocks on this surface would otherwise go
 * uncaught: the mobile gallery would silently show an empty page/card.
 *
 * This test drives the real HTTP endpoints against the actual seeded
 * template library so it catches regressions in the live mobile serialize
 * path (buildPreviewLink, applyCardToLink, the shared sanitizer) that a
 * pure snapshot-shape check would miss.
 *
 * Auth note: authenticated with a REAL Sanctum bearer token, not
 * Sanctum::actingAs() — the latter injects a mock current-access-token that
 * the TouchSessionToken middleware then tries to ->save(), 500-ing every
 * request.
 */
class MobileTemplatePreviewRendersTest extends TestCase
{
    use RefreshDatabase;

    /** Seed the full live template library (page + card). */
    private function seedTemplateLibrary(): void
    {
        $this->seed(StarterPageTemplatesSeeder::class);
        $this->seed(ExpandedPageTemplateLibrarySeeder::class);
        $this->seed(CardTemplateSeeder::class);
    }

    /**
     * Build a user holding a plan that ranks above every plan tier any active
     * template requires, so plan-locked templates ({@see CardTemplateController})
     * are still appliable and the test covers the FULL active library — not just
     * the free tier. Returns the user with a default workspace.
     */
    private function makeUnlockedUser(): User
    {
        // Create a Plan row for every distinct plan_tier referenced by an
        // active template, ranked low; the user's own plan ranks far above
        // them all so isLocked() never trips for any template.
        $tiers = collect()
            ->merge(PageTemplate::active()->pluck('plan_tier'))
            ->merge(CardTemplate::active()->pluck('plan_tier'))
            ->filter(fn ($t) => is_string($t) && $t !== '')
            ->unique();

        foreach ($tiers as $tier) {
            Plan::firstOrCreate(
                ['slug' => $tier],
                [
                    'name'          => ucfirst((string) $tier),
                    'monthly_price' => 0,
                    'annual_price'  => 0,
                    'trial_days'    => 0,
                    'status'        => 'active',
                    'sort_order'    => 1,
                    'features'      => ['max_links' => 1000, 'max_biolinks' => 1000],
                ],
            );
        }

        $topPlan = Plan::firstOrCreate(
            ['slug' => 'tpl-top-' . Str::random(6)],
            [
                'name'          => 'Top',
                'monthly_price' => 0,
                'annual_price'  => 0,
                'trial_days'    => 0,
                'status'        => 'active',
                'sort_order'    => 99999,
                'features'      => ['max_links' => 1000, 'max_biolinks' => 1000],
            ],
        );

        $user = User::create([
            'name'              => 'Mobile Tpl ' . Str::random(4),
            'email'             => 'mt-' . Str::random(8) . '@example.com',
            'password'          => Hash::make('x'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'plan_id'           => $topPlan->id,
        ]);
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    private function makeBiolink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => Link::generateAlias(),
            'title'   => 'Preview Bio',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * The page-template seeders were neutralized (legacy blueprints
     * retired), so the page-side guards create their own fixture row to
     * keep the serialize paths exercised against a real template.
     */
    private function makePageTemplateFixture(): PageTemplate
    {
        return PageTemplate::create([
            'name'                 => 'Preview Fixture',
            'slug'                 => 'preview-fixture-page',
            'category'             => 'general',
            'description'          => 'Fixture page template for mobile preview coverage.',
            'thumbnail_url'        => null,
            'plan_tier'            => null,
            'is_active'            => true,
            'sort_order'           => 1,
            'recommended_personas' => [],
            'snapshot'             => ['blocks' => [
                ['type' => 'heading', 'settings' => ['text' => 'Hello'], 'is_active' => true],
                ['type' => 'paragraph', 'settings' => ['text' => 'World'], 'is_active' => true],
                ['type' => 'link', 'settings' => ['name' => 'Visit', 'url' => 'https://example.com'], 'is_active' => true],
            ]],
        ]);
    }

    /**
     * Recursively count the active blocks a page snapshot should flatten into
     * via buildPreviewLink: every block (default-active unless is_active is
     * explicitly false), recursing into the children of ANY container type
     * (card, grid_auto, grid, …) exactly like buildPreviewBlock does via
     * BiolinkBlock::isContainerType() — the set the show endpoint emits
     * after filtering on is_active.
     */
    private function countActivePreviewBlocks(array $blocks): int
    {
        $n = 0;
        foreach ($blocks as $b) {
            if (!is_array($b)) {
                continue;
            }
            $active = !array_key_exists('is_active', $b) || (bool) $b['is_active'];
            if ($active) {
                $n++;
            }
            if (\App\Modules\User\Models\BiolinkBlock::isContainerType($b['type'] ?? null)
                && !empty($b['children']) && is_array($b['children'])) {
                $n += $this->countActivePreviewBlocks($b['children']);
            }
        }
        return $n;
    }

    /**
     * Count the nodes a flat snapshot list would summarize into via
     * TemplateContentSummarizer::summarizeChildren / summarizePageBlocks:
     * each entry that is an array carrying a non-empty `type` (summarizeOne
     * drops everything else). This is exactly the set the gallery's
     * `children` / `content` arrays — and the `children_count` /
     * `blocks_count` tallies — are built from.
     *
     * @param  array<int, mixed>  $nodes
     */
    private function countSummarizable(array $nodes): int
    {
        $n = 0;
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ((string) ($node['type'] ?? '') === '') {
                continue;
            }
            $n++;
        }
        return $n;
    }

    /**
     * The mobile card *gallery* (GET .../card-templates, index) serializes each
     * card's children through TemplateContentSummarizer::summarizeChildren — a
     * DIFFERENT path than the apply endpoint above. A regression that blanks out
     * a card's "what's inside" summary in the browse list (e.g. a child type that
     * summarizeOne silently drops) would leave the user staring at an empty card
     * preview before they ever tap Apply. Drive the real endpoint for the full
     * seeded library and assert every active card lists a non-empty summary whose
     * count matches its snapshot.
     */
    public function test_every_active_card_template_lists_a_nonempty_children_summary(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $resp = $this->getJson("/api/v1/links/{$link->id}/card-templates");
        $resp->assertOk();

        $items = $resp->json('data.items');
        $this->assertIsArray($items, 'card-template gallery returned no items array');
        $this->assertNotEmpty($items, 'card-template gallery listed no templates');

        $templates = CardTemplate::where('is_active', true)
            ->get(['id', 'slug', 'snapshot'])
            ->keyBy('id');
        $this->assertSame(
            $templates->count(),
            count($items),
            'card-template gallery item count drifted from the active card library: '
            . "expected {$templates->count()} active template(s), got " . count($items)
        );

        foreach ($items as $item) {
            $tpl = $templates[$item['id']] ?? null;
            $this->assertNotNull($tpl, "gallery returned an unknown card template id {$item['id']}");
            $label = "card template '{$tpl->slug}'";

            $this->assertArrayHasKey('children', $item, "{$label} is missing a 'children' summary");
            $this->assertIsArray($item['children'], "{$label} 'children' summary is not an array");
            $this->assertNotEmpty(
                $item['children'],
                "{$label} listed an EMPTY 'what's inside' summary — every child block "
                . 'was dropped by the gallery summarizer, so the mobile picker shows a '
                . 'blank card preview. A child block type is no longer summarizable.'
            );

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $rawChildren = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];
            $expected = $this->countSummarizable($rawChildren);

            $this->assertSame(
                $expected,
                $item['children_count'],
                "{$label} reported children_count {$item['children_count']} but its snapshot "
                . "holds {$expected} summarizable child block(s) — the gallery is silently "
                . 'dropping children from the summary.'
            );
            $this->assertSame(
                $expected,
                count($item['children']),
                "{$label} serialized " . count($item['children']) . " summary entr(ies) but "
                . "its snapshot holds {$expected} summarizable child block(s)."
            );

            foreach ($item['children'] as $child) {
                $this->assertNotEmpty(
                    $child['label'] ?? '',
                    "{$label} produced a child summary with a blank label ("
                    . ($child['type'] ?? '?') . ') — the friendly-label lookup broke.'
                );
            }
        }
    }

    /**
     * The mobile page-template *gallery* (GET .../page-templates, index)
     * summarizes each template's top-level blocks through
     * TemplateContentSummarizer::summarizePageBlocks for the `content` /
     * `blocks_count` browse preview — a DIFFERENT path than the show endpoint
     * (which builds a real preview link). A regression that blanks the content
     * summary would show an empty page card in the picker, so cover it too.
     */
    public function test_every_active_page_template_lists_a_nonempty_content_summary(): void
    {
        $this->seedTemplateLibrary();
        $this->makePageTemplateFixture();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates");
        $resp->assertOk();

        $items = $resp->json('data.items');
        $this->assertIsArray($items, 'page-template gallery returned no items array');
        $this->assertNotEmpty($items, 'page-template gallery listed no templates');

        $templates = PageTemplate::where('is_active', true)
            ->get(['id', 'slug', 'snapshot'])
            ->keyBy('id');
        $this->assertSame(
            $templates->count(),
            count($items),
            'page-template gallery item count drifted from the active page library: '
            . "expected {$templates->count()} active template(s), got " . count($items)
        );

        foreach ($items as $item) {
            $tpl = $templates[$item['id']] ?? null;
            $this->assertNotNull($tpl, "gallery returned an unknown page template id {$item['id']}");
            $label = "page template '{$tpl->slug}'";

            $this->assertArrayHasKey('content', $item, "{$label} is missing a 'content' summary");
            $this->assertIsArray($item['content'], "{$label} 'content' summary is not an array");
            $this->assertNotEmpty(
                $item['content'],
                "{$label} listed an EMPTY content summary — every top-level block was "
                . 'dropped by the gallery summarizer, so the mobile picker shows a blank '
                . 'page preview. A block type is no longer summarizable.'
            );

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
            $expected = $this->countSummarizable($blocks);

            $this->assertSame(
                $expected,
                $item['blocks_count'],
                "{$label} reported blocks_count {$item['blocks_count']} but its snapshot "
                . "holds {$expected} summarizable top-level block(s) — the gallery is "
                . 'silently dropping blocks from the summary.'
            );
            $this->assertSame(
                $expected,
                count($item['content']),
                "{$label} serialized " . count($item['content']) . " summary entr(ies) but "
                . "its snapshot holds {$expected} summarizable top-level block(s)."
            );

            foreach ($item['content'] as $entry) {
                $this->assertNotEmpty(
                    $entry['label'] ?? '',
                    "{$label} produced a content summary with a blank label ("
                    . ($entry['type'] ?? '?') . ') — the friendly-label lookup broke.'
                );
            }
        }
    }

    public function test_every_active_page_template_serializes_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $this->makePageTemplateFixture();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $templates = PageTemplate::where('is_active', true)->get(['id', 'slug', 'snapshot']);
        $this->assertNotEmpty($templates, 'no active page templates were seeded to preview');

        foreach ($templates as $tpl) {
            $label = "page template '{$tpl->slug}'";

            $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates/{$tpl->id}");
            $resp->assertOk(
                // A 500 here means buildPreviewLink threw on a renderer-
                // incompatible block type in the snapshot.
            );

            $blocks = $resp->json('data.blocks');
            $this->assertIsArray($blocks, "{$label} returned no blocks array");
            $this->assertNotEmpty(
                $blocks,
                "{$label} serialized to an empty block payload — its snapshot "
                . 'has no active blocks the mobile renderer can show. Open the '
                . 'admin design-fix tools to repair it.'
            );

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $expected = $this->countActivePreviewBlocks($snapshot['blocks'] ?? []);
            $this->assertSame(
                $expected,
                count($blocks),
                "{$label} silently dropped blocks during mobile serialization: "
                . "expected {$expected} active block(s) from the snapshot, got "
                . count($blocks) . '. A renderer-incompatible block type is being '
                . 'stripped instead of rendered.'
            );
        }
    }

    public function test_every_active_card_template_applies_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $templates = CardTemplate::where('is_active', true)->get(['id', 'slug', 'snapshot']);
        $this->assertNotEmpty($templates, 'no active card templates were seeded to preview');

        foreach ($templates as $tpl) {
            $label = "card template '{$tpl->slug}'";

            $resp = $this->postJson("/api/v1/links/{$link->id}/card-templates/apply", [
                'template_id' => $tpl->id,
            ]);
            $resp->assertOk(
                // A 500 here means applyCardToLink/insertBlockTree threw on a
                // renderer-incompatible block type in the snapshot.
            );

            $blocks = $resp->json('data.blocks');
            $this->assertIsArray($blocks, "{$label} returned no blocks array");
            $this->assertNotEmpty(
                $blocks,
                "{$label} applied to an empty block payload — its snapshot "
                . 'produced no blocks the mobile renderer can show.'
            );

            // applyCardToLink returns the root card plus its direct children,
            // so the serialized tree must include the card and one entry per
            // snapshot child. A short payload means children were dropped.
            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $children = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];
            $expected = 1 + count($children);
            $this->assertSame(
                $expected,
                count($blocks),
                "{$label} silently dropped blocks during mobile apply: expected "
                . "{$expected} block(s) (card + " . count($children) . ' child blocks) '
                . 'from the snapshot, got ' . count($blocks) . '. A renderer-'
                . 'incompatible child block type is being stripped instead of '
                . 'inserted.'
            );

            // The root must be the card container; remaining entries are its
            // children, confirming the sub-tree serialized intact.
            $this->assertSame('card', $blocks[0]['type'], "{$label} root block is not a card");
        }
    }

    /**
     * Replicate {@see TemplatePreviewLayoutBuilder::build()}'s 12-col grid
     * packing so the test can assert the endpoint's `preview_layout` actually
     * mirrors the snapshot's blocks — instead of re-calling build() (which
     * would be vacuous). Each emitted cell is reduced to {shape, span}; the
     * shape oracle is the builder's own cellFor() type→shape contract (the
     * documented mapping all three renderers read), so adding a new block type
     * to the palette won't break this test, but build() returning a
     * blank/empty/mis-packed blueprint will.
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<int, array{shape: string, span: int}>>
     */
    private function expectedPreviewLayout(array $items, int $maxRows = 6): array
    {
        $builder = app(TemplatePreviewLayoutBuilder::class);
        $maxRows = max(1, $maxRows);
        $rows = [];
        $current = [];
        $used = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = (string) ($item['type'] ?? '');
            if ($type === '') {
                continue;
            }
            $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];
            // Mirror build(): the desktop span (grid_span_md) wins when a
            // block carries one, since thumbnails read as desktop blueprints.
            $span = (int) ($settings['_style']['grid_span_md']
                ?? $settings['_style']['grid_span'] ?? 12);
            $span = max(1, min(12, $span));
            $cell = ['shape' => (string) $builder->cellFor($type)['shape'], 'span' => $span];
            if ($used + $span > 12 && $current) {
                $rows[] = $current;
                $current = [];
                $used = 0;
                if (count($rows) >= $maxRows) {
                    break;
                }
            }
            $current[] = $cell;
            $used += $span;
            if ($used >= 12) {
                $rows[] = $current;
                $current = [];
                $used = 0;
                if (count($rows) >= $maxRows) {
                    break;
                }
            }
        }
        if ($current && count($rows) < $maxRows) {
            $rows[] = $current;
        }
        return $rows;
    }

    /**
     * Assert one endpoint-returned `preview_layout` is non-empty, structurally
     * valid (rows of cells on a 12-col grid, each cell carrying renderer
     * hints), and exactly mirrors the snapshot's block shapes/spans.
     *
     * @param  mixed  $layout
     * @param  array<int, mixed>  $rawItems
     */
    private function assertPreviewLayoutMirrorsSnapshot($layout, array $rawItems, string $label): void
    {
        $this->assertIsArray($layout, "{$label} preview_layout is not an array");
        $this->assertNotEmpty(
            $layout,
            "{$label} produced an EMPTY preview_layout blueprint — the shape-aware "
            . 'thumbnail mock has no rows, so the mobile gallery would render this '
            . 'template as a featureless blank box. The build() grid-packing dropped '
            . 'every block.'
        );

        $expected = $this->expectedPreviewLayout($rawItems);
        $this->assertNotEmpty(
            $expected,
            "{$label} has a snapshot with no layout-eligible (typed) blocks, so the "
            . 'non-empty preview guarantee cannot hold — the snapshot itself is blank.'
        );

        $this->assertSame(
            count($expected),
            count($layout),
            "{$label} preview_layout has " . count($layout) . ' row(s) but its snapshot '
            . 'packs into ' . count($expected) . ' row(s) — the blueprint grid-packing '
            . 'drifted from the snapshot.'
        );

        foreach ($layout as $r => $row) {
            $this->assertIsArray($row, "{$label} preview_layout row {$r} is not an array");
            $this->assertNotEmpty($row, "{$label} preview_layout row {$r} is empty");

            $expectedRow = $expected[$r];
            $this->assertSame(
                count($expectedRow),
                count($row),
                "{$label} preview_layout row {$r} has " . count($row) . ' cell(s) but the '
                . 'snapshot packs ' . count($expectedRow) . ' cell(s) there.'
            );

            $spanSum = 0;
            foreach ($row as $c => $cell) {
                $this->assertIsArray($cell, "{$label} preview_layout row {$r} cell {$c} is not an array");
                $this->assertArrayHasKey('shape', $cell, "{$label} row {$r} cell {$c} has no shape hint");
                $this->assertArrayHasKey('span', $cell, "{$label} row {$r} cell {$c} has no span");
                $this->assertArrayHasKey('bg', $cell, "{$label} row {$r} cell {$c} has no bg hint");
                $this->assertArrayHasKey('h', $cell, "{$label} row {$r} cell {$c} has no height hint");

                $this->assertNotEmpty(
                    (string) $cell['shape'],
                    "{$label} row {$r} cell {$c} has a blank shape hint — the renderer "
                    . 'would have nothing to draw.'
                );
                $span = (int) $cell['span'];
                $this->assertGreaterThanOrEqual(1, $span, "{$label} row {$r} cell {$c} span is < 1");
                $this->assertLessThanOrEqual(12, $span, "{$label} row {$r} cell {$c} span is > 12");

                $this->assertSame(
                    $expectedRow[$c]['shape'],
                    (string) $cell['shape'],
                    "{$label} row {$r} cell {$c} shape '" . (string) $cell['shape'] . "' diverged "
                    . "from the snapshot block's shape '{$expectedRow[$c]['shape']}' — the "
                    . 'thumbnail mock no longer matches the template.'
                );
                $this->assertSame(
                    $expectedRow[$c]['span'],
                    $span,
                    "{$label} row {$r} cell {$c} span {$span} diverged from the snapshot "
                    . "block's grid_span {$expectedRow[$c]['span']}."
                );

                $spanSum += $span;
            }

            $this->assertLessThanOrEqual(
                12,
                $spanSum,
                "{$label} preview_layout row {$r} packs spans summing to {$spanSum} (> 12 "
                . 'columns) — the blueprint overflows the grid.'
            );
        }
    }

    /**
     * The mobile card *gallery* (GET .../card-templates, index) also builds a
     * shape-aware `preview_layout` blueprint via TemplatePreviewLayoutBuilder —
     * the little mock drawn at thumbnail size when a card has no static
     * thumbnail_url. This is a THIRD serialize path (besides the children
     * summary and the apply tree) that nothing else covers. A regression that
     * blanks out or mis-packs this blueprint would make every card render as a
     * featureless box in the picker. Drive the real endpoint for the full
     * seeded library and assert every active card's blueprint is non-empty,
     * structurally valid, and mirrors its snapshot's child shapes.
     */
    public function test_every_active_card_template_has_a_valid_preview_layout(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $resp = $this->getJson("/api/v1/links/{$link->id}/card-templates");
        $resp->assertOk();

        $items = $resp->json('data.items');
        $this->assertIsArray($items, 'card-template gallery returned no items array');
        $this->assertNotEmpty($items, 'card-template gallery listed no templates');

        $templates = CardTemplate::where('is_active', true)
            ->get(['id', 'slug', 'snapshot'])
            ->keyBy('id');
        $this->assertSame(
            $templates->count(),
            count($items),
            'card-template gallery item count drifted from the active card library: '
            . "expected {$templates->count()} active template(s), got " . count($items)
        );

        foreach ($items as $item) {
            $tpl = $templates[$item['id']] ?? null;
            $this->assertNotNull($tpl, "gallery returned an unknown card template id {$item['id']}");
            $label = "card template '{$tpl->slug}'";

            $this->assertArrayHasKey('preview_layout', $item, "{$label} is missing a 'preview_layout' blueprint");

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $rawChildren = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];

            $this->assertPreviewLayoutMirrorsSnapshot($item['preview_layout'], $rawChildren, $label);
        }
    }

    /**
     * The mobile page-template *gallery* (GET .../page-templates, index) builds
     * the same shape-aware `preview_layout` blueprint from the page's top-level
     * blocks for the thumbnail mock when no static thumbnail_url is set — a
     * third serialize path alongside the content summary and the show preview.
     * A regression that blanks or mis-packs it would show a featureless box for
     * every page in the picker, so cover the full seeded library here too.
     */
    public function test_every_active_page_template_has_a_valid_preview_layout(): void
    {
        $this->seedTemplateLibrary();
        $this->makePageTemplateFixture();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates");
        $resp->assertOk();

        $items = $resp->json('data.items');
        $this->assertIsArray($items, 'page-template gallery returned no items array');
        $this->assertNotEmpty($items, 'page-template gallery listed no templates');

        $templates = PageTemplate::where('is_active', true)
            ->get(['id', 'slug', 'snapshot'])
            ->keyBy('id');
        $this->assertSame(
            $templates->count(),
            count($items),
            'page-template gallery item count drifted from the active page library: '
            . "expected {$templates->count()} active template(s), got " . count($items)
        );

        foreach ($items as $item) {
            $tpl = $templates[$item['id']] ?? null;
            $this->assertNotNull($tpl, "gallery returned an unknown page template id {$item['id']}");
            $label = "page template '{$tpl->slug}'";

            $this->assertArrayHasKey('preview_layout', $item, "{$label} is missing a 'preview_layout' blueprint");

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];

            $this->assertPreviewLayoutMirrorsSnapshot($item['preview_layout'], $blocks, $label);
        }
    }

    /**
     * Sanity check on the guard itself: a page template whose snapshot holds
     * a deliberately broken block type must blow up the mobile show endpoint
     * (buildPreviewLink throws, uncaught -> HTTP 500). This proves the
     * positive tests above would actually catch a renderer-incompatible
     * template rather than passing vacuously.
     */
    public function test_broken_page_template_fails_the_mobile_preview(): void
    {
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->withToken($this->token($user));

        $broken = PageTemplate::create([
            'slug'                 => 'broken-' . Str::random(8),
            'name'                 => 'Broken Preview',
            'category'             => 'general',
            'description'          => 'Deliberately broken for the guard test.',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => [
                'biolink' => [],
                'blocks'  => [
                    ['type' => 'definitely_not_a_real_block_type', 'settings' => [], 'is_active' => true],
                ],
            ],
        ]);

        $resp = $this->getJson("/api/v1/links/{$link->id}/page-templates/{$broken->id}");
        $resp->assertStatus(500);
    }
}
