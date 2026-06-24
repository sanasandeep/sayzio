<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\TemplatePreviewLayoutBuilder;
use App\Modules\User\Services\WorkspaceContext;
use Database\Seeders\CardTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web-side sibling of {@see MobileTemplatePreviewRendersTest}.
 *
 * The shape-aware `preview_layout` blueprint produced by
 * {@see TemplatePreviewLayoutBuilder} is drawn as a thumbnail mock whenever a
 * template has no static thumbnail_url. That same builder feeds THREE surfaces;
 * the mobile gallery's path is already guarded over /api/v1, but the two WEB
 * surfaces had no test asserting the blueprint is non-empty / structurally
 * valid:
 *
 *   - Card Templates gallery  (Alpine, biolink-editor.blade.php) ← built by
 *       LinkTemplateController@cardGallery, exposed as JSON `items[].preview_layout`
 *       at GET user/links/{link}/templates/cards (maxRows = 10, taller cards).
 *   - Page Templates picker   (Blade,  templates/picker.blade.php) ← built by
 *       LinkTemplateController@picker, exposed as the `preview_layout` model
 *       attribute on each `pageTemplates` view item at
 *       GET user/links/{link}/templates (default maxRows = 6).
 *
 * A regression that blanks or mis-packs the blueprint would make every
 * template render as a featureless box in the web editor, uncaught. These
 * tests drive the real web controllers against the full seeded library and,
 * for every active template with no static thumbnail_url, assert the exposed
 * preview_layout is non-empty and structurally valid (rows of cells on a
 * 12-col grid, each carrying valid shape/span/bg/height hints) and exactly
 * mirrors the snapshot's block shapes/spans.
 *
 * Auth note: both routes are web-session guarded and sit behind
 * `workspace.can:links.view`, which the workspace OWNER bypasses — so we
 * authenticate a real web user via actingAs() and bind their default
 * workspace into the session (the SetActiveWorkspace middleware reads it).
 */
