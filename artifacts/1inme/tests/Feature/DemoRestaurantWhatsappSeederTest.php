<?php

namespace Tests\Feature;

use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantTable;
use Database\Seeders\LinkTypeExplainerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the live demo restaurant menu (`/demo-restaurant`) seeded by
 * LinkTypeExplainerSeeder so the public demo gallery can always hand diners
 * into the real ordering + "Send order via WhatsApp" confirmation flow.
 *
 * The demo is built via direct model writes and converged on each post-merge
 * seed, with no other automated coverage. A future change to the seeder, the
 * `restaurant_menu` models, or WhatsappOrderLink could silently break it, so
 * this test asserts the seeded shape and that re-running the seeder is
 * idempotent (no duplicate links / menu / categories / items / tables).
 */
class DemoRestaurantWhatsappSeederTest extends TestCase
{
    use RefreshDatabase;

    private const ALIAS = 'demo-restaurant';

    private function demoLink(): ?Link
    {
        return Link::query()->withoutWorkspaceScope()
            ->where('alias', self::ALIAS)
            ->where('type', Link::TYPE_RESTAURANT_MENU)
            ->first();
    }

    public function test_seeder_creates_working_demo_restaurant_with_whatsapp_ordering(): void
    {
        $this->seed(LinkTypeExplainerSeeder::class);

        $link = $this->demoLink();
        $this->assertNotNull($link, 'Demo restaurant link was not seeded.');
        $this->assertSame(Link::TYPE_RESTAURANT_MENU, $link->type);
        $this->assertSame('public', $link->visibility);
        $this->assertTrue((bool) $link->is_active, 'Demo restaurant link should be active.');

        // Menu config: present, in order mode, with a normalized WhatsApp number.
        $menu = RestaurantMenu::where('link_id', $link->id)->first();
        $this->assertNotNull($menu, 'Demo restaurant menu config row is missing.');
        $this->assertSame(RestaurantMenu::MODE_ORDER, $menu->mode);
        $this->assertTrue($menu->isOrderMode(), 'Demo menu should be in order mode.');

        $number = $menu->settings['whatsapp_number'] ?? null;
        $this->assertNotEmpty($number, 'Demo menu is missing a WhatsApp number.');
        $this->assertSame(
            WhatsappOrderLink::normalizeNumber($number),
            $number,
            'Stored WhatsApp number must already be normalized (digits only).'
        );
        $this->assertSame(
            $number,
            WhatsappOrderLink::numberFor($menu),
            'WhatsappOrderLink::numberFor() should resolve the stored demo number.'
        );

        // At least one category, item and table so the live ordering + per-table
        // QR flow is fully reachable.
        $this->assertGreaterThanOrEqual(1, $menu->categories()->count(),
            'Demo menu should have at least one category.');
        $this->assertGreaterThanOrEqual(1, $menu->items()->count(),
            'Demo menu should have at least one item.');
        $this->assertGreaterThanOrEqual(1, $menu->tables()->count(),
            'Demo menu should have at least one table.');
    }

    public function test_restaurant_explainer_cta_points_to_demo_restaurant(): void
    {
        $this->seed(LinkTypeExplainerSeeder::class);

        $explainer = Link::query()->withoutWorkspaceScope()
            ->where('alias', 'demo-type-restaurant-menu')
            ->first();
        $this->assertNotNull($explainer, 'Restaurant explainer page was not seeded.');

        $ctaUrls = BiolinkBlock::where('link_id', $explainer->id)
            ->where('type', 'link')
            ->get()
            ->map(fn (BiolinkBlock $b) => (string) data_get($b->settings, 'url'))
            ->all();

        $matches = array_filter(
            $ctaUrls,
            fn (string $url) => str_ends_with($url, '/' . self::ALIAS)
        );
        $this->assertNotEmpty(
            $matches,
            'Restaurant explainer CTA should link to /' . self::ALIAS . '.'
        );
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(LinkTypeExplainerSeeder::class);

        $link = $this->demoLink();
        $this->assertNotNull($link);
        $menu = RestaurantMenu::where('link_id', $link->id)->first();
        $this->assertNotNull($menu);

        $categories = RestaurantMenuCategory::where('menu_id', $menu->id)->count();
        $items = RestaurantMenuItem::where('menu_id', $menu->id)->count();
        $tables = RestaurantTable::where('menu_id', $menu->id)->count();

        // Second pass exercises the convergence path admins trigger on every
        // post-merge re-seed. Nothing should be duplicated.
        $this->seed(LinkTypeExplainerSeeder::class);

        $this->assertSame(
            1,
            Link::query()->withoutWorkspaceScope()
                ->where('alias', self::ALIAS)->count(),
            'Re-seeding duplicated the demo restaurant link.'
        );
        $this->assertSame(
            1,
            RestaurantMenu::where('link_id', $link->id)->count(),
            'Re-seeding duplicated the demo restaurant menu.'
        );
        $this->assertSame($categories,
            RestaurantMenuCategory::where('menu_id', $menu->id)->count(),
            'Re-seeding changed the demo category count.');
        $this->assertSame($items,
            RestaurantMenuItem::where('menu_id', $menu->id)->count(),
            'Re-seeding changed the demo item count.');
        $this->assertSame($tables,
            RestaurantTable::where('menu_id', $menu->id)->count(),
            'Re-seeding changed the demo table count.');

        // WhatsApp ordering survives the re-seed.
        $menu->refresh();
        $this->assertNotEmpty($menu->settings['whatsapp_number'] ?? null);
        $this->assertSame(RestaurantMenu::MODE_ORDER, $menu->mode);
    }
}
