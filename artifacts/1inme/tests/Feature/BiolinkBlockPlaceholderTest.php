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
            BlockVariantCatalog::VERSION,
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
}