class WebTemplatePreviewLayoutTest extends TestCase
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
     * template requires, so plan-locked templates still render (the picker view
     * renders every active template regardless of lock) and the test covers the
     * FULL active library, not just the free tier. Returns the user with a
     * default workspace (the user owns it, so workspace.can:* is bypassed).
     */
    private function makeUnlockedUser(): User
    {
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
            'name'              => 'Web Tpl ' . Str::random(4),
            'email'             => 'wt-' . Str::random(8) . '@example.com',
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

    /**
     * Create an active page template with NO static thumbnail_url whose
     * snapshot holds the given single full-span block, so the picker is
     * forced to draw the shape-aware blueprint mock for it. `plan_tier` is
     * left null so the picker never locks it (locked cards still render the
     * blueprint, but null keeps the fixture intent obvious).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function makePageTemplate(string $slug, array $blocks): PageTemplate
    {
        return PageTemplate::create([
            'name'                 => 'Shape ' . $slug,
            'slug'                 => $slug,
            'category'             => 'general',
            'description'          => 'Shape coverage fixture',
            'thumbnail_url'        => null,
            'plan_tier'            => null,
            'recommended_personas' => [],
            'is_active'            => true,
            'sort_order'           => 1,
            'snapshot'             => ['blocks' => $blocks],
        ]);
    }

    /** A single full-span (grid_span 12) block of the given type. */
    private function fullSpanBlock(string $type): array
    {
        return ['type' => $type, 'settings' => ['_style' => ['grid_span' => 12]]];
    }

    /**
     * Authenticate the web user and bind their default workspace into the
     * session so SetActiveWorkspace resolves `current_workspace` and the
     * owner-bypass in RequireWorkspacePermission lets the request through.
     */
    private function actAsOwner(User $user): void
    {
        $ws = $user->ownedWorkspaces()->first();
        $this->actingAs($user);
        $this->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
    }

    /**
     * Replicate {@see TemplatePreviewLayoutBuilder::build()}'s 12-col grid
     * packing so the test can assert the controller's exposed `preview_layout`
     * actually mirrors the snapshot's blocks — instead of re-calling build()
     * (which would be vacuous). Each emitted cell is reduced to {shape, span};
     * the shape oracle is the builder's own cellFor() type→shape contract (the
     * documented mapping all three renderers read), so adding a new block type
     * to the palette won't break this test, but build() returning a
     * blank/empty/mis-packed blueprint will. `$maxRows` mirrors the per-surface
     * cap (web card gallery 10, page picker 6).
     *
     * @param  array<int, mixed>  $items
     * @return array<int, array<int, array{shape: string, span: int}>>
     */
    private function expectedPreviewLayout(array $items, int $maxRows): array
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
            $span = (int) ($settings['_style']['grid_span'] ?? 12);
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
     * Assert one exposed `preview_layout` is non-empty, structurally valid
     * (rows of cells on a 12-col grid, each cell carrying renderer hints), and
     * exactly mirrors the snapshot's block shapes/spans.
     *
     * @param  mixed  $layout
     * @param  array<int, mixed>  $rawItems
     */
    private function assertPreviewLayoutMirrorsSnapshot($layout, array $rawItems, string $label, int $maxRows): void
    {
        $this->assertIsArray($layout, "{$label} preview_layout is not an array");
        $this->assertNotEmpty(
            $layout,
            "{$label} produced an EMPTY preview_layout blueprint — the shape-aware "
            . 'thumbnail mock has no rows, so the web gallery would render this '
            . 'template as a featureless blank box. The build() grid-packing dropped '
            . 'every block.'
        );

        $expected = $this->expectedPreviewLayout($rawItems, $maxRows);
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
     * The web Card Templates gallery (LinkTemplateController@cardGallery,
     * GET user/links/{link}/templates/cards) builds a shape-aware
     * `preview_layout` (maxRows = 10) drawn as the thumbnail mock when a card
     * has no static thumbnail_url. Drive the real controller for the full
     * seeded library and assert every such card's blueprint is non-empty,
     * structurally valid, and mirrors its snapshot's child shapes.
     */
    public function test_web_card_gallery_exposes_a_valid_preview_layout_for_every_active_card(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->actAsOwner($user);

        $resp = $this->getJson(route('user.links.templates.cards', $link));
        $resp->assertOk();

        $items = $resp->json('items');
        $this->assertIsArray($items, 'web card gallery returned no items array');
        $this->assertNotEmpty($items, 'web card gallery listed no templates');

        $templates = CardTemplate::where('is_active', true)
            ->get(['id', 'slug', 'snapshot', 'thumbnail_url'])
            ->keyBy('id');
        $this->assertSame(
            $templates->count(),
            count($items),
            'web card gallery item count drifted from the active card library: '
            . "expected {$templates->count()} active template(s), got " . count($items)
        );

        $checked = 0;
        foreach ($items as $item) {
            $tpl = $templates[$item['id']] ?? null;
            $this->assertNotNull($tpl, "gallery returned an unknown card template id {$item['id']}");
            $label = "card template '{$tpl->slug}'";

            $this->assertArrayHasKey('preview_layout', $item, "{$label} is missing a 'preview_layout' blueprint");

            // The blueprint only fronts the thumbnail when no static image is
            // set; templates with a thumbnail_url never render it.
            if (!empty($item['thumbnail_url'])) {
                continue;
            }

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $rawChildren = is_array($snapshot['children'] ?? null) ? $snapshot['children'] : [];

            $this->assertPreviewLayoutMirrorsSnapshot($item['preview_layout'], $rawChildren, $label, 10);
            $checked++;
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'no active card template lacked a thumbnail_url, so the preview_layout '
            . 'blueprint guard never ran — the test would pass vacuously.'
        );
    }

    /**
     * The web Page Templates picker (LinkTemplateController@picker,
     * GET user/links/{link}/templates) attaches a shape-aware `preview_layout`
     * (default maxRows = 6) to each page template for the thumbnail mock when
     * no static thumbnail_url is set. Drive the real controller for the full
     * seeded library and assert every such page's exposed blueprint is
     * non-empty, structurally valid, and mirrors its snapshot's block shapes.
     */
    public function test_web_page_picker_exposes_a_valid_preview_layout_for_every_active_page(): void
    {
        $this->seedTemplateLibrary();
        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->actAsOwner($user);

        $resp = $this->get(route('user.links.templates.picker', $link));
        $resp->assertOk();

        $pageTemplates = $resp->viewData('pageTemplates');
        $this->assertNotNull($pageTemplates, 'page picker exposed no pageTemplates view data');
        $this->assertNotEmpty($pageTemplates, 'page picker listed no templates');

        $activeCount = PageTemplate::where('is_active', true)->count();
        $this->assertSame(
            $activeCount,
            count($pageTemplates),
            'page picker item count drifted from the active page library: '
            . "expected {$activeCount} active template(s), got " . count($pageTemplates)
        );

        $checked = 0;
        foreach ($pageTemplates as $tpl) {
            $label = "page template '{$tpl->slug}'";

            $layout = $tpl->preview_layout;
            $this->assertNotNull($layout, "{$label} is missing a 'preview_layout' blueprint");

            // The blueprint only fronts the thumbnail when no static image is
            // set; templates with a thumbnail_url never render it.
            if (!empty($tpl->thumbnail_url)) {
                continue;
            }

            $snapshot = is_array($tpl->snapshot) ? $tpl->snapshot : [];
            $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];

            $this->assertPreviewLayoutMirrorsSnapshot($layout, $blocks, $label, 6);
            $checked++;
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'no active page template lacked a thumbnail_url, so the preview_layout '
            . 'blueprint guard never ran — the test would pass vacuously.'
        );
    }

    /**
     * The data-level tests above prove the controller EXPOSES a valid
     * blueprint, but they stop short of the Blade renderer: a regression in
     * the @foreach($previewRows) shape loop in templates/picker.blade.php
     * (a wrong @if, a broken @case branch, a dropped wrapper) would still
     * draw a featureless box while the exposed data stays perfect. This test
     * drives the REAL picker route and asserts the rendered HTML actually
     * contains the blueprint container plus the distinctive per-cell markup
     * each shape branch emits.
     *
     * Coverage: one dedicated no-thumbnail page template per shape family
     * (heading, text_lines, pill, avatar, media, dot_row, form, list_rows),
     * each holding a single full-span block, so every @case in the picker's
     * shape switch is exercised.
     *
     * Anchoring note: the shape CSS class names (.tpl-prev-*) ALSO appear in
     * the picker's <style> block, so asserting a bare class name would pass
     * even if zero cells were drawn. Every needle below is anchored to
     * markup the renderer ONLY emits inside a drawn cell — a combined class
     * string, an inline style fragment, a placeholder <img> URL, or the
     * builder's hardcoded placeholder copy (which never appears in the
     * <style> block or the "what's inside" summary chips, since the fixture
     * blocks carry no text settings).
     */
    public function test_web_page_picker_draws_per_shape_cell_markup_in_the_rendered_html(): void
    {
        // shape family => a representative block type that maps to it via
        // TemplatePreviewLayoutBuilder::cellFor().
        $byShape = [
            'heading'    => 'heading',
            'text_lines' => 'paragraph',
            'pill'       => 'link',
            'avatar'     => 'profile_card_v1',
            'media'      => 'image',
            'dot_row'    => 'socials',
            'form'       => 'email_subscribe',
            'list_rows'  => 'list',
        ];
        foreach ($byShape as $shape => $type) {
            $this->makePageTemplate('shape-' . $shape, [$this->fullSpanBlock($type)]);
        }

        $user = $this->makeUnlockedUser();
        $link = $this->makeBiolink($user);
        $this->actAsOwner($user);

        $resp = $this->get(route('user.links.templates.picker', $link));
        $resp->assertOk();
        $html = (string) $resp->getContent();

        // The blueprint container wrapper + per-cell flex stub must be present
        // at all; their absence means the no-thumbnail templates fell through
        // to a blank/static tile instead of the shape mock.
        $this->assertStringContainsString(
            'flex flex-col gap-1 justify-center',
            $html,
            'picker rendered no blueprint container wrapper — every no-thumbnail '
            . 'template fell through to a blank tile instead of the shape mock.'
        );
        $this->assertStringContainsString(
            'flex: 12 0 0;',
            $html,
            'picker drew no per-cell flex stub (flex: {span} 0 0) — the '
            . '@foreach($row) cell loop produced nothing.'
        );

        // Each shape branch's distinctive, markup-only signal. Where a needle
        // is the builder placeholder copy, presence proves the text reached
        // the shape branch (it is emitted nowhere else).
        $perShape = [
            // <div class="tpl-prev-heading w-full">Your Headline</div>
            'heading' => [
                ['tpl-prev-heading w-full', 'heading @case did not draw its heading line'],
                ['Your Headline', 'heading placeholder copy missing from the rendered cell'],
            ],
            // <div class="tpl-prev-text" style="-webkit-line-clamp: ...">...</div>
            'text_lines' => [
                ['class="tpl-prev-text" style="-webkit-line-clamp', 'text_lines @case did not draw its clamped paragraph'],
                ['A short intro about you and what you share here.', 'paragraph placeholder copy missing from the rendered cell'],
            ],
            // pill button: rounded-full row carrying the link label.
            'pill' => [
                ['rounded-full flex items-center justify-center gap-1 px-1.5', 'pill @case did not draw its rounded button shell'],
                ['Visit my website', 'pill (link) placeholder copy missing from the rendered cell'],
            ],
            // avatar: circular placeholder <img> + name/handle lines.
            'avatar' => [
                ['block-placeholders/avatar.svg', 'avatar @case did not draw its circular placeholder image'],
                ['@yourhandle', 'avatar handle line missing from the rendered cell'],
            ],
            // media: tall image cell with a real placeholder <img>.
            'media' => [
                ['block-placeholders/image.svg', 'media @case did not draw its placeholder image'],
                ['rounded-[3px] relative overflow-hidden', 'media @case did not draw its image frame shell'],
            ],
            // dot_row: row of fixed 5px circular dots.
            'dot_row' => [
                ['width: 5px; height: 5px;', 'dot_row @case did not draw its circular icon dots'],
            ],
            // form: stacked input lines + a centered labelled button (70% wide).
            'form' => [
                ['min-height: 7px; width: 70%;', 'form @case did not draw its labelled submit button'],
                ['Subscribe', 'form button placeholder copy missing from the rendered cell'],
            ],
            // list_rows: dot + sample-text rows.
            'list_rows' => [
                ['tpl-prev-list flex-1', 'list_rows @case did not draw its sample text rows'],
                ['First item', 'list_rows placeholder copy missing from the rendered cell'],
            ],
        ];

        foreach ($perShape as $shape => $needles) {
            foreach ($needles as [$needle, $why]) {
                $this->assertStringContainsString(
                    $needle,
                    $html,
                    "[{$shape} shape] {$why} — the picker would render this "
                    . 'template as a featureless box even though its preview_layout '
                    . 'data is valid.'
                );
            }
        }
    }
}
