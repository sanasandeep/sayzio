<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
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
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Sana, 2026-09-27, looking at a restaurant menu: "CSS seems broken in page
 * demo".
 *
 * It was. Every block in resources/views/common/blocks/* is written in
 * Tailwind utility classes and FontAwesome icon classes. The block loop used
 * to live inline in common/biolink.blade.php, which loads both in its own
 * head. Moving it into common/partials/biolink-block-list so the menus could
 * render blocks too carried the markup across and not the stylesheet -- and a
 * menu page has no @vite.
 *
 * So on a menu, every block rendered as raw HTML. Measured on the live page
 * before the fix: a Heading block came out at the browser's default h2, 42px
 * with 35px margins instead of 24px with none, and a full-width button was
 * 87px wide -- the width of the word on it -- instead of filling the column.
 * Icons were blank.
 *
 * Nothing threw. No route 500'd, no test failed, every block was present in
 * the DOM with all its classes on it. The only way to find out was to open
 * the page and look, which is why this is tested by asserting the page HEAD
 * carries what the block markup needs, and why the check is also a build
 * guard rather than only a test: the next page type to render blocks will
 * forget in exactly the same silent way.
 */
class ABlockOnAMenuPageLooksLikeABlockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A restaurant menu with one section, one dish, and no blocks yet. */
    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    /** A store menu with one category, one product, and no blocks yet. */
    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    /** A heading block on the page, which is the one Sana had on his. */
    private function addHeading(Link $link, string $text = 'Today we open at four'): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id' => $link->id,
            'type' => 'heading',
            'settings' => ['text' => $text],
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    /** Everything before </head>, which is the only place a stylesheet helps. */
    private function headOf(string $html): string
    {
        $end = stripos($html, '</head>');

        return $end === false ? $html : substr($html, 0, $end);
    }

    // ===== 1. The head of a menu page carries what a block is written in ====

    public function test_a_restaurant_menu_loads_the_stylesheet_its_blocks_are_written_in(): void
    {
        $link = $this->restaurant();
        $this->addHeading($link);

        $head = $this->headOf($this->page($link));

        // Tailwind. Vite hashes the built filename (app-DMrzTdBA.css) and
        // serves the entry unhashed from the dev server, so match the stem
        // both forms share rather than either spelling.
        $this->assertMatchesRegularExpression('#/(build/assets/)?app[-.][\w.-]*css#', $head);
        // Icon classes. A block's icons are blank without it.
        $this->assertStringContainsString('fontawesome', $head);
    }

    public function test_a_store_menu_loads_it_too(): void
    {
        $link = $this->store();
        $this->addHeading($link);

        $head = $this->headOf($this->page($link));

        $this->assertMatchesRegularExpression('#/(build/assets/)?app[-.][\w.-]*css#', $head);
        $this->assertStringContainsString('fontawesome', $head);
    }

    // ===== 2. In the head, not next to the blocks ===========================

    public function test_the_stylesheet_is_in_the_head_and_not_beside_the_blocks(): void
    {
        $link = $this->restaurant();
        $this->addHeading($link);

        $html = $this->page($link);

        // A stylesheet linked from the body repaints the blocks mid-scroll on
        // a slow connection, which is most of how a menu is read. The asset
        // partial exists so that this stays true.
        $this->assertMatchesRegularExpression('#/(build/assets/)?app[-.][\w.-]*css#', $this->headOf($html));

        $headEnd = stripos($html, '</head>');
        $this->assertNotFalse($headEnd);
        $body = substr($html, $headEnd);
        $this->assertDoesNotMatchRegularExpression('#/(build/assets/)?app[-.][\w.-]*css#', $body);
    }

    // ===== 3. The block still renders, and still where it belongs ==========

    public function test_the_block_itself_is_still_on_the_page_below_the_menu(): void
    {
        $link = $this->restaurant();
        $this->addHeading($link, 'Today we open at four');

        $html = $this->page($link);

        $this->assertStringContainsString('Today we open at four', $html);
        $this->assertStringContainsString('Masala Dosa', $html);

        // A block with no side chosen sits below the menu, because the menu is
        // what the page is for. Ordering, not just presence.
        $this->assertLessThan(
            strpos($html, 'Today we open at four'),
            strpos($html, 'Masala Dosa'),
            'An unassigned block should render below the menu, not above it.'
        );
    }

    // ===== 4. A menu with no blocks ships no block assets ==================

    public function test_the_menu_rows_keep_the_line_height_they_were_built_with(): void
    {
        $link = $this->restaurant();
        $this->addHeading($link);

        $head = $this->headOf($this->page($link));

        // Tailwind's preflight sets line-height:inherit on every element,
        // which grew these rows about 8% taller than they had rendered since
        // the page type existed. Sana's other complaint the same day was that
        // menu items look too big; loading Tailwind must not make that worse.
        $this->assertMatchesRegularExpression(
            '/\.items\s+\.item\s+\.name[^{]*\{[^}]*line-height/s',
            $head,
            'The menu rows should restate their own line-height once Tailwind is on the page.'
        );
    }

    // ===== 5. The build refuses the next page type that forgets ============

    public function test_the_guard_passes_on_the_tree_as_it_stands(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root.'/scripts/check-block-assets.php';
        $this->assertFileExists($script);

        $p = new Process([PHP_BINARY, $script], $root);
        $p->run();

        $this->assertSame(
            0,
            $p->getExitCode(),
            "check-block-assets should pass on a clean tree.\n".$p->getOutput().$p->getErrorOutput()
        );
    }

    public function test_the_guard_fails_a_page_that_renders_blocks_with_nothing_to_style_them(): void
    {
        $root = dirname(__DIR__, 2);
        $script = $root.'/scripts/check-block-assets.php';
        $menu = $root.'/resources/views/common/restaurant-menu.blade.php';

        $original = file_get_contents($menu);
        $this->assertStringContainsString('biolink-block-assets', $original);

        // Take the assets back out of the head, exactly as the page stood
        // while every block on it rendered unstyled.
        $broken = str_replace(
            "    @include('common.partials.biolink-block-assets')\n",
            '',
            $original
        );
        $this->assertNotSame($original, $broken, 'The include line should be removable for this check.');

        try {
            file_put_contents($menu, $broken);

            $p = new Process([PHP_BINARY, $script], $root);
            $p->run();

            $this->assertSame(1, $p->getExitCode(), 'The guard should fail a menu that loads nothing.');
            $this->assertStringContainsString('common.restaurant-menu', $p->getErrorOutput());
        } finally {
            file_put_contents($menu, $original);
        }

        // And the tree is back the way it was.
        $p = new Process([PHP_BINARY, $script], $root);
        $p->run();
        $this->assertSame(0, $p->getExitCode(), 'The file should be restored after the check.');
    }
}
