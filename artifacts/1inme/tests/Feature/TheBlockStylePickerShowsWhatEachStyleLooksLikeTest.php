<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Modules\User\Support\PageBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "block Templates for links are not clear.... clearly
 * not identifiable. verify and make it look good" and "rename block level
 * templates to something else as it coincides with page templates".
 *
 * The first was not a design problem, it was a bug. The endpoint that
 * renders the tiles called
 *
 *     BiolinkBlock::getBlockStyle($flatStyleMap, ...)
 *
 * but getBlockStyle() takes a block's SETTINGS and reads `_style` out of
 * it. A flat map has no `_style`, so every variant's payload was dropped on
 * the floor and all of them resolved to bare STYLE_DEFAULTS -- which is
 * why forty different designs rendered as forty copies of the same blank
 * sketch. Two smaller things made it worse: the page's Block Theme was read
 * from the wrong key so it never reached a tile, and the tiles sat on a
 * checkerboard rather than the page's own background, so a style with a
 * transparent background or inherited text colour had nothing to show
 * against.
 *
 * The second is a rename: the picker says "Styles" now, so nothing in the
 * editor competes with page templates for the word.
 */
class TheBlockStylePickerShowsWhatEachStyleLooksLikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $this->user = User::factory()->create(['onboarded_at' => Carbon::parse('2026-01-01')]);
        $ws = app(WorkspaceContext::class)->resolve($this->user);

        $this->link = Link::create([
            'user_id' => $this->user->id, 'workspace_id' => $ws?->id,
            'type' => 'biolink', 'alias' => 'styles'.fake()->unique()->numerify('####'),
            'title' => 'My page', 'is_active' => true,
        ]);
        $this->link->biolinkBlocks()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function block(string $type = 'link'): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id' => $this->link->id, 'type' => $type,
            'settings' => ['text' => 'Book a call', 'url' => 'https://cal.example'],
            'sort_order' => 0, 'is_active' => true,
        ]);
    }

    private function previews(BiolinkBlock $b): array
    {
        return $this->actingAs($this->user)
            ->getJson('/user/links/'.$this->link->id.'/blocks/'.$b->id.'/variant-previews')
            ->assertOk()->json();
    }

    // ===== 1. The tiles actually differ =====

    /**
     * The bug, stated as a number: distinct rendered styles, not distinct
     * names. Before the fix this was 1 for every block type in the app.
     */
    public function test_the_styles_do_not_all_render_identically(): void
    {
        $data  = $this->previews($this->block());
        $tiles = $data['previews'];

        $this->assertGreaterThan(5, count($tiles), 'a link button has a gallery of styles');

        $rendered = array_unique(array_column($tiles, 'inline_style'));

        $this->assertGreaterThan(
            count($tiles) / 2,
            count($rendered),
            'most styles must LOOK different, not just be named differently — '
            .count($tiles).' styles rendered as '.count($rendered).' distinct previews'
        );
    }

    /** And a named style actually carries its own design. */
    public function test_a_style_preview_carries_that_styles_own_design(): void
    {
        $b = $this->block();
        $variants = BlockVariantCatalog::forType('link');

        // Pick a variant that sets a background colour, and prove the tile
        // shows it. Any variant will do; this asserts on whichever is first.
        $withBg = null;
        foreach ($variants as $v) {
            $bg = $v['style']['bg_color'] ?? '';
            if (is_string($bg) && preg_match('/^#[0-9a-f]{6}$/i', $bg)) { $withBg = $v; break; }
        }
        $this->assertNotNull($withBg, 'the catalog should have at least one solid-background style');

        $tiles = collect($this->previews($b)['previews'])->keyBy('key');
        $tile  = $tiles[$withBg['key']] ?? null;

        $this->assertNotNull($tile, 'every catalog style must come back as a tile');
        $this->assertStringContainsString(
            strtolower($withBg['style']['bg_color']),
            strtolower($tile['inline_style']),
            'the "'.$withBg['name'].'" style sets a background; its preview must show it'
        );
    }

    /** Nothing comes back as an empty style. */
    public function test_no_tile_comes_back_with_nothing_to_draw(): void
    {
        $blank = collect($this->previews($this->block())['previews'])
            ->filter(fn ($p) => trim((string) $p['inline_style']) === '')
            ->pluck('name');

        $this->assertCount(0, $blank,
            'these styles resolved to no CSS at all: '.$blank->implode(', '));
    }

    /**
     * Sana's second note -- "similarly update for block templates for other
     * blocks... check where possible" -- needed no per-type work: the bug
     * was in the one endpoint every type's gallery goes through. This is
     * the proof, across a spread of shapes: a button, a heading, an image,
     * an avatar and a socials row.
     */
    public function test_every_kind_of_block_gets_distinguishable_styles(): void
    {
        $thin = [];

        foreach (['link', 'heading', 'image', 'avatar', 'socials', 'paragraph_rich'] as $type) {
            $tiles = $this->previews($this->block($type))['previews'];
            if (count($tiles) < 2) {
                continue;   // a type with a single style has nothing to tell apart
            }

            $distinct = count(array_unique(array_column($tiles, 'inline_style')));
            if ($distinct < 2) {
                $thin[] = $type.' ('.count($tiles).' styles, '.$distinct.' distinct)';
            }

            $this->link->biolinkBlocks()->delete();
        }

        $this->assertSame([], $thin,
            'these block types still render every style the same way: '.implode(', ', $thin));
    }

    // ===== 2. The tile is drawn on this page's own ground =====

    /** The gallery is told what this page looks like, once. */
    public function test_the_gallery_is_told_the_pages_background_and_ink(): void
    {
        $this->link->settings = ['biolink' => [
            'background_type'  => 'color',
            'background_color' => '#fdf6e3',
            'font_color'       => '#3a2f1b',
        ]];
        $this->link->save();

        $ground = $this->previews($this->block())['ground'];

        $this->assertSame('#fdf6e3', $ground['bg'],
            'a tile floating on a checkerboard cannot tell you whether a style '
            .'will be readable on your page');
        $this->assertSame('#3a2f1b', $ground['ink']);
        $this->assertFalse($ground['ink_is_light'], 'dark ink on a cream page');
    }

    /** A photo or preset background falls back to the colour behind it. */
    public function test_a_picture_background_falls_back_to_the_colour_behind_it(): void
    {
        $this->link->settings = ['biolink' => [
            'background_type'   => 'image',
            'background_image'  => 'https://example.com/x.jpg',
            'bg_fallback_color' => '#112233',
        ]];
        $this->link->save();

        $this->assertSame('#112233', $this->previews($this->block())['ground']['bg'],
            'the fallback colour is the one that always renders, so it is the '
            .'honest stand-in for a photo in an 80px tile');
    }

    /** A page that has never been themed still gets a real ground. */
    public function test_an_unthemed_page_still_gets_a_ground(): void
    {
        $ground = $this->previews($this->block())['ground'];

        $this->assertNotSame('', trim($ground['bg']));
        $this->assertNotSame('', trim($ground['ink']));
    }

    /** The helper is a preview stand-in, and says so by behaving like one. */
    public function test_the_preview_ground_helper_covers_the_common_cases(): void
    {
        $this->assertSame('#123456',
            PageBackground::previewGround(['background_type' => 'color', 'background_color' => '#123456'])['bg']);

        $this->assertStringContainsString('gradient',
            PageBackground::previewGround(['background_type' => 'gradient'])['bg'],
            'the default page is a gradient');

        $this->assertSame(PageBackground::DEFAULT_COLOR,
            PageBackground::previewGround(['background_type' => 'color', 'background_color' => ''])['bg'],
            'an empty colour is not a background');
    }

    // ===== 3. The page's own theme reaches the tiles =====

    /**
     * A Block Theme set to apply to all blocks changes what every style
     * looks like, so the gallery must show it. It was read from
     * `$link->settings` -- the wrong level -- so it never did.
     */
    public function test_the_pages_block_theme_reaches_the_previews(): void
    {
        $plain = collect($this->previews($this->block())['previews'])->keyBy('key');

        $this->link->settings = ['biolink' => ['block_theme' => [
            'apply_to_all' => true,
            'font_family'  => 'Courier Prime',
        ]]];
        $this->link->save();

        $themed = collect($this->previews($this->block())['previews'])->keyBy('key');

        $key = $plain->keys()->first();
        $this->assertStringNotContainsString('Courier Prime', $plain[$key]['inline_style']);
        $this->assertStringContainsString('Courier Prime', $themed[$key]['inline_style'],
            "the page's own Block Theme must show in the gallery, or the previews "
            .'are of a page the creator does not have');
    }

    // ===== 4. The rename =====

    /**
     * The picker says "Styles", so nothing in the editor competes with page
     * templates for the word.
     *
     * Asserted against the edit form, which is where the picker lives -- it
     * is fetched on demand when a creator opens a block, not rendered with
     * the page.
     */
    public function test_the_picker_is_called_styles(): void
    {
        $b = $this->block();

        $html = $this->actingAs($this->user)
            ->getJson('/user/links/'.$this->link->id.'/blocks/'.$b->id.'/edit-form')
            ->assertOk()->json('html');

        $this->assertStringContainsString('One-click styles for this block', $html);
        $this->assertStringContainsString("showToast('Style applied'", $html);
        $this->assertStringContainsString('Apply this style to all', $html);
        $this->assertStringContainsString('No styles match this filter yet.', $html);
        $this->assertStringContainsString('>Styles', $html, 'the tab itself');

        // And the old vocabulary is gone from what a creator reads.
        $this->assertStringNotContainsString('One-click skins', $html,
            '"skins" was the other half of the inconsistency');
        $this->assertStringNotContainsString("showToast('Design applied'", $html);
        $this->assertStringNotContainsString('Apply this design to all', $html);
        $this->assertStringNotContainsString('No designs match', $html);
    }

    // ===== 5. It is still someone else's page's business =====

    /** Previews for a block on another page are refused. */
    public function test_previews_are_scoped_to_your_own_page(): void
    {
        $other = User::factory()->create(['onboarded_at' => Carbon::parse('2026-01-01')]);
        $ws    = app(WorkspaceContext::class)->resolve($other);
        $their = Link::create(['user_id' => $other->id, 'workspace_id' => $ws?->id, 'type' => 'biolink',
            'alias' => 'theirs'.fake()->unique()->numerify('####'), 'is_active' => true]);
        $their->biolinkBlocks()->delete();
        $block = BiolinkBlock::create(['link_id' => $their->id, 'type' => 'link',
            'settings' => [], 'sort_order' => 0, 'is_active' => true]);
        app(WorkspaceContext::class)->resolve($this->user);

        $this->actingAs($this->user)
            ->getJson('/user/links/'.$this->link->id.'/blocks/'.$block->id.'/variant-previews')
            ->assertStatus(403);
    }
}
