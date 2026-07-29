<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #1204 — feature coverage for the first-paint defaults flow:
 * store() seeds, update() clears the placeholder flag, applyVariant()
 * fully replaces the seeded _style.
 */
class BiolinkBlockPlaceholderTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeBiolink(User $owner): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    private function seedPlaceholderBlock(User $owner, Link $link, string $type): BiolinkBlock
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => $type]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    public function test_store_seeds_placeholder_content_style_and_flag_for_new_block(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        $block = $this->seedPlaceholderBlock($owner, $link, 'link');
        $settings = $block->settings ?? [];

        // Content seeded from BlockDefaults / getDefaultSettings('link').
        $this->assertSame('My Link', $settings['text'] ?? null);
        $this->assertNotEmpty($settings['url'] ?? '');
        $this->assertStringStartsWith('http', (string) ($settings['url'] ?? ''));

        // Placeholder bookkeeping.
        $this->assertTrue((bool) ($settings['_placeholder'] ?? false));
        $this->assertIsArray($settings['_placeholder_seed'] ?? null);
        $seed = $settings['_placeholder_seed'];
        $this->assertSame($settings['text'], $seed['text'] ?? null);
        $this->assertSame($settings['url'],  $seed['url']  ?? null);
        $this->assertArrayNotHasKey('_placeholder', $seed);
        $this->assertArrayNotHasKey('_style', $seed);
        $this->assertArrayNotHasKey('_placeholder_seed', $seed);

        // Style seeded from BlockDefaults::styleForType('link') merged
        // over STYLE_DEFAULTS.
        $this->assertIsArray($settings['_style'] ?? null);
        $this->assertNotEmpty($settings['_style']);
        $this->assertSame('card', $settings['_style']['display_mode'] ?? null);
        $this->assertArrayHasKey('padding', $settings['_style']);
        $this->assertArrayHasKey('border_radius', $settings['_style']);
    }

    public function test_update_clears_placeholder_flag_when_creator_edits_seeded_field(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->seedPlaceholderBlock($owner, $link, 'link');

        $this->assertTrue((bool) ($block->settings['_placeholder'] ?? false));
        $this->assertIsArray($block->settings['_placeholder_seed'] ?? null);

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", [
                'settings' => [
                    'url'  => 'https://realsite.example.com',
                    'text' => 'Real copy from the creator',
                    'icon' => '',
                    'thumbnail' => '',
                ],
            ]);

        $resp->assertOk();
        $fresh = $block->fresh()->settings ?? [];

        $this->assertSame('Real copy from the creator', $fresh['text'] ?? null);
        $this->assertSame('https://realsite.example.com', $fresh['url'] ?? null);

        // Both placeholder bookkeeping keys must be gone.
        $this->assertArrayNotHasKey('_placeholder', $fresh);
        $this->assertArrayNotHasKey('_placeholder_seed', $fresh);

        // Style + visibility must survive the update.
        $this->assertIsArray($fresh['_style'] ?? null);
        $this->assertNotEmpty($fresh['_style']);
        $this->assertIsArray($fresh['_visibility'] ?? null);
    }

    public function test_apply_variant_fully_replaces_seeded_style(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        // Use a `heading` block: its seeded style ships `font_size: 28`
        // and `font_weight: 700`, neither of which the universal
        // `classic` variant sets. If applyVariant() merged into the
        // existing _style instead of replacing it, those seeded font
        // tokens would survive — that's the regression this test pins.
        $block = $this->seedPlaceholderBlock($owner, $link, 'heading');

        $seededStyle = $block->settings['_style'] ?? [];
        $this->assertSame('28',  (string) ($seededStyle['font_size']   ?? ''),
            'pre-condition: heading seed must populate font_size=28');
        $this->assertSame('700', (string) ($seededStyle['font_weight'] ?? ''),
            'pre-condition: heading seed must populate font_weight=700');
        $this->assertTrue(empty($seededStyle['bg_color']),
            'pre-condition: heading seed must not carry bg_color');
        $this->assertSame('', $seededStyle['_variant'] ?? '',
            'pre-condition: heading seed must not carry a curated variant key');

        $variantKey = 'classic';
        $variant = BlockVariantCatalog::find($block->type, $variantKey);
        $this->assertNotNull($variant, "fixture relies on the '{$variantKey}' variant existing for {$block->type}");
        $this->assertArrayNotHasKey('font_size',   $variant['style'],
            'fixture relies on classic variant NOT setting font_size');
        $this->assertArrayNotHasKey('font_weight', $variant['style'],
            'fixture relies on classic variant NOT setting font_weight');

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/apply-variant", [
                'variant' => $variantKey,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        $newStyle = $block->fresh()->settings['_style'] ?? [];

        // Variant payload landed.
        $this->assertNotEmpty($newStyle['bg_color']     ?? '');
        $this->assertNotEmpty($newStyle['border_color'] ?? '');
        $this->assertSame($variantKey, $newStyle['_variant'] ?? null);
        $this->assertSame(
            BlockVariantCatalog::version(),
            (int) ($newStyle['_variant_version'] ?? 0)
        );

        // Replacement, not merge: seeded font tokens must be gone
        // because the variant didn't set them and STYLE_DEFAULTS
        // ships them as '' (sanitizer drops empty values).
        $this->assertArrayNotHasKey('font_size', $newStyle,
            'applyVariant() must replace _style, not merge — seeded font_size leaked through');
        $this->assertArrayNotHasKey('font_weight', $newStyle,
            'applyVariant() must replace _style, not merge — seeded font_weight leaked through');

        // Per-key proof of replacement: every key in the new _style
        // either came from the variant payload or from STYLE_DEFAULTS,
        // never from the seeded style.
        $variantKeys      = array_keys($variant['style']);
        $styleDefaultKeys = array_keys(BiolinkBlock::STYLE_DEFAULTS);
        $allowedKeys      = array_unique(array_merge($variantKeys, $styleDefaultKeys));
        foreach (array_keys($newStyle) as $k) {
            $this->assertContains($k, $allowedKeys,
                "applyVariant() produced unexpected style key '{$k}' — likely seed residue");
        }
    }

    /**
     * Overlay distinctive handcrafted style overrides on top of the
     * already-seeded `_style`. We mutate the model directly so the
     * test is independent of the update() pipeline (which has its own
     * coverage in test_update_clears_placeholder_flag_…).
     *
     * Returned settings array is what gets persisted, so callers can
     * assert against it later as the "pre-variant baseline".
     */
    private function overlayHandcraftedStyle(BiolinkBlock $block, array $overrides): array
    {
        $settings = $block->settings ?? [];
        $style    = $settings['_style'] ?? [];
        foreach ($overrides as $k => $v) {
            $style[$k] = $v;
        }
        $settings['_style'] = $style;
        $block->settings = $settings;
        $block->save();
        return $style;
    }

    public function test_apply_variant_to_all_replaces_style_on_siblings_and_snapshots_each(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);

        // Two heading siblings, each with a distinct handcrafted look.
        // We pick keys the `classic` heading variant does NOT touch
        // (font_size / font_weight) plus one it DOES touch (padding)
        // — so the snapshot can prove the original padding was kept
        // while the live `_style` proves the variant overwrote it.
        $blockA = $this->seedPlaceholderBlock($owner, $link, 'heading');
        $blockB = $this->seedPlaceholderBlock($owner, $link, 'heading');

        $handcraftedA = $this->overlayHandcraftedStyle($blockA, [
            'font_size'   => '40',
            'font_weight' => '600',
            'padding'     => '24',
        ]);
        $handcraftedB = $this->overlayHandcraftedStyle($blockB, [
            'font_size'   => '48',
            'font_weight' => '500',
            'padding'     => '32',
        ]);

        // Pre-conditions: neither block carries a curated variant yet,
        // and the pre-existing handcrafted overlay is on disk.
        $this->assertSame('', $handcraftedA['_variant'] ?? '');
        $this->assertSame('', $handcraftedB['_variant'] ?? '');
        $this->assertSame('40', $handcraftedA['font_size']);
        $this->assertSame('32', $handcraftedB['padding']);

        $variantKey = 'classic';
        $variant = BlockVariantCatalog::find('heading', $variantKey);
        $this->assertNotNull($variant, "fixture relies on '{$variantKey}' variant for heading");
        $this->assertArrayNotHasKey('font_size',   $variant['style'],
            'fixture relies on classic variant NOT setting font_size');
        $this->assertArrayNotHasKey('font_weight', $variant['style'],
            'fixture relies on classic variant NOT setting font_weight');
        $this->assertArrayHasKey('padding', $variant['style'],
            'fixture relies on classic variant setting padding so we can prove replacement');

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$blockA->id}/apply-variant-to-all", [
                'variant' => $variantKey,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));
        $this->assertSame(2, (int) $resp->json('updated'),
            'applyVariantToAll() must touch both heading siblings');

        foreach ([
            ['block' => $blockA, 'pre' => $handcraftedA],
            ['block' => $blockB, 'pre' => $handcraftedB],
        ] as $row) {
            $fresh   = $row['block']->fresh()->settings ?? [];
            $style   = $fresh['_style'] ?? [];
            $snap    = $fresh['_style_custom_snapshot'] ?? null;
            $pre     = $row['pre'];

            // Variant payload landed on every sibling.
            $this->assertSame($variantKey, $style['_variant'] ?? null,
                'applyVariantToAll() must stamp the chosen variant key on each sibling');
            $this->assertSame(
                BlockVariantCatalog::version(),
                (int) ($style['_variant_version'] ?? 0),
                'applyVariantToAll() must stamp the catalog VERSION on each sibling'
            );
            $this->assertNotEmpty($style['bg_color']     ?? '',
                'variant bg_color should be present on each sibling');
            $this->assertNotEmpty($style['border_color'] ?? '',
                'variant border_color should be present on each sibling');
            $this->assertSame('16', (string) ($style['padding'] ?? ''),
                'variant padding must overwrite the handcrafted padding on each sibling');

            // Replacement (not merge): keys the variant didn't set must
            // be gone — even though the sibling's pre-variant style
            // explicitly populated them.
            $this->assertArrayNotHasKey('font_size', $style,
                'applyVariantToAll() must replace _style — handcrafted font_size leaked through');
            $this->assertArrayNotHasKey('font_weight', $style,
                'applyVariantToAll() must replace _style — handcrafted font_weight leaked through');

            // Each sibling's own pre-variant style is snapshotted.
            $this->assertIsArray($snap,
                'applyVariantToAll() must snapshot the pre-variant handcrafted style of each sibling');
            $this->assertSame($pre['font_size'],   $snap['font_size']   ?? null,
                'snapshot must preserve this sibling\'s handcrafted font_size');
            $this->assertSame($pre['font_weight'], $snap['font_weight'] ?? null,
                'snapshot must preserve this sibling\'s handcrafted font_weight');
            $this->assertSame($pre['padding'],     $snap['padding']     ?? null,
                'snapshot must preserve this sibling\'s pre-variant padding');
        }
    }

    public function test_restore_custom_style_replaces_style_with_snapshot_and_clears_variant(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->seedPlaceholderBlock($owner, $link, 'heading');

        $seededStyle = $block->settings['_style'] ?? [];
        $this->assertSame('28',  (string) ($seededStyle['font_size']   ?? ''));
        $this->assertSame('700', (string) ($seededStyle['font_weight'] ?? ''));

        // Apply a variant first so the pre-variant snapshot exists and
        // the live `_style` carries variant residue we expect to be
        // wiped by the restore.
        $variantKey = 'classic';
        $applyResp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/apply-variant", [
                'variant' => $variantKey,
            ]);
        $applyResp->assertOk();

        $afterApply = $block->fresh()->settings ?? [];
        $this->assertSame($variantKey, $afterApply['_style']['_variant'] ?? null,
            'pre-condition: variant must be stamped before restore is meaningful');
        $this->assertNotEmpty($afterApply['_style']['bg_color'] ?? '',
            'pre-condition: variant residue (bg_color) must be present before restore');
        $this->assertIsArray($afterApply['_style_custom_snapshot'] ?? null,
            'pre-condition: snapshot must have been captured by applyVariant');

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/restore-custom-style");

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));

        $fresh = $block->fresh()->settings ?? [];
        $style = $fresh['_style'] ?? [];

        // Variant bookkeeping is cleared. `_variant` is dropped by the
        // sanitizer (empty string isn't persisted); `_variant_version`
        // is explicitly forced to 0.
        $this->assertArrayNotHasKey('_variant', $style,
            'restoreCustomStyle() must clear the curated variant key');
        $this->assertSame(0, (int) ($style['_variant_version'] ?? -1),
            'restoreCustomStyle() must reset _variant_version to 0');

        // Snapshotted handcrafted tokens are back.
        $this->assertSame('28',  (string) ($style['font_size']   ?? ''),
            'restoreCustomStyle() must restore the handcrafted font_size');
        $this->assertSame('700', (string) ($style['font_weight'] ?? ''),
            'restoreCustomStyle() must restore the handcrafted font_weight');

        // Variant residue must be fully gone — the restored style is
        // built from the snapshot, which never had bg_color / border_color.
        $this->assertArrayNotHasKey('bg_color', $style,
            'restoreCustomStyle() must replace _style — variant bg_color leaked through');
        $this->assertArrayNotHasKey('border_color', $style,
            'restoreCustomStyle() must replace _style — variant border_color leaked through');

        // Snapshot itself is intentionally kept on disk so the creator
        // can round-trip between variants and their custom look.
        $this->assertIsArray($fresh['_style_custom_snapshot'] ?? null,
            'restoreCustomStyle() must keep the snapshot for future round-trips');
    }

    public function test_reset_style_falls_back_to_style_defaults_and_drops_snapshot(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->seedPlaceholderBlock($owner, $link, 'heading');

        // Apply a variant first so we have BOTH variant residue in `_style`
        // AND a populated `_style_custom_snapshot` to prove resetStyle
        // wipes both surfaces.
        $applyResp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/apply-variant", [
                'variant' => 'classic',
            ]);
        $applyResp->assertOk();

        $afterApply = $block->fresh()->settings ?? [];
        $this->assertNotEmpty($afterApply['_style']['bg_color'] ?? '',
            'pre-condition: variant residue must be present before reset');
        $this->assertIsArray($afterApply['_style_custom_snapshot'] ?? null,
            'pre-condition: snapshot must be present before reset');

        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks/{$block->id}/reset-style");

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));
        $this->assertSame(1, (int) $resp->json('updated'),
            'resetStyle() without apply_to_all must report a single update');

        $fresh = $block->fresh()->settings ?? [];
        $style = $fresh['_style'] ?? [];

        // Both the variant payload and the seeded heading tokens are gone.
        $this->assertArrayNotHasKey('font_size', $style,
            'resetStyle() must drop the seeded font_size when falling back to STYLE_DEFAULTS');
        $this->assertArrayNotHasKey('font_weight', $style,
            'resetStyle() must drop the seeded font_weight when falling back to STYLE_DEFAULTS');
        $this->assertArrayNotHasKey('bg_color', $style,
            'resetStyle() must drop variant residue (bg_color)');
        $this->assertArrayNotHasKey('border_color', $style,
            'resetStyle() must drop variant residue (border_color)');
        $this->assertArrayNotHasKey('_variant', $style,
            'resetStyle() must drop the curated variant key');

        // The fallback equals the sanitized STYLE_DEFAULTS payload.
        // Spot-check the always-on defaults that survive the sanitizer
        // (display_mode is 'card', shadow_color is '#00000040', etc.).
        $this->assertSame('card',      $style['display_mode'] ?? null);
        $this->assertSame('normal',    $style['font_style']   ?? null);
        $this->assertSame('#00000040', $style['shadow_color'] ?? null);
        $this->assertSame(100,         (int) ($style['bg_opacity'] ?? -1));
        $this->assertSame(12,          (int) ($style['grid_span']  ?? -1));

        // Snapshot is dropped so the user gets a truly clean slate.
        $this->assertArrayNotHasKey('_style_custom_snapshot', $fresh,
            'resetStyle() must drop _style_custom_snapshot for a clean slate');
    }
}
