<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Services\Biolink\AiBiolinkBuilderService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the AI Biolink Page Builder's profile-design selection (Task #1740
 * follow-up): the ready-made identity designs the AI may pick must stay in
 * sync with the `profile_identity` catalog bundle, and a picked design must
 * materialise as the correct `_style._profile_layout` plus variant stamps on
 * the built block — with unknown keys falling back to the default look.
 *
 * Pure unit tests: they exercise the static catalog and the private
 * `buildBlock()` builder (via reflection) without touching the database.
 */
class AiBiolinkBuilderProfileDesignTest extends TestCase
{
    /** Invoke the service's private buildBlock() without booting its deps. */
    private function buildBlock(string $type, array $aiSettings): array
    {
        $service = (new ReflectionClass(AiBiolinkBuilderService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod($service, 'buildBlock');
        $method->setAccessible(true);

        return $method->invoke($service, $type, $aiSettings);
    }

    /** The raw `profile_identity` bundle straight from the catalog source. */
    private function profileIdentityBundle(): array
    {
        $method = new ReflectionMethod(BlockVariantCatalog::class, 'bundles');
        $method->setAccessible(true);

        return $method->invoke(null)['profile_identity'] ?? [];
    }

    public function test_profile_designs_returns_ten_identity_designs_in_sync_with_bundle(): void
    {
        $designs = AiBiolinkBuilderService::profileDesigns();

        // Every ready-made identity look, keyed by variant key. Task #1745
        // grew the original 10 to 16, and later tasks (#5929 Paper Collage)
        // keep extending the bundle, so assert the floor + naming convention
        // here and exact parity against the live bundle below.
        $this->assertGreaterThanOrEqual(21, count($designs));
        foreach (array_keys($designs) as $key) {
            $this->assertStringStartsWith('identity_', $key, "Unexpected design key: {$key}");
        }

        // Stay in lockstep with the profile_identity bundle: every bundled
        // variant carrying a structural `_profile_layout` token must appear,
        // and nothing else may. This is what breaks loudly if a future
        // catalog edit adds/removes a design or drops the layout token.
        $bundle = $this->profileIdentityBundle();
        $expected = [];
        foreach ($bundle as $variant) {
            $layout = $variant['style']['_profile_layout'] ?? '';
            if (is_string($layout) && $layout !== '') {
                $expected[$variant['key']] = (string) ($variant['name'] ?? $variant['key']);
            }
        }

        $this->assertSame($expected, $designs);
        $this->assertContains('Paper Collage', $designs, 'Task #5929 Paper Collage design must be pickable by the AI builder');
    }

    public function test_build_block_applies_chosen_profile_design_style_and_variant_stamps(): void
    {
        $block = $this->buildBlock('profile_card_v1', [
            'design' => 'identity_founder',
            'name'   => 'Jane Founder',
        ]);

        $variant = BlockVariantCatalog::find('profile_card_v1', 'identity_founder');
        $this->assertNotNull($variant);

        $style = $block['settings']['_style'];

        // The structural layout token from the chosen variant is applied…
        $this->assertSame('founder', $style['_profile_layout']);
        $this->assertSame($variant['style']['_profile_layout'], $style['_profile_layout']);

        // …and the variant bookkeeping is stamped exactly like applyVariant.
        $this->assertSame('identity_founder', $style['_variant']);
        $this->assertSame(BlockVariantCatalog::version(), $style['_variant_version']);

        // The skin keys from the variant are merged in too.
        $this->assertSame($variant['style']['bg_color'], $style['bg_color']);

        // `design` is a meta hint, not a real content field — it must not leak.
        $this->assertArrayNotHasKey('design', $block['settings']);
        $this->assertSame('Jane Founder', $block['settings']['name']);
    }

    public function test_build_block_ignores_unknown_design_and_falls_back_to_default(): void
    {
        $block = $this->buildBlock('profile_card_v1', [
            'design' => 'identity_does_not_exist',
            'name'   => 'Nobody',
        ]);

        $style = $block['settings']['_style'];

        // No variant matched, so the default first-paint style is left intact:
        // the layout token and variant stamps stay at their empty defaults
        // (a real variant is what overwrites them with a concrete look).
        $this->assertSame('', $style['_profile_layout']);
        $this->assertSame('', $style['_variant']);
        $this->assertSame(0, $style['_variant_version']);

        // The bogus hint is still stripped from the saved settings.
        $this->assertArrayNotHasKey('design', $block['settings']);

        // Baseline structural defaults still seeded.
        $this->assertSame(BiolinkBlock::STYLE_DEFAULTS['bg_color'], $style['bg_color']);
    }

    public function test_build_block_preserves_new_profile_fields_into_settings(): void
    {
        $socials = [
            ['name' => 'instagram', 'url' => 'https://instagram.com/jane'],
            ['name' => 'x', 'url' => 'https://x.com/jane'],
        ];

        $block = $this->buildBlock('profile_card_v1', [
            'design'    => 'identity_classic',
            'name'      => 'Jane Doe',
            'verified'  => false,
            'location'  => 'Berlin, Germany',
            'website'   => 'https://janedoe.example',
            'cta_label' => 'Hire me',
            'cta_url'   => 'https://janedoe.example/contact',
            'socials'   => $socials,
        ]);

        $settings = $block['settings'];

        // AI-supplied values win over the placeholder defaults and survive
        // intact into the built block's settings.
        $this->assertSame('Jane Doe', $settings['name']);
        $this->assertFalse($settings['verified']);
        $this->assertSame('Berlin, Germany', $settings['location']);
        $this->assertSame('https://janedoe.example', $settings['website']);
        $this->assertSame('Hire me', $settings['cta_label']);
        $this->assertSame('https://janedoe.example/contact', $settings['cta_url']);
        $this->assertSame($socials, $settings['socials']);

        // Real content means the placeholder flags are cleared.
        $this->assertArrayNotHasKey('_placeholder', $settings);

        // And the chosen design still applied alongside the preserved fields.
        $this->assertSame('classic_creator', $settings['_style']['_profile_layout']);
    }
}
