<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BlockStyleSanitizer;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6576 — trackable link-list block (link_tree_group) with layout
 * variations. Covers:
 *   - per-item click tracking through the block-redirect pipeline
 *   - sanitizer acceptance of the new layout/alignment keys
 *   - stable item ids minted on save
 *   - legacy blocks (no new keys) still rendering the default list layout
 */
class LinkTreeGroupBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeBiolink(User $user): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function makeGroupBlock(Link $bio, array $settings): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link_tree_group',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => $settings,
        ]);
    }

    public function test_item_click_is_tracked_and_redirects(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'layout' => 'list',
            'items'  => [
                ['id' => 'abc12345', 'text' => 'Portfolio', 'url' => 'https://example.com/portfolio'],
                ['id' => 'def67890', 'text' => 'Blog', 'url' => 'https://example.com/blog'],
            ],
        ]);

        $resp = $this->get("/{$bio->alias}/b/{$block->id}?to=" . urlencode('https://example.com/portfolio') . '&item=abc12345');
        $resp->assertRedirect('https://example.com/portfolio');

        $click = LinkClick::where('link_id', $bio->id)->first();
        $this->assertNotNull($click, 'expected a click row for the item tap');
        $this->assertSame($block->id, (int) $click->block_id);
        $this->assertSame('https://example.com/portfolio', $click->destination_url);
        $this->assertSame('abc12345', $click->block_item_id);
    }

    public function test_items_sharing_a_destination_are_attributed_separately(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'layout' => 'list',
            'items'  => [
                ['id' => 'aaa11111', 'text' => 'Shop', 'url' => 'https://example.com/same'],
                ['id' => 'bbb22222', 'text' => 'Merch', 'url' => 'https://example.com/same'],
            ],
        ]);

        $to = urlencode('https://example.com/same');
        $this->get("/{$bio->alias}/b/{$block->id}?to={$to}&item=aaa11111")->assertRedirect();
        $this->get("/{$bio->alias}/b/{$block->id}?to={$to}&item=bbb22222")->assertRedirect();

        $ids = LinkClick::where('link_id', $bio->id)->pluck('block_item_id')->all();
        sort($ids);
        $this->assertSame(['aaa11111', 'bbb22222'], $ids);
    }

    public function test_invalid_item_param_is_dropped_not_fatal(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'items' => [['id' => 'abc12345', 'text' => 'X', 'url' => 'https://example.com/x']],
        ]);

        $this->get("/{$bio->alias}/b/{$block->id}?to=" . urlencode('https://example.com/x') . '&item=<bad$id>')
            ->assertRedirect('https://example.com/x');
        $click = LinkClick::where('link_id', $bio->id)->first();
        $this->assertNotNull($click);
        $this->assertNull($click->block_item_id);
    }

    public function test_mobile_tap_records_item_id(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'items' => [['id' => 'abc12345', 'text' => 'Blog', 'url' => 'https://example.com/blog']],
        ]);

        $this->postJson("/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/tap", [
            'destination_url' => 'https://example.com/blog',
            'item_id'         => 'abc12345',
        ])->assertSuccessful();

        $click = LinkClick::where('link_id', $bio->id)->first();
        $this->assertNotNull($click);
        $this->assertSame('abc12345', $click->block_item_id);
        $this->assertSame('mobile_app', $click->source);
    }

    public function test_public_page_renders_tracked_item_hrefs(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'layout' => 'list',
            'items'  => [
                ['id' => 'abc12345', 'text' => 'Portfolio', 'url' => 'https://example.com/portfolio'],
            ],
        ]);

        $html = $this->get('/' . $bio->alias)->assertOk()->getContent();
        $this->assertStringContainsString("/{$bio->alias}/b/{$block->id}?to=", $html);
        $this->assertStringContainsString('item=abc12345', $html);
        // Raw untracked href must be gone from the anchor.
        $this->assertStringNotContainsString('href="https://example.com/portfolio"', $html);
    }

    public function test_text_divider_layout_and_alignment_render(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $this->makeGroupBlock($bio, [
            'layout' => 'text_divider',
            'align'  => 'right',
            'items'  => [
                ['id' => 'abc12345', 'text' => 'Collabs', 'url' => 'https://example.com/collabs'],
            ],
        ]);

        $html = $this->get('/' . $bio->alias)->assertOk()->getContent();
        $this->assertStringContainsString('data-ltg-layout="text_divider"', $html);
        $this->assertStringContainsString('text-right', $html);
        $this->assertStringContainsString('Collabs', $html);
    }

    public function test_variant_style_hook_overrides_layout(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $this->makeGroupBlock($bio, [
            'layout' => 'list',
            '_style' => ['_ltg_layout' => 'text_divider', '_ltg_align' => 'center'],
            'items'  => [
                ['id' => 'abc12345', 'text' => 'Merch', 'url' => 'https://example.com/merch'],
            ],
        ]);

        $html = $this->get('/' . $bio->alias)->assertOk()->getContent();
        $this->assertStringContainsString('data-ltg-layout="text_divider"', $html);
        $this->assertStringContainsString('text-center', $html);
    }

    public function test_legacy_block_without_new_keys_renders_list_layout(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $this->makeGroupBlock($bio, [
            // Legacy shape: no layout/align/ids at all.
            'items' => [
                ['text' => 'My website', 'url' => 'https://example.com', 'description' => 'Home'],
            ],
        ]);

        $html = $this->get('/' . $bio->alias)->assertOk()->getContent();
        $this->assertStringContainsString('My website', $html);
        $this->assertStringNotContainsString('data-ltg-layout="text_divider"', $html);
    }

    public function test_sanitizer_accepts_new_style_hooks_and_strips_junk(): void
    {
        $clean = BlockStyleSanitizer::sanitize([
            '_ltg_layout' => 'text_divider',
            '_ltg_align'  => 'right',
        ]);
        $this->assertSame('text_divider', $clean['_ltg_layout'] ?? null);
        $this->assertSame('right', $clean['_ltg_align'] ?? null);

        $dirty = BlockStyleSanitizer::sanitize([
            '_ltg_layout' => '<script>alert(1)</script>',
        ]);
        $this->assertSame('scriptalert1script', $dirty['_ltg_layout'] ?? null);
    }

    public function test_save_mints_stable_item_ids_and_validates_layout(): void
    {
        $user  = User::factory()->create();
        $bio   = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, ['layout' => 'list', 'items' => []]);

        $this->actingAs($user)
            ->put(route('user.links.blocks.update', ['link' => $bio->id, 'block' => $block->id]), [
                'settings' => [
                    'layout' => 'bogus_layout',
                    'align'  => 'diagonal',
                    'items'  => [
                        ['text' => 'Collabs', 'url' => 'https://example.com/collabs'],
                        ['id' => 'fixed123', 'text' => 'Blog', 'url' => 'https://example.com/blog'],
                    ],
                ],
            ]);

        $block->refresh();
        $s = $block->settings;
        $this->assertSame('list', $s['layout']);
        $this->assertSame('left', $s['align']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{6,16}$/', $s['items'][0]['id']);
        $this->assertSame('fixed123', $s['items'][1]['id']);

        // Re-save: existing ids must be preserved. Clear the workspace
        // binding leaked by the first request or the Link global scope
        // filters the (workspace-less) fixture link away and 404s.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');
        $firstId = $s['items'][0]['id'];
        $resp2 = $this->actingAs($user)
            ->put(route('user.links.blocks.update', ['link' => $bio->id, 'block' => $block->id]), [
                'settings' => [
                    'layout' => 'text_divider',
                    'align'  => 'right',
                    'items'  => [
                        ['id' => $firstId, 'text' => 'Collabs', 'url' => 'https://example.com/collabs'],
                        ['id' => 'fixed123', 'text' => 'Blog', 'url' => 'https://example.com/blog'],
                    ],
                ],
            ]);
        $resp2->assertStatus(302);
        $block->refresh();
        $this->assertSame('text_divider', $block->settings['layout']);
        $this->assertSame('right', $block->settings['align']);
        $this->assertSame($firstId, $block->settings['items'][0]['id']);
    }

    public function test_variant_catalog_lists_new_link_group_variants(): void
    {
        $keys = BlockVariantCatalog::validKeys('link_tree_group');
        foreach (['ltg_text_divider_right', 'ltg_text_divider_left', 'ltg_text_divider_center', 'ltg_grid_tiles'] as $k) {
            $this->assertContains($k, $keys);
        }
        $v = BlockVariantCatalog::find('link_tree_group', 'ltg_text_divider_right');
        $this->assertSame('text_divider', $v['style']['_ltg_layout'] ?? null);
        $this->assertSame('right', $v['style']['_ltg_align'] ?? null);
    }

    // ── Task #6589 — three new curated styles ─────────────────────────

    public function test_variant_catalog_lists_task_6589_styles(): void
    {
        $keys = BlockVariantCatalog::validKeys('link_tree_group');
        $expected = [
            'ltg_outline_pills'     => 'outline_pills',
            'ltg_outline_pills_ink' => 'outline_pills',
            'ltg_washi_tape'        => 'washi_tape',
            'ltg_washi_tape_sage'   => 'washi_tape',
            'ltg_tile_grid_alt'     => 'tile_grid_alt',
            'ltg_tile_grid_alt_dark'=> 'tile_grid_alt',
        ];
        foreach ($expected as $key => $layout) {
            $this->assertContains($key, $keys);
            $v = BlockVariantCatalog::find('link_tree_group', $key);
            $this->assertSame($layout, $v['style']['_ltg_layout'] ?? null, $key);
            // Sanitizer must round-trip the layout hook, not strip it.
            $clean = BlockStyleSanitizer::sanitize($v['style']);
            $this->assertSame($layout, $clean['_ltg_layout'] ?? null, $key);
        }
    }

    public function test_new_layouts_render_on_public_page(): void
    {
        $user = User::factory()->create();
        $bio  = $this->makeBiolink($user);
        $items = [
            ['id' => 'aaaa1111', 'text' => 'Shop', 'url' => 'https://example.com/shop'],
            ['id' => 'bbbb2222', 'text' => 'About', 'url' => 'https://example.com/about'],
            ['id' => 'cccc3333', 'text' => 'Contact', 'url' => 'https://example.com/contact'],
        ];

        foreach (['outline_pills', 'washi_tape', 'tile_grid_alt'] as $layout) {
            $block = $this->makeGroupBlock($bio, [
                'items'  => $items,
                '_style' => ['_ltg_layout' => $layout],
            ]);
            app()->forgetInstance('current_workspace');
            app()->forgetInstance('workspace_owner');
            $resp = $this->get('/' . $bio->alias);
            $resp->assertStatus(200);
            $resp->assertSee('data-ltg-layout="' . $layout . '"', false);
            // Every item routes through the tracked redirect with its id.
            $resp->assertSee('item=aaaa1111', false);
            $resp->assertSee('item=cccc3333', false);
            $block->delete();
        }
    }

    public function test_editor_form_surfaces_per_item_click_counts(): void
    {
        $user = User::factory()->create();
        $bio  = $this->makeBiolink($user);
        $block = $this->makeGroupBlock($bio, [
            'items' => [
                ['id' => 'aaaa1111', 'text' => 'Shop', 'url' => 'https://example.com/shop'],
                ['id' => 'bbbb2222', 'text' => 'About', 'url' => 'https://example.com/about'],
            ],
        ]);
        foreach (['aaaa1111', 'aaaa1111', 'bbbb2222'] as $itemId) {
            LinkClick::create([
                'link_id'       => $bio->id,
                'alias'         => $bio->alias,
                'block_id'      => $block->id,
                'block_type'    => 'link_tree_group',
                'block_item_id' => $itemId,
                'is_bot'        => false,
                'clicked_at'    => now(),
            ]);
        }

        $html = view('user.links.partials.block-settings-form', [
            'block' => $block,
            'link'  => $bio,
        ])->render();

        // The Alpine items payload carries the per-item counts.
        $this->assertStringContainsString('"clicks":2', $html);
        $this->assertStringContainsString('"clicks":1', $html);
    }
}
