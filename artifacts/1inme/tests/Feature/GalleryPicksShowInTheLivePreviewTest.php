<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Picking a gallery image changed nothing in the device preview.
 *
 * The draft-preview endpoint caches the in-progress form so the iframe can
 * render unsaved edits. It deliberately drops uploaded FILES, because those
 * are not persisted yet and cannot be handed to the renderer.
 *
 * A gallery pick is not a file. It posts `background_image_asset`, an S3
 * object key, which the save path resolves to a public CDN URL and stores
 * as `background_image`. The preview path never did that translation, so it
 * cached a key under a name the renderer does not read, and the preview sat
 * unchanged -- while the badge still flipped to "Unsaved preview", which
 * made it look like the pick had registered and then been ignored.
 *
 * The old gallery accordion had the same hole. Merging the two image
 * pickers made the gallery the main way to choose an image, and the bug
 * stopped being easy to miss.
 *
 * The key arrives from a form, so these tests care as much about the keys
 * that must NOT resolve as the one that must.
 */
class GalleryPicksShowInTheLivePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);

        return User::factory()->create();
    }

    private function biolink(User $user): Link
    {
        return Link::factory()->create([
            'user_id' => $user->id,
            'type'    => 'biolink',
        ]);
    }

    /** @return array<string, mixed> the cached draft's biolink settings */
    private function draftAfterPosting(User $user, Link $link, array $payload): array
    {
        $this->actingAs($user)
            ->post(route('user.links.preview-draft', $link), $payload)
            ->assertOk();

        $draft = Cache::get("biolink_draft:{$link->id}");

        return is_array($draft) ? ($draft['biolink'] ?? []) : [];
    }

    /** The pick reaches the renderer as the field it actually reads. */
    public function test_a_gallery_pick_lands_as_a_background_image_url(): void
    {
        $user = $this->owner();
        $link = $this->biolink($user);
        $key  = 'assets/biolink-backgrounds/aurora.jpg';

        $draft = $this->draftAfterPosting($user, $link, [
            'background_type'        => 'image',
            'background_image_asset' => $key,
        ]);

        $this->assertSame(
            PlatformAssetCatalog::urlForKey($key),
            $draft['background_image'] ?? null,
            'the preview renderer reads background_image, not the asset key'
        );

        // The raw key must not ride along under its own name -- the renderer
        // ignores it, and leaving it in the draft only invites confusion.
        $this->assertArrayNotHasKey('background_image_asset', $draft);
    }

    /** Every folder the picker offers previews, not just backgrounds. */
    public function test_photos_and_hand_drawn_preview_too(): void
    {
        $user = $this->owner();

        foreach ([
            'assets/grid-images/city.png',
            'assets/hand-drawn/leaf.svg',
        ] as $key) {
            $link  = $this->biolink($user);
            $draft = $this->draftAfterPosting($user, $link, [
                'background_type'        => 'image',
                'background_image_asset' => $key,
            ]);

            $this->assertSame(
                PlatformAssetCatalog::urlForKey($key),
                $draft['background_image'] ?? null,
                "{$key} is offered in the picker, so it has to preview"
            );
        }
    }

    /**
     * The key is form input. A crafted one must be dropped, not resolved --
     * otherwise the preview becomes a way to render an arbitrary object.
     */
    public function test_a_key_outside_the_gallery_is_dropped_rather_than_rendered(): void
    {
        $user = $this->owner();

        foreach ([
            'assets/people-avatars/someone.png',   // real folder, not offered here
            'assets/grid-images/../../.env',
            'assets/grid-images/nested/city.png',
            'https://example.com/evil.png',
            'assets/grid-images/shell.php',
        ] as $key) {
            $link  = $this->biolink($user);
            $draft = $this->draftAfterPosting($user, $link, [
                'background_type'        => 'image',
                'background_image_asset' => $key,
            ]);

            $this->assertArrayNotHasKey('background_image', $draft,
                var_export($key, true).' must not reach the renderer');
            $this->assertArrayNotHasKey('background_image_asset', $draft);
        }
    }

    /** Everything else still flows through untouched. */
    public function test_the_rest_of_the_form_is_unaffected(): void
    {
        $user = $this->owner();
        $link = $this->biolink($user);

        $draft = $this->draftAfterPosting($user, $link, [
            'background_type'        => 'image',
            'background_image_asset' => 'assets/biolink-backgrounds/aurora.jpg',
            'bg_blur'                => '12',
            'bg_overlay_opacity'     => '40',
            'font_family'            => 'Inter',
        ]);

        $this->assertSame('12', $draft['bg_blur'] ?? null);
        $this->assertSame('40', $draft['bg_overlay_opacity'] ?? null);
        $this->assertSame('Inter', $draft['font_family'] ?? null);
    }
}
