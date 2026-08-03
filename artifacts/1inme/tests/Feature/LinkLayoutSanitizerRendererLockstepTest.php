<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockStyleSanitizer;
use App\Modules\User\Support\BlockVariantCatalog;
use Tests\TestCase;

/**
 * Task #6606 — lockstep guard for button-style link layouts.
 *
 * Every new `link_layout` token must be added to
 * BlockStyleSanitizer::LINK_LAYOUTS or the style is silently stripped on
 * save and the block quietly reverts to the default button — a regression
 * class that has bitten before. This test walks every link_buttons /
 * link_shapes catalog variant (via BlockVariantCatalog::forType('link')),
 * runs its style payload through BlockStyleSanitizer::sanitize(), and
 * asserts the variant's `link_layout` survives. It then renders
 * common/blocks/link.blade.php once per allowed layout token and asserts
 * each one produces markup distinct from the default button render (i.e.
 * the renderer actually has a branch for the token).
 *
 * Pure in-memory: no database rows are created.
 */
class LinkLayoutSanitizerRendererLockstepTest extends TestCase
{
    /**
     * Every catalog variant for the `link` block family whose style
     * carries a non-empty `link_layout` must survive sanitize() intact.
     * Includes hidden/disabled variants (forType(..., false)) — blocks
     * already wearing them still re-sanitize on save.
     */
    public function test_every_catalog_link_layout_survives_sanitizer(): void
    {
        $variants = BlockVariantCatalog::forType('link', false);
        $this->assertNotEmpty($variants, 'forType(link) must return variants');

        $seenLayouts = 0;
        foreach ($variants as $variant) {
            $style  = $variant['style'] ?? [];
            $layout = $style['link_layout'] ?? '';
            if (!is_array($style) || $layout === '') continue;
            $seenLayouts++;

            $this->assertContains($layout, BlockStyleSanitizer::LINK_LAYOUTS,
                "Catalog variant `{$variant['key']}` carries link_layout `{$layout}` "
                . 'which is missing from BlockStyleSanitizer::LINK_LAYOUTS — it will be '
                . 'silently stripped on save.');

            $clean = BlockStyleSanitizer::sanitize($style);
            $this->assertSame($layout, $clean['link_layout'] ?? null,
                "link_layout `{$layout}` (variant `{$variant['key']}`) must survive "
                . 'BlockStyleSanitizer::sanitize()');
        }

        $this->assertGreaterThan(0, $seenLayouts,
            'Expected at least one link catalog variant with a non-empty link_layout '
            . '— if the catalog stopped carrying layouts this guard went vacuous.');
    }

    /**
     * Every allowlisted layout token must have a dedicated renderer branch
     * in common/blocks/link.blade.php: rendering the partial with the token
     * set must produce markup distinct from the default button render.
     * A token that falls through to the @else default means the renderer
     * branch was forgotten and the "style" does nothing on the public page.
     */
    public function test_every_allowed_link_layout_renders_non_default_markup(): void
    {
        $bladeSource = file_get_contents(
            resource_path('views/common/blocks/link.blade.php')
        );

        $defaultHtml = $this->renderLinkBlock('');

        foreach (BlockStyleSanitizer::LINK_LAYOUTS as $layout) {
            $this->assertStringContainsString("'{$layout}'", $bladeSource,
                "Renderer common/blocks/link.blade.php has no branch mentioning "
                . "link_layout token `{$layout}` — the layout would silently fall "
                . 'back to the default button.');

            $html = $this->renderLinkBlock($layout);
            $this->assertNotSame('', trim($html),
                "link_layout `{$layout}` rendered empty markup");
            $this->assertNotSame($defaultHtml, $html,
                "link_layout `{$layout}` rendered the DEFAULT button markup — "
                . 'its renderer branch is missing or unreachable.');
        }
    }

    /** Render common/blocks/link.blade.php for one layout, in memory. */
    private function renderLinkBlock(string $layout): string
    {
        $settings = [
            'text'        => 'Lockstep Guard Link',
            'url'         => 'https://example.com/guard',
            'description' => 'Distinct-markup probe',
            'icon'        => 'fas fa-star',
            'thumbnail'   => 'https://example.com/thumb.png',
            '_style'      => array_filter([
                'link_layout' => $layout,
            ]),
        ];

        $block = new BiolinkBlock([
            'type'     => 'link',
            'settings' => $settings,
        ]);

        return view('common.blocks.link', [
            'block'     => $block,
            's'         => $settings,
            'link'      => null,
            'btnInline' => '',
            'fontColor' => '#ffffff',
        ])->render();
    }
}
