<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04: "menu items color changed, updated live but not shown
 * changed value in settings" and "soo many colors not customized... even
 * default always grey.. doesnt seems right".
 *
 * ---- Half a round trip ------------------------------------------------
 *
 * The five menu colours saved, and the public page read them -- which is
 * why the preview changed the moment he picked one. The EDITOR's state blob
 * never carried them back. So every reload handed the pickers an empty
 * string, the `|| '#888888'` fallback painted all five grey, and the panel
 * said the menu had no colours set while the page was rendering them.
 *
 * The same shape as the block position dropdown and the Layout card: a
 * control whose write works and whose read does not. These tests are the
 * read half, asserted per colour rather than once, and driven off
 * MenuPresentation::COLOURS so a sixth colour cannot be added to the panel
 * and missed here.
 */
class TheMenuColourPickersShowWhatIsSavedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** A distinct, recognisable colour per key. */
    private const CHOSEN = [
        'heading_color' => '#112233',
        'item_color'    => '#445566',
        'desc_color'    => '#778899',
        'price_color'   => '#aabbcc',
        'divider_color' => '#ddeeff',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $settings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $settings,
        ]);

        return $link;
    }

    private function store(array $settings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $settings,
        ]);

        return $link;
    }

    // ===== 1. The editor opens on the colours that are saved =====

    public function test_every_saved_colour_reaches_the_restaurant_editor(): void
    {
        $link = $this->restaurant(self::CHOSEN);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        foreach (self::CHOSEN as $key => $hex) {
            $this->assertStringContainsString(
                $hex,
                $html,
                'The '.$key.' picker does not open on the colour that is saved.'
            );
        }
    }

    public function test_every_saved_colour_reaches_the_store_editor(): void
    {
        $link = $this->store(self::CHOSEN);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/store')
            ->assertOk()->getContent();

        foreach (self::CHOSEN as $key => $hex) {
            $this->assertStringContainsString($hex, $html, 'The '.$key.' picker is wrong on the store editor.');
        }
    }

    /**
     * Driven off the panel's own list, so adding a sixth colour to
     * MenuPresentation::COLOURS without carrying it into the editor's state
     * fails here rather than shipping as another grey swatch.
     */
    public function test_the_editor_carries_every_colour_the_panel_offers(): void
    {
        $settings = [];
        foreach (array_keys(MenuPresentation::COLOURS) as $i => $key) {
            // A different, findable value per key, generated rather than
            // listed, so the test covers keys this file has never heard of.
            $settings[$key] = sprintf('#%02x0%02x0', 17 * ($i + 1), 17 * ($i + 1));
        }

        $link = $this->restaurant($settings);
        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        foreach ($settings as $key => $hex) {
            $this->assertStringContainsString($hex, $html, $key.' is offered by the panel but not loaded into it.');
        }
    }

    // ===== 2. A menu nobody has recoloured is unchanged =====

    /**
     * Unset must stay unset. If this fix handed the pickers a default hex
     * instead of an empty string, every menu in the product would start
     * claiming five colours it does not have, and the next save would
     * write them in for real.
     */
    public function test_an_unrecoloured_menu_reports_no_colours(): void
    {
        $link = $this->restaurant();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        foreach (array_keys(MenuPresentation::COLOURS) as $key) {
            $this->assertMatchesRegularExpression(
                '/"'.$key.'":""/',
                $html,
                $key.' should be empty on a menu nobody has recoloured, so it keeps inheriting.'
            );
        }
    }

    /** And its page still paints what it painted before. */
    public function test_an_unrecoloured_menu_still_inherits_on_the_page(): void
    {
        $link = $this->restaurant();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('--ink-head:  inherit;', $html);
    }

    // ===== 3. Junk in the settings blob does not reach a style attribute =====

    /**
     * These values go straight into a CSS custom property on a public page,
     * so the editor must not hand back whatever the column happens to hold.
     * MenuPresentation::hex is what cleans them, and this is the test that
     * it is actually being used on the way out.
     */
    public function test_a_value_that_is_not_a_colour_comes_back_empty(): void
    {
        $link = $this->restaurant([
            'heading_color' => 'red; } body { display:none } .x {',
            'item_color'    => 'javascript:alert(1)',
            'price_color'   => 'not a colour',
        ]);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        // Asserted on the VALUES, not on the page text. The first version
        // of this checked that "display:none" appeared nowhere in the HTML,
        // which the editor's own markup uses legitimately -- a test that
        // would have failed whether or not the colour was sanitised.
        foreach (['heading_color', 'item_color', 'price_color'] as $key) {
            $this->assertMatchesRegularExpression(
                '/"'.$key.'":""/',
                $html,
                $key.' held something that is not a colour and it was not cleaned on the way out.'
            );
        }
    }

    // ===== 4. The whole trip, as the owner makes it =====

    /**
     * Save a colour the way the editor does, then reopen the editor. This
     * is the actual complaint, end to end: it is not enough that a saved
     * colour renders, it has to come back to the box that set it.
     */
    public function test_a_colour_saved_from_the_editor_is_there_when_it_reopens(): void
    {
        $link = $this->restaurant();

        // mode and currency are required on this endpoint; the editor always
        // sends the whole settings object, so the test does too.
        $this->actingAs($this->user)->postJson('/user/links/'.$link->id.'/restaurant/settings', [
            'mode' => 'order',
            'currency' => 'INR',
            'heading_color' => '#ff8800',
        ])->assertOk();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        $this->assertStringContainsString('#ff8800', $html);
        // And the page agrees with the box.
        $page = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('--ink-head:  #ff8800;', $page);
    }
}
