<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BackgroundFit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04, having uploaded a decorative border frame as his page
 * background: "i have attached background image.... fit, strech, cover ...
 * all are missing.... make sure things are working for normal link in bio
 * and others also".
 *
 * ---- One behaviour, written twice -------------------------------------
 *
 * `center/cover no-repeat` as a literal, in body-declarations.blade.php and
 * again in css.blade.php. Cover fills the viewport and discards the
 * overflow, which is right for a photograph and exactly wrong for a frame
 * whose entire content is at the edges -- it cropped off the only part that
 * mattered, and there was no setting to say otherwise.
 *
 * ---- What these tests hold ---------------------------------------------
 *
 * That the choice reaches the page on EVERY page type that paints a
 * background, since that was the explicit ask; that an unset fit still
 * renders cover, because every page with a background today is rendering
 * cover and must not move; and that the shorthand is gone, because a
 * `background:` shorthand silently resets the longhands beside it.
 */
class ABackgroundImageCanFitRatherThanCropTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** @param array<string,mixed> $bg */
    private function biolink(array $bg = []): Link
    {
        return Link::create([
            'user_id' => $this->user->id, 'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Page', 'is_active' => true,
            'settings' => ['biolink' => array_merge([
                'background_type'  => 'image',
                'background_image' => 'https://sayzio.app/f/1/frame.png',
            ], $bg)],
        ]);
    }

    /** @param array<string,mixed> $bg */
    private function restaurant(array $bg = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
            'settings' => ['biolink' => array_merge([
                'background_type'  => 'image',
                'background_image' => 'https://sayzio.app/f/1/frame.png',
            ], $bg)],
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Chilli Paneer',
            'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    // ===== 1. The one he asked for =====

    /**
     * The frame. `contain` is the whole point of this change: it is the only
     * fit that shows an image whose content is at its edges.
     */
    public function test_fit_whole_image_reaches_a_link_in_bio(): void
    {
        $html = $this->get('/'.$this->biolink(['bg_fit' => BackgroundFit::CONTAIN])->alias)
            ->assertOk()->getContent();

        $this->assertStringContainsString('background-size: contain;', $html);
        $this->assertStringContainsString('background-repeat: no-repeat;', $html);
        $this->assertStringNotContainsString('background-size: cover;', $html);
    }

    /** "make sure things are working for normal link in bio and others also". */
    public function test_fit_whole_image_reaches_a_menu_page(): void
    {
        $html = $this->get('/'.$this->restaurant(['bg_fit' => BackgroundFit::CONTAIN])->alias)
            ->assertOk()->getContent();

        $this->assertStringContainsString('background-size: contain;', $html);
    }

    /**
     * Every fit, on both page types, driven off the vocabulary rather than
     * listed -- a sixth fit cannot be added and left unrendered.
     */
    public function test_every_fit_renders_on_every_page_that_paints_a_background(): void
    {
        foreach (array_keys(BackgroundFit::CHOICES) as $fit) {
            $expected = BackgroundFit::declarations($fit)['size'];

            foreach ([$this->biolink(['bg_fit' => $fit]), $this->restaurant(['bg_fit' => $fit])] as $link) {
                $html = $this->get('/'.$link->alias)->assertOk()->getContent();
                $this->assertStringContainsString(
                    'background-size: '.$expected.';',
                    $html,
                    'The "'.BackgroundFit::CHOICES[$fit]['label'].'" fit does not reach '.$link->type.'.'
                );
            }
        }
    }

    public function test_tile_repeats_from_the_corner_rather_than_the_centre(): void
    {
        $html = $this->get('/'.$this->biolink(['bg_fit' => BackgroundFit::TILE])->alias)
            ->assertOk()->getContent();

        $this->assertStringContainsString('background-repeat: repeat;', $html);
        // Tiling from the centre leaves a half tile at every edge.
        $this->assertStringContainsString('background-position: top left;', $html);
    }

    public function test_a_position_is_honoured_for_the_fits_that_can_leave_a_gap(): void
    {
        $html = $this->get('/'.$this->biolink([
            'bg_fit' => BackgroundFit::CONTAIN, 'bg_position' => 'bottom right',
        ])->alias)->assertOk()->getContent();

        $this->assertStringContainsString('background-position: bottom right;', $html);
    }

    /** Cover fills the frame, so a position would be a control that lies. */
    public function test_a_position_is_ignored_where_it_cannot_mean_anything(): void
    {
        $d = BackgroundFit::declarations(BackgroundFit::COVER, 'bottom right');

        $this->assertSame('center', $d['position']);
        $this->assertFalse(BackgroundFit::usesPosition(BackgroundFit::COVER));
        $this->assertTrue(BackgroundFit::usesPosition(BackgroundFit::CONTAIN));
    }

    // ===== 2. Nothing moves for a page that never set one =====

    /**
     * The one that matters for everybody else. Every page with a background
     * image in the product is rendering cover, because it was the only
     * option. An unset fit has to keep meaning cover.
     */
    public function test_a_page_that_never_chose_a_fit_still_renders_cover(): void
    {
        foreach ([$this->biolink(), $this->restaurant()] as $link) {
            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('background-size: cover;', $html);
            $this->assertStringContainsString('background-position: center;', $html);
            $this->assertStringContainsString('background-repeat: no-repeat;', $html);
        }
    }

    public function test_junk_in_the_settings_blob_reads_as_cover(): void
    {
        $this->assertSame(BackgroundFit::COVER, BackgroundFit::fit('squish'));
        $this->assertSame(BackgroundFit::COVER, BackgroundFit::fit(null));
        $this->assertSame(BackgroundFit::COVER, BackgroundFit::fit(['cover']));
        $this->assertSame('center', BackgroundFit::position('under the fold'));
    }

    /**
     * The emitters used the `background:` shorthand, which resets every
     * longhand it omits -- so a fit set as a longhand beside it would have
     * been silently wiped. Both now write longhands only.
     */
    public function test_the_image_is_painted_with_longhands_not_the_shorthand(): void
    {
        $html = $this->get('/'.$this->biolink(['bg_fit' => BackgroundFit::CONTAIN])->alias)
            ->assertOk()->getContent();

        $this->assertStringContainsString('background-image: url(', $html);
        // The literal that was there before, in both emitters.
        $this->assertStringNotContainsString('center/cover no-repeat', $html);
    }

    // ===== 3. It saves =====

    public function test_a_fit_and_a_position_save_from_the_appearance_page(): void
    {
        $link = $this->biolink();

        $this->actingAs($this->user)->post('/user/links/'.$link->id.'/page-settings', [
            // No background_image here: it is an UPLOAD field, and passing
            // a URL string fails its rule and bounces the whole save. The
            // link already carries the image; the fit is the subject.
            'background_type' => 'image',
            'bg_fit' => BackgroundFit::CONTAIN,
            'bg_position' => 'bottom',
        ])->assertRedirect();

        $bs = $link->fresh()->settings['biolink'] ?? [];
        $this->assertSame(BackgroundFit::CONTAIN, $bs['bg_fit'] ?? null);
        $this->assertSame('bottom', $bs['bg_position'] ?? null);
    }

    public function test_a_fit_nobody_offers_is_refused_on_save(): void
    {
        $link = $this->biolink();

        $this->actingAs($this->user)->post('/user/links/'.$link->id.'/page-settings', [
            'background_type' => 'image',
            'bg_fit' => 'squish',
        ])->assertSessionHasErrors('bg_fit');
    }

    // ===== 4. The control is on the screen =====

    public function test_the_appearance_page_offers_every_fit(): void
    {
        $link = $this->biolink(['bg_fit' => BackgroundFit::CONTAIN]);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/appearance')
            ->assertOk()->getContent();

        foreach (BackgroundFit::CHOICES as $meta) {
            $this->assertStringContainsString($meta['label'], $html);
        }
        $this->assertStringContainsString('name="bg_fit"', $html);
        $this->assertStringContainsString('name="bg_position"', $html);
    }

    /** And it opens on the fit the page is actually rendering. */
    public function test_the_control_opens_on_the_saved_fit(): void
    {
        $link = $this->biolink(['bg_fit' => BackgroundFit::TILE]);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/appearance')
            ->assertOk()->getContent();

        $this->assertStringContainsString("fit: '".BackgroundFit::TILE."'", $html);
    }
}
