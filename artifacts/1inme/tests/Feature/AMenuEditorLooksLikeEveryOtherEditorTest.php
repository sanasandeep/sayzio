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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "move settings column to another tab menu menu..
 * (rename settings to better name).. this way it will look uniform and all
 * will be looking same layout type... menu tab content should open in this
 * layout. also make sure all live changes are done snd shown on right
 * side... also make sure everything synchronized witrh store pages also".
 *
 * Four things, and the fourth is the one that keeps going wrong: the store
 * getting what the restaurant got. So every assertion here runs against
 * both editors from one list, rather than being written twice and drifting
 * the first time somebody edits one of them.
 *
 * What the shell is:
 *   - the same hero and main tab row every other editor screen uses;
 *   - a pill bar of three panes, because "Settings" was two unrelated jobs
 *     in one column -- how the page looks, and how ordering works;
 *   - content on the left, the live page on the right, on every pane.
 */
class AMenuEditorLooksLikeEveryOtherEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->owner);
    }

    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => Link::generateAlias(), 'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => Link::generateAlias(), 'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    /** Both editors, as [url, label] — the parity rule, written once. */
    private function bothEditors(): array
    {
        $r = $this->restaurant();
        $s = $this->store();

        return [
            ['restaurant', "/user/links/{$r->id}/restaurant", $r->id],
            ['store',      "/user/links/{$s->id}/store", $s->id],
        ];
    }

    private function html(string $url): string
    {
        return $this->actingAs($this->owner)->get($url)->assertOk()->getContent();
    }

    public function test_both_editors_wear_the_shared_shell(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            $html = $this->html($url);

            // The hero and main tab row every other editor screen has.
            $this->assertStringContainsString('editor-tabs', $html, "$kind: no main tab row");
            // Content left, page right, in the same twelve-column grid the
            // Settings screens use.
            $this->assertStringContainsString('lg:grid-cols-12', $html, "$kind: not the shared grid");
            $this->assertStringContainsString('lg:col-span-7', $html, "$kind: content column");
            $this->assertStringContainsString('lg:col-span-5', $html, "$kind: preview column");
            // The 320px bolted-on column is gone.
            $this->assertStringNotContainsString('class="rm-grid"', $html, "$kind: still using the old two-column grid");
        }
    }

    public function test_the_live_page_sits_beside_every_pane(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            $html = $this->html($url);

            // Sana: "make sure all live changes are done snd shown on right
            // side". The preview is outside the panes, so it is there
            // whichever one is open rather than only on one of them.
            $this->assertStringContainsString('device-preview-root', $html, "$kind: no live preview");

            // The column that WRAPS the preview is what decides whether it
            // is on every pane, so the test reads backwards from the
            // preview to its own opening tag. Reading forwards passes while
            // the preview sits inside one pane and vanishes on the others,
            // which is exactly the bug it is meant to catch.
            $pos = strpos($html, 'device-preview-root');
            $this->assertNotFalse($pos);
            $open = strrpos(substr($html, 0, $pos), '<div');
            $this->assertNotFalse($open);
            $wrapper = substr($html, $open, strpos($html, '>', $open) - $open + 1);
            $this->assertStringContainsString('lg:col-span-5', $wrapper, "$kind: preview is not in the shared column");
            $this->assertStringNotContainsString(
                'x-show',
                $wrapper,
                "$kind: the preview column is tied to one pane, so it disappears on the others"
            );
        }
    }

    public function test_the_panes_are_named_after_what_they_do(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            $html = $this->html($url);

            $this->assertStringContainsString('How it looks', $html, "$kind: no design pane");
            $this->assertStringContainsString('How ordering works', $html, "$kind: no ordering pane");
            // "Settings" held two unrelated jobs and said so to nobody.
            $this->assertStringNotContainsString('<h5>Settings</h5>', $html, "$kind: the old catch-all card is back");
        }
    }

    public function test_each_pane_holds_what_belongs_in_it(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            $html = $this->html($url);

            $design   = $this->paneBody($html, 'design');
            $ordering = $this->paneBody($html, 'ordering');

            // Looks.
            $this->assertStringContainsString('Item colours', $design, "$kind: colours are not in the design pane");
            $this->assertStringContainsString('Background &amp; fonts', $design, "$kind: background link misplaced");
            // Works.
            $this->assertStringContainsString('Currency', $ordering, "$kind: currency is not in the ordering pane");
            $this->assertStringContainsString('WhatsApp number', $ordering, "$kind: WhatsApp is not in the ordering pane");

            // And not the other way round, which is the state this replaces.
            $this->assertStringNotContainsString('Currency', $design, "$kind: ordering control left in the design pane");
            $this->assertStringNotContainsString('Item colours', $ordering, "$kind: design control left in the ordering pane");
        }
    }

    public function test_the_open_pane_survives_a_reload(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            // No parameter: the items, which is what somebody opening a menu
            // came for.
            $this->assertStringContainsString("menuEditorPanes('items')", $this->html($url), "$kind: default pane");

            $this->assertStringContainsString("menuEditorPanes('design')", $this->html($url.'?pane=design'), "$kind: ?pane=design");
            $this->assertStringContainsString("menuEditorPanes('ordering')", $this->html($url.'?pane=ordering'), "$kind: ?pane=ordering");

            // A pane name nobody wrote lands on the items rather than on a
            // screen with nothing drawn on it.
            $this->assertStringContainsString("menuEditorPanes('items')", $this->html($url.'?pane=../../etc'), "$kind: junk pane");
        }
    }

    public function test_the_page_specific_actions_survived_the_move(): void
    {
        foreach ($this->bothEditors() as [$kind, $url, $id]) {
            $html = $this->html($url);

            // These lived in the editor's own header, which the shared one
            // replaced. Losing them is the obvious way this change goes
            // wrong, and the reason editor-header now takes extra actions.
            // The href, not the word: "Orders" appears in half the copy on
            // this screen and `fa-receipt` is the ordering pane's own tab
            // icon, so asserting on either passes with the link deleted.
            $this->assertStringContainsString(
                "/user/links/{$id}/{$kind}/orders\"",
                $html,
                "$kind: lost the Orders link when the header was replaced"
            );
        }
    }

    /**
     * The markup of one pane.
     *
     * Panes are siblings, so a pane runs from its own x-show to the next
     * one's -- or, for the last, to the end of the content column.
     */
    private function paneBody(string $html, string $pane): string
    {
        $start = strpos($html, 'x-show="pane === \''.$pane.'\'"');
        $this->assertNotFalse($start, "pane $pane not found");

        $nextPositions = [];
        foreach (['items', 'design', 'ordering'] as $other) {
            if ($other === $pane) {
                continue;
            }
            $p = strpos($html, 'x-show="pane === \''.$other.'\'"', $start + 1);
            if ($p !== false) {
                $nextPositions[] = $p;
            }
        }
        $end = $nextPositions ? min($nextPositions) : strpos($html, 'device-preview-root', $start);

        return substr($html, $start, ($end ?: strlen($html)) - $start);
    }
}
