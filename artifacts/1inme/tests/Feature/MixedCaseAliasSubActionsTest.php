<?php

namespace Tests\Feature;

use App\Modules\Common\Models\BiolinkReport;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the case-insensitive alias resolution on the SECONDARY public
 * biolink actions — the sub-actions that fire after (or alongside) the
 * primary `/{alias}` page render. The primary page already resolves any
 * casing via Link::resolveByAlias()'s LOWER() fallback; these endpoints
 * each call resolveByAlias() independently, so without coverage a future
 * refactor could silently reintroduce a case-sensitive 404 on one of them
 * while the main page kept working.
 *
 * Every test stores a deliberately MIXED-case alias and then hits the
 * sub-action with a DIFFERENT (lower) casing, asserting it still reaches
 * the same link instead of 404ing. Each test also pins that an alias of
 * the WRONG link type still 404s, proving the type filtering that lives
 * next to the resolution call is preserved.
 *
 * Surfaces covered:
 *   - report submit          Common\BiolinkReportController::store
 *   - restaurant order/menu  Common\PublicRestaurantController::placeOrder / resolveMenu
 *   - slide-view ping (web)  Common\SlideEventController::view
 *   - API resolve + actions  Api\BiolinkController::show / visit / tap /
 *                            pollVote / pollResults / rsvpSubmit / slideView
 */
class MixedCaseAliasSubActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /**
     * A guaranteed mixed-case alias: the leading capitals ensure the stored
     * value differs from its lowercase form, so resolveByAlias()'s exact
     * lookup misses and the case-insensitive fallback must do the work.
     */
    private function mixedAlias(string $prefix): string
    {
        return $prefix . Str::random(5);
    }

    private function makeBiolink(User $user, string $alias): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => $alias,
            'title'     => 'My Bio',
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // report submit (Common\BiolinkReportController::store)
    // ------------------------------------------------------------------

    public function test_report_submit_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('RepBio');
        $bio   = $this->makeBiolink($user, $alias);

        // Visitor typed an all-lowercase casing of the stored mixed-case alias.
        $resp = $this->postJson('/' . strtolower($alias) . '/report', [
            'reason'    => array_key_first(BiolinkReport::REASONS),
            'comment'   => 'Looks like spam',
            'captcha_a' => 2,
            'captcha_b' => 3,
            'captcha'   => 5,
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        $this->assertSame(1, BiolinkReport::where('link_id', $bio->id)->count());
    }

    public function test_report_submit_404s_for_wrong_link_type(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('RepUrl');
        // A short URL link is NOT in the biolink family — must be rejected.
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => 'https://dest.example.com',
            'is_active' => true,
        ]);

        $this->postJson('/' . strtolower($alias) . '/report', [
            'reason'    => array_key_first(BiolinkReport::REASONS),
            'captcha_a' => 1,
            'captcha_b' => 1,
            'captcha'   => 2,
        ])->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // restaurant order / menu (Common\PublicRestaurantController)
    // ------------------------------------------------------------------

    /** A restaurant_menu link in order mode with one orderable item. */
    private function makeRestaurantLink(User $user, string $alias): array
    {
        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => Link::TYPE_RESTAURANT_MENU,
            'alias'     => $alias,
            'title'     => 'My Diner',
            'is_active' => true,
        ]);

        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $user->id,
            'mode'     => RestaurantMenu::MODE_ORDER,
            'currency' => 'USD',
        ]);

        $category = RestaurantMenuCategory::create([
            'menu_id'    => $menu->id,
            'name'       => 'Mains',
            'sort_order' => 0,
            'is_active'  => true,
        ]);

        $item = RestaurantMenuItem::create([
            'menu_id'     => $menu->id,
            'category_id' => $category->id,
            'name'        => 'Burger',
            'price'       => 9.50,
            'currency'    => 'USD',
            'sort_order'  => 0,
            'is_sold_out' => false,
            'is_active'   => true,
        ]);

        return [$link, $menu, $item];
    }

    public function test_restaurant_order_resolves_mixed_case_alias(): void
    {
        $user = $this->makeUser();
        $alias = $this->mixedAlias('MenuBio');
        [$link, $menu, $item] = $this->makeRestaurantLink($user, $alias);

        $resp = $this->postJson('/rm/' . strtolower($alias) . '/order', [
            'customer_name' => 'Pat Guest',
            'items'         => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        $resp->assertCreated();
        $resp->assertJsonPath('data.order.status', \App\Modules\User\Models\RestaurantOrder::STATUS_NEW);
    }

    public function test_restaurant_order_404s_for_wrong_link_type(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('MenuPln');
        // A plain biolink is the wrong type for the restaurant order surface.
        $this->makeBiolink($user, $alias);

        $this->postJson('/rm/' . strtolower($alias) . '/order', [
            'items' => [['item_id' => 1, 'quantity' => 1]],
        ])->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // slide-view ping — web (Common\SlideEventController::view)
    // ------------------------------------------------------------------

    public function test_web_slide_view_ping_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('SldBio');
        $bio   = $this->makeBiolink($user, $alias);

        LinkSlideDeck::create([
            'link_id'            => $bio->id,
            'version'            => 1,
            'is_published'       => true,
            'published_snapshot' => ['slides' => []],
        ]);

        $resp = $this->postJson('/sl/' . strtolower($alias) . '/view', [
            'slide_index' => 0,
            'completed'   => true,
        ]);

        $resp->assertOk();
        // tracked=true proves the alias resolved to the link AND its deck.
        $resp->assertJson(['ok' => true, 'tracked' => true]);
    }

    public function test_web_slide_view_ping_404s_for_wrong_link_type(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('SldUrl');
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => 'https://dest.example.com',
            'is_active' => true,
        ]);

        $this->postJson('/sl/' . strtolower($alias) . '/view', [
            'slide_index' => 0,
        ])->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // API resolve + actions (Api\BiolinkController)
    // ------------------------------------------------------------------

    public function test_api_show_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('ApiBio');
        $bio   = $this->makeBiolink($user, $alias);

        $resp = $this->getJson('/api/v1/biolinks/' . strtolower($alias));
        $resp->assertOk();
        // Resolution returns the SAME link — its canonical, stored casing.
        $resp->assertJsonPath('data.biolink.id', $bio->id);
        $resp->assertJsonPath('data.biolink.alias', $alias);
    }

    public function test_api_show_404s_for_wrong_link_type(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('ApiUrl');
        Link::create([
            'user_id'   => $user->id,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => 'https://dest.example.com',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/biolinks/' . strtolower($alias))
            ->assertStatus(404);
    }

    public function test_api_visit_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('VstBio');
        $this->makeBiolink($user, $alias);

        $this->postJson('/api/v1/biolinks/' . strtolower($alias) . '/visit')
            ->assertOk()
            ->assertJson(['data' => ['tracked' => true]]);
    }

    public function test_api_slide_view_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('AslBio');
        $bio   = $this->makeBiolink($user, $alias);

        LinkSlideDeck::create([
            'link_id'            => $bio->id,
            'version'            => 1,
            'is_published'       => true,
            'published_snapshot' => ['slides' => []],
        ]);

        $this->postJson('/api/v1/biolinks/' . strtolower($alias) . '/slides/view', [
            'slide_index' => 0,
        ])->assertOk()->assertJson(['data' => ['tracked' => true]]);
    }

    public function test_api_tap_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('TapBio');
        $bio   = $this->makeBiolink($user, $alias);

        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['_link' => ['url' => 'https://example.com']],
        ]);

        $this->postJson(
            '/api/v1/biolinks/' . strtolower($alias) . "/blocks/{$block->id}/tap",
            ['destination_url' => 'https://example.com']
        )->assertOk()->assertJson(['data' => ['tracked' => true]]);
    }

    public function test_api_poll_vote_and_results_resolve_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('PllBio');
        $bio   = $this->makeBiolink($user, $alias);

        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'poll',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['question' => 'Pick one', 'options' => ['A', 'B', 'C']],
        ]);

        $lower = strtolower($alias);

        $this->postJson("/api/v1/biolinks/{$lower}/blocks/{$block->id}/poll-vote", [
            'option_index' => 1,
            'option_label' => 'B',
        ])->assertOk();
        $this->assertSame(1, PollVote::where('block_id', $block->id)->count());

        $this->getJson("/api/v1/biolinks/{$lower}/blocks/{$block->id}/poll-results")
            ->assertOk()
            ->assertJsonPath('data.total_votes', 1);
    }

    public function test_api_rsvp_resolves_mixed_case_alias(): void
    {
        $user  = $this->makeUser();
        $alias = $this->mixedAlias('RsvBio');
        $bio   = $this->makeBiolink($user, $alias);

        $event = Link::create([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => Link::generateAlias(),
            'title'     => 'Launch Party',
            'is_active' => true,
            'settings'  => ['rsvp_enabled' => true, 'rsvp_allow_plus_ones' => true],
        ]);

        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'rsvp',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['event_link_id' => $event->id, 'heading' => 'RSVP to'],
        ]);

        $this->postJson(
            '/api/v1/biolinks/' . strtolower($alias) . "/blocks/{$block->id}/rsvp",
            ['name' => 'Sam Sample', 'response' => 'yes', 'plus_ones' => 1]
        )->assertCreated();

        $this->assertSame(1, Rsvp::where('link_id', $event->id)->count());
    }
}
