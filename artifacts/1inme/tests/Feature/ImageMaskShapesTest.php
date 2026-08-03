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
 * Task #6575 — expanded image mask shape library + clickable masked images.
 *
 * Covers: sanitizer acceptance of the new mask_shape values, the inline
 * clip-path/border-radius output, public-page rendering (clip + anchor
 * wrapping when a link URL is set), and the new "Mask · …" variant
 * catalog entries.
 */
class ImageMaskShapesTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_SHAPES = [
        'oval', 'pill', 'triangle', 'pentagon', 'semicircle',
        'wave', 'shield', 'scallop', 'cross',
    ];

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

    private function createImageBlock(User $owner, Link $link): BiolinkBlock
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'image']);

        $resp->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateBlock(User $owner, Link $link, BiolinkBlock $block, array $payload)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", $payload);
    }

    public function test_sanitizer_accepts_every_new_mask_shape(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createImageBlock($owner, $link);

        foreach (self::NEW_SHAPES as $shape) {
            $this->updateBlock($owner, $link, $block, [
                'settings' => [
                    'url'          => 'https://example.com/pic.jpg',
                    '_image_style' => ['mask_shape' => $shape],
                ],
            ])->assertOk();

            $this->assertSame(
                $shape,
                $block->fresh()->settings['_image_style']['mask_shape'] ?? null,
                "mask_shape '{$shape}' did not survive the sanitizer"
            );
        }
    }

    public function test_sanitizer_rejects_unknown_mask_shape(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createImageBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'url'          => 'https://example.com/pic.jpg',
                '_image_style' => ['mask_shape' => 'dodecahedron'],
            ],
        ])->assertOk();

        $this->assertNotSame(
            'dodecahedron',
            $block->fresh()->settings['_image_style']['mask_shape'] ?? null
        );
    }

    public function test_inline_style_builder_emits_clip_path_for_polygon_shapes(): void
    {
        foreach (['triangle', 'pentagon', 'semicircle', 'wave', 'shield', 'scallop', 'cross'] as $shape) {
            $css = BiolinkBlock::buildImageInlineStyle(['mask_shape' => $shape]);
            $this->assertStringContainsString('clip-path:polygon(', $css, $shape);
        }

        $this->assertStringContainsString(
            'clip-path:ellipse(',
            BiolinkBlock::buildImageInlineStyle(['mask_shape' => 'oval'])
        );

        // Pill is border-radius only — no clip so it tracks the aspect ratio.
        $pill = BiolinkBlock::buildImageInlineStyle(['mask_shape' => 'pill']);
        $this->assertStringContainsString('border-radius:999px', $pill);
        $this->assertStringNotContainsString('clip-path', $pill);
    }

    public function test_public_page_renders_masked_image_wrapped_in_tracked_anchor(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeBiolink($owner);
        $block = $this->createImageBlock($owner, $link);

        $this->updateBlock($owner, $link, $block, [
            'settings' => [
                'url'          => 'https://example.com/pic.jpg',
                '_image_style' => ['mask_shape' => 'shield'],
                '_link'        => ['url' => 'https://example.com/target'],
            ],
        ])->assertOk();

        // Public visitor request: drop the leaked workspace binding so the
        // catch-all /{alias} route resolves like a real guest hit.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $html = $this->get('/' . $link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('clip-path:polygon(0% 0%, 100% 0%, 100% 65%', $html);
        $this->assertStringContainsString(
            route('redirect.block', ['alias' => $link->alias, 'blockId' => $block->id]),
            $html
        );
    }

    public function test_variant_catalog_has_mask_variants_for_new_shapes(): void
    {
        $this->assertGreaterThanOrEqual(20, BlockVariantCatalog::VERSION);

        foreach (self::NEW_SHAPES as $shape) {
            $variant = BlockVariantCatalog::find('image', 'mask_' . $shape);
            $this->assertNotNull($variant, "missing image variant mask_{$shape}");
            $this->assertStringStartsWith('Mask ·', $variant['name']);
        }
    }
}
