<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\OnboardingSlide;
use Database\Seeders\OnboardingSlidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression coverage for OnboardingSlidesSeeder's image galleries.
 *
 * The mobile splash slider animates through each slide's `gallery_images`
 * (see OnboardingSlide::galleryUrls + the mobile `resolveImages` helper). The
 * seeder builds those galleries by auto-detecting `onboarding/<slug>.png`
 * plus the `_2` / `_3` variants on the public disk. That auto-detection is
 * silent: if a variant file is missing (a bad deploy, a renamed slug, or an
 * S3 sync gap) the slide simply drops back to a single static background — no
 * error, no warning — even though every intro slide is meant to cycle through
 * a multi-image gallery.
 *
 * These tests pin the contract loudly:
 *   1. The seeder builds a full multi-image gallery for EVERY slide slug — the
 *      four framing slides (welcome, platform, grow, get-started) AND the six
 *      personas — with the exact variant paths, in order (fake-disk driven so
 *      it is deterministic in CI regardless of where the real bytes live).
 *   2. A missing variant measurably shrinks the gallery, proving the seeder is
 *      genuinely file-driven and that such a regression is detectable.
 *   3. When the real onboarding assets are provisioned in the current
 *      environment, every expected variant file must actually be present in
 *      the storage location the seeder reads from — so a missing/renamed file
 *      fails loudly instead of silently collapsing a slide to a static image.
 *
 * Note on storage: the `public` disk is backed by S3 (see config/filesystems),
 * and the shipped images are gitignored, so a bare CI checkout has neither S3
 * credentials nor the bytes. Tests 1 and 2 therefore fake the disk; test 3 is
 * environment-aware and only asserts presence when the asset set is actually
 * provisioned locally (dev boxes and deploy targets), skipping otherwise.
 */
class OnboardingSlidesGalleryCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The full slide roster and the gallery each slug is expected to yield.
     * Kept explicit (rather than derived from the seeder) so a renamed slug,
     * a dropped slide, or a shrunk gallery shows up as a failing diff here.
     *
     * @var array<string, array<int, string>>
     */
    private const EXPECTED_GALLERY = [
        // ── Framing slides ──────────────────────────────────────────────
        'welcome'     => ['onboarding/welcome.png', 'onboarding/welcome_2.png', 'onboarding/welcome_3.png'],
        'platform'    => ['onboarding/platform.png', 'onboarding/platform_2.png', 'onboarding/platform_3.png'],
        'grow'        => ['onboarding/grow.png', 'onboarding/grow_2.png', 'onboarding/grow_3.png'],
        'get-started' => ['onboarding/get-started.png', 'onboarding/get-started_2.png', 'onboarding/get-started_3.png'],
        // ── Personas ────────────────────────────────────────────────────
        'creators'    => ['onboarding/creators.png', 'onboarding/creators_2.png', 'onboarding/creators_3.png'],
        'business'    => ['onboarding/business.png', 'onboarding/business_2.png', 'onboarding/business_3.png'],
        'freelancer'  => ['onboarding/freelancer.png', 'onboarding/freelancer_2.png', 'onboarding/freelancer_3.png'],
        'networker'   => ['onboarding/networker.png', 'onboarding/networker_2.png', 'onboarding/networker_3.png'],
        'students'    => ['onboarding/students.png', 'onboarding/students_2.png', 'onboarding/students_3.png'],
        'coaches'     => ['onboarding/coaches.png', 'onboarding/coaches_2.png', 'onboarding/coaches_3.png'],
    ];

    /** Every gallery is expected to cycle through this many images. */
    private const IMAGES_PER_SLIDE = 3;

    /**
     * Seed a faked public disk with every expected variant so the seeder's
     * auto-detection has real files to find.
     */
    private function fakePublicDiskWithAllVariants(): void
    {
        Storage::fake('public');
        foreach (self::EXPECTED_GALLERY as $paths) {
            foreach ($paths as $path) {
                Storage::disk('public')->put($path, 'fake-image-bytes');
            }
        }
    }

    public function test_seeder_builds_full_gallery_for_every_slide(): void
    {
        $this->fakePublicDiskWithAllVariants();

        (new OnboardingSlidesSeeder())->run();

        // The seeded slug set must match the expected roster exactly — this
        // catches a renamed or dropped slide, or an unlisted new one.
        $seededSlugs = OnboardingSlide::query()->pluck('slug')->sort()->values()->all();
        $expectedSlugs = collect(array_keys(self::EXPECTED_GALLERY))->sort()->values()->all();
        $this->assertSame(
            $expectedSlugs,
            $seededSlugs,
            'Seeded onboarding slugs drifted from the expected roster; update EXPECTED_GALLERY '
            . 'and confirm the matching images exist.'
        );

        foreach (self::EXPECTED_GALLERY as $slug => $expectedPaths) {
            $slide = OnboardingSlide::where('slug', $slug)->first();
            $this->assertNotNull($slide, "slide '{$slug}' was not seeded");

            $gallery = $slide->gallery_images;
            $this->assertIsArray(
                $gallery,
                "slide '{$slug}' has no gallery_images array — it would render a static background"
            );
            $this->assertCount(
                self::IMAGES_PER_SLIDE,
                $gallery,
                "slide '{$slug}' should cycle through exactly " . self::IMAGES_PER_SLIDE
                . ' gallery images, got ' . count($gallery) . ': ' . json_encode($gallery)
            );
            $this->assertSame(
                $expectedPaths,
                array_values($gallery),
                "slide '{$slug}' gallery paths (or their order) drifted from what the slider expects"
            );

            // galleryUrls() is what the API actually serves to the slider; it
            // must expose every gallery entry as an absolute URL.
            $this->assertCount(
                self::IMAGES_PER_SLIDE,
                $slide->galleryUrls(),
                "slide '{$slug}' galleryUrls() should expose all " . self::IMAGES_PER_SLIDE . ' images to the slider'
            );
        }
    }

    public function test_missing_variant_measurably_shrinks_the_gallery(): void
    {
        // Prove the seeder is genuinely file-driven: drop the `_3` variant of
        // one slide on the faked disk and confirm its gallery shrinks. This is
        // the exact silent regression the coverage is protecting against (a
        // missing file => a shorter, static-leaning gallery).
        $this->fakePublicDiskWithAllVariants();
        Storage::disk('public')->delete('onboarding/welcome_3.png');

        (new OnboardingSlidesSeeder())->run();

        $welcome = OnboardingSlide::where('slug', 'welcome')->firstOrFail();
        $this->assertSame(
            ['onboarding/welcome.png', 'onboarding/welcome_2.png'],
            array_values($welcome->gallery_images ?? []),
            'seeder should only include the variants that actually exist on disk'
        );
        $this->assertLessThan(
            self::IMAGES_PER_SLIDE,
            count($welcome->gallery_images ?? []),
            'a missing variant must measurably shrink the gallery'
        );
    }

    public function test_every_expected_variant_image_is_present_when_assets_are_provisioned(): void
    {
        // The seeder reads from the `public` disk, which is S3-backed in real
        // environments; the shipped bytes live under the public storage path
        // (storage/app/public/onboarding/…) before they are synced to S3. In a
        // bare CI checkout neither the S3 credentials nor the gitignored bytes
        // are present, so there is nothing to guard — skip cleanly. Wherever
        // the asset set IS provisioned (dev boxes, deploy targets), assert that
        // EVERY expected variant is present, failing loudly on any gap.
        $onboardingDir = storage_path('app/public/onboarding');
        $anyPresent = is_dir($onboardingDir) && glob($onboardingDir . '/*.png');

        if (! $anyPresent) {
            $this->markTestSkipped(
                'Onboarding image assets are not provisioned in this environment '
                . "({$onboardingDir} is empty/absent); nothing to guard here."
            );
        }

        $missing = [];
        foreach (self::EXPECTED_GALLERY as $slug => $paths) {
            foreach ($paths as $path) {
                $absolute = storage_path('app/public/' . $path);
                if (! is_file($absolute)) {
                    $missing[] = "{$path} (slide '{$slug}')";
                }
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Onboarding gallery variant image(s) missing from the storage location the seeder reads "
            . "from — the affected slide(s) would silently drop to a single static background instead "
            . "of cycling through a gallery:\n  - " . implode("\n  - ", $missing)
        );
    }
}
