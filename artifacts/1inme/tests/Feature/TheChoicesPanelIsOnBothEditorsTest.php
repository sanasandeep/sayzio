<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The screen that offers item choices, on both menu types.
 *
 * This test exists because of a pattern: seven times this month a thing was
 * built into the code and no screen ever offered it -- active categories,
 * leader dots, resume aliases, the AI builder's images, the block
 * stylesheet, block positions, the WhatsApp handoff. The endpoints and the
 * rules engine for choices are green and would stay green forever with the
 * panel missing from one of the two editors, which is exactly how the store
 * kept ending up a week behind the restaurant.
 *
 * So: the panel renders, on both, with the right endpoint and the real
 * items, and the owner can reach it.
 */
class TheChoicesPanelIsOnBothEditorsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function source(string $view): string
    {
        return file_get_contents(resource_path('views/user/links/'.$view.'/editor.blade.php'));
    }

    /**
     * `@js` JSON-encodes, and JSON escapes forward slashes, so the endpoint
     * reaches the page as `...\/restaurant\/option-groups`. Match either
     * spelling rather than pinning the one this Laravel version happens to
     * emit.
     */
    private function assertHasPath(string $path, string $html, string $why): void
    {
        $pattern = '#'.str_replace('/', '\\\\?/', preg_quote($path, '#')).'#';
        $this->assertMatchesRegularExpression($pattern, $html, $why);
    }

    private function assertLacksPath(string $path, string $html, string $why): void
    {
        $pattern = '#'.str_replace('/', '\\\\?/', preg_quote($path, '#')).'#';
        $this->assertDoesNotMatchRegularExpression($pattern, $html, $why);
    }

    // ===== It is on the screen ===========================================

    public function test_the_restaurant_editor_offers_it(): void
    {
        $link = $this->restaurant();

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        $this->assertStringContainsString('menuChoices(', $html, 'The panel did not render.');
        $this->assertHasPath('/restaurant/option-groups', $html,
            'The panel is pointed at no endpoint, or the wrong one.');
        // The real items, so a group can actually be put on something.
        $this->assertStringContainsString('Chilli Paneer', $html);
        $this->assertMatchesRegularExpression('/(&quot;|&#039;|["\'])dish\\1?/', $html,
            'The panel should know it is talking about dishes.');
    }

    public function test_the_store_editor_offers_it_too(): void
    {
        $link = $this->store();

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/store')
            ->assertOk()->getContent();

        $this->assertStringContainsString('menuChoices(', $html, 'The store was left behind again.');
        $this->assertHasPath('/store/option-groups', $html, 'The store panel has no endpoint.');
        $this->assertStringContainsString('Big Mug', $html);
        $this->assertMatchesRegularExpression('/(&quot;|&#039;|["\'])product\\1?/', $html,
            'The panel should know it is talking about products.');
    }

    public function test_each_editor_points_at_its_own_menu(): void
    {
        // A copy-pasted endpoint is the other way these two drift: the
        // store's panel quietly editing the restaurant's groups.
        $restaurant = $this->restaurant();
        $store = $this->store();

        $rHtml = $this->actingAs($this->owner)
            ->get('/user/links/'.$restaurant->id.'/restaurant')->assertOk()->getContent();
        $sHtml = $this->actingAs($this->owner)
            ->get('/user/links/'.$store->id.'/store')->assertOk()->getContent();

        $this->assertHasPath('/links/'.$restaurant->id.'/restaurant/option-groups', $rHtml,
            'The restaurant panel is not pointed at the restaurant.');
        $this->assertLacksPath('/store/option-groups', $rHtml,
            "The restaurant editor would be editing the store's groups.");

        $this->assertHasPath('/links/'.$store->id.'/store/option-groups', $sHtml,
            'The store panel is not pointed at the store.');
        $this->assertLacksPath('/restaurant/option-groups', $sHtml,
            "The store editor would be editing the restaurant's groups.");
    }

    // ===== One copy, not two =============================================

    public function test_neither_editor_keeps_its_own_copy_of_the_panel(): void
    {
        foreach (['restaurant', 'store'] as $editor) {
            $src = file_get_contents(resource_path('views/user/links/'.$editor.'/editor.blade.php'));

            $this->assertStringContainsString('menu-choices-panel', $src,
                "The {$editor} editor should draw the panel from the shared partial.");
            $this->assertStringNotContainsString('function menuChoices(', $src,
                "The {$editor} editor is carrying its own copy, which is how these two drift.");
        }

        $partial = file_get_contents(resource_path('views/user/links/partials/menu-choices-panel.blade.php'));
        $this->assertSame(1, substr_count($partial, 'function menuChoices('));
    }

    // ===== The preview says what the customer will read ==================

    public function test_the_panel_previews_the_sentence_the_customer_gets(): void
    {
        $partial = file_get_contents(resource_path('views/user/links/partials/menu-choices-panel.blade.php'));

        // The owner sets four numbers; the preview turns them into the line
        // their customers will read, mirroring MenuOptionSelection so it is
        // the real sentence rather than an approximation of it.
        $this->assertStringContainsString("preview()", $partial);
        foreach (["'Choose '", "'choose at least '", "'optional'", "' · each up to '"] as $piece) {
            $this->assertStringContainsString($piece, $partial,
                'The preview does not mirror the server label, so it will drift from it.');
        }
    }

    public function test_a_group_has_to_be_created_before_it_can_hold_choices(): void
    {
        $partial = file_get_contents(resource_path('views/user/links/partials/menu-choices-panel.blade.php'));

        // Choices and attachments are rows against a group id, so both
        // sections only appear once there is one -- and creating leaves the
        // sheet open rather than making it a two-step job with no sign of it.
        $this->assertStringContainsString('<template x-if="draft.id">', $partial);
        $this->assertStringContainsString("this.draft.id = d.group.id;", $partial);
    }
}
