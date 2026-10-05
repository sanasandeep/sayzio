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
use App\Modules\User\Support\MenuSectionNav;
use App\Modules\User\Support\MenuTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "Section jumping - suggest multi different layout type
 * options: horizontal scroll tabs with all, select drop down, verticle tab
 * with icon display or number (default)" and "section headings should have
 * numbers default, option with selecting icons also".
 *
 * What this file guards, hardest first:
 *
 *   - THE BAR AND THE HEADINGS AGREE. Both number the sections from the
 *     same tree, in the order it is drawn. A tab marked 3 landing on a
 *     heading marked 4 is the one failure that makes the whole feature
 *     read as broken;
 *   - THE BAR CANNOT OFFER A SECTION THE PAGE DOES NOT SHOW. A jump link to
 *     a hidden section is a link to nowhere, and the customer who taps it
 *     decides the menu is broken rather than that the section was hidden;
 *   - every shape in the catalogue RENDERS and is SAVEABLE, walked rather
 *     than listed, so a fifth one is covered the day it is added;
 *   - an icon marker falls back to the number. A creator who picks Icons
 *     and has not set them all must not get gaps;
 *   - anchors are built from row ids, so two sections called "Specials" do
 *     not collide and renaming one does not break a shared link.
 */
class ACustomerCanJumpDownALongMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner '.Str::random(4), 'email' => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->owner);
    }

    /** @return array{0: Link, 1: object, 2: array} link, menu, schema */
    private function page(string $kind = 'restaurant'): array
    {
        if ($kind === 'restaurant') {
            $link = Link::create([
                'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
                'alias' => Link::generateAlias(), 'title' => 'Priyumm Tiffins', 'is_active' => true,
            ]);
            $menu = RestaurantMenu::create([
                'link_id' => $link->id, 'user_id' => $this->owner->id,
                'mode' => 'display', 'currency' => 'INR', 'settings' => [],
            ]);
            $schema = ['category' => RestaurantMenuCategory::class, 'item' => RestaurantMenuItem::class];
        } else {
            $link = Link::create([
                'user_id' => $this->owner->id, 'type' => 'store_menu',
                'alias' => Link::generateAlias(), 'title' => 'Clay & Co', 'is_active' => true,
            ]);
            $menu = StoreMenu::create([
                'link_id' => $link->id, 'user_id' => $this->owner->id,
                'mode' => 'display', 'currency' => 'INR', 'settings' => [],
            ]);
            $schema = ['category' => StoreCategory::class, 'item' => StoreProduct::class];
        }

        return [$link, $menu, $schema];
    }

    private function section($menu, array $schema, string $name, int $sort, ?string $icon = null, bool $active = true)
    {
        $cat = $schema['category']::create([
            'menu_id' => $menu->id, 'name' => $name, 'icon' => $icon,
            'sort_order' => $sort, 'is_active' => $active,
        ]);

        // A section with no items is pruned from the tree, so every one of
        // these needs a dish to exist at all.
        $schema['item']::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => $name.' dish',
            'price' => 100, 'currency' => 'INR', 'sort_order' => 0, 'is_active' => true,
        ]);

        return $cat;
    }

    private function tree($menu, array $schema): array
    {
        // The same call the public page makes, so this test cannot pass
        // against a tree the page never builds.
        return MenuTree::build(
            $schema['category']::where('menu_id', $menu->id)->orderBy('sort_order')->get(),
            $schema['item']::where('menu_id', $menu->id)->orderBy('sort_order')->get(),
        );
    }

    private function publicPage(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    // ── The one that makes or breaks the feature ──────────────────

    /**
     * A tab marked 3 must land on the heading marked 3.
     *
     * Both halves number from the same tree in the order it is drawn. If
     * they ever counted separately — one skipping hidden sections, the
     * other not — every number on the page would be off by one somewhere
     * down the card, and the feature would read as broken rather than
     * subtly wrong.
     */
    public function test_the_bar_and_the_headings_number_the_same_sections(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0);
        $this->section($menu, $schema, 'Hidden Away', 1, null, active: false);
        $this->section($menu, $schema, 'Drinks', 2);
        $this->section($menu, $schema, 'Desserts', 3);

        $targets = MenuSectionNav::targets($this->tree($menu, $schema));

        $this->assertSame(
            ['Tiffins' => 1, 'Drinks' => 2, 'Desserts' => 3],
            collect($targets)->mapWithKeys(fn ($t) => [$t['name'] => $t['number']])->all(),
            'the bar is numbering around a hidden section differently from the headings'
        );

        $html = $this->publicPage($link);

        // Every target's anchor exists on the page, and its number is drawn
        // beside the right heading.
        foreach ($targets as $t) {
            $this->assertStringContainsString(
                'id="'.$t['anchor'].'"',
                $html,
                $t['name'].': the bar points at an anchor the page does not have'
            );
            $this->assertMatchesRegularExpression(
                '/id="'.preg_quote($t['anchor'], '/').'"[\s\S]{0,400}?>'.$t['number'].'<[\s\S]{0,200}?'.preg_quote($t['name'], '/').'/',
                $html,
                $t['name'].': the heading is numbered differently from its tab'
            );
        }
    }

    public function test_the_bar_cannot_offer_a_section_the_page_does_not_show(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0);
        $this->section($menu, $schema, 'Secret Menu', 1, null, active: false);

        $names = array_column(MenuSectionNav::targets($this->tree($menu, $schema)), 'name');

        $this->assertSame(
            ['Tiffins'],
            $names,
            'a hidden section is in the jump bar — that is a link to nowhere'
        );
        $this->assertStringNotContainsString('Secret Menu', $this->publicPage($link));
    }

    // ── Every shape in the catalogue ──────────────────────────────

    /**
     * Walked, not listed.
     *
     * The catalogue is the only place the four shapes are written down, so
     * a fifth is covered here the afternoon somebody adds it — which is the
     * rule this codebase has had to learn repeatedly.
     */
    public function test_every_nav_shape_renders_and_saves(): void
    {
        foreach (array_keys(MenuSectionNav::NAVS) as $key) {
            [$link, $menu, $schema] = $this->page();
            $this->section($menu, $schema, 'Tiffins', 0);
            $this->section($menu, $schema, 'Drinks', 1);

            // Saved through the editor's own endpoint, so a shape that the
            // catalogue offers and the save silently drops is caught here
            // rather than by somebody picking it on a live menu.
            $this->actingAs($this->owner)
                ->postJson(route('user.links.restaurant.settings', $link), [
                    'mode'        => 'display',
                    'currency'    => 'INR',
                    'section_nav' => $key,
                ])->assertSuccessful();

            $this->assertSame(
                $key,
                $menu->fresh()->settings['section_nav'] ?? null,
                $key.': the editor offers it and the save dropped it'
            );

            $html = $this->publicPage($link);

            if ($key === 'none') {
                $this->assertStringNotContainsString('class="sn ', $html, 'none still drew a jump bar');
                continue;
            }

            $this->assertStringContainsString('sn-'.($key === 'vertical' ? 'rail' : ($key === 'dropdown' ? 'drop' : 'tabs')), $html, $key.': did not render');
            // And it actually points somewhere.
            $this->assertStringContainsString('#sec-', $html, $key.': rendered with no jump targets');
        }
    }

    public function test_every_marker_renders_and_saves(): void
    {
        foreach (array_keys(MenuSectionNav::MARKERS) as $key) {
            [$link, $menu, $schema] = $this->page();
            $this->section($menu, $schema, 'Tiffins', 0, 'mug-hot');
            $this->section($menu, $schema, 'Drinks', 1, 'leaf');

            $this->actingAs($this->owner)
                ->postJson(route('user.links.restaurant.settings', $link), [
                    'mode'           => 'display',
                    'currency'       => 'INR',
                    'section_marker' => $key,
                ])->assertSuccessful();

            $this->assertSame(
                $key,
                $menu->fresh()->settings['section_marker'] ?? null,
                $key.': offered and dropped'
            );

            $html = $this->publicPage($link);

            // Asserted on the RENDERED ELEMENT, not the class name: the
            // stylesheet carries "sn-n" too, so a contains-check on the
            // bare class passes whether or not anything was drawn. Same
            // mistake as the in-flight guard and the export buttons.
            $drawnNumber = (bool) preg_match('/<span class="sn-n">\s*\d+\s*<\/span>/', $html);
            $drawnIcon   = str_contains($html, 'fa-mug-hot');

            if ($key === 'icon') {
                $this->assertTrue($drawnIcon, 'the icon marker did not draw the icon');
            } elseif ($key === 'number') {
                $this->assertTrue($drawnNumber, 'the number marker did not draw a number');
            } else {
                $this->assertFalse($drawnNumber, '"nothing" still drew a number');
                $this->assertFalse($drawnIcon, '"nothing" still drew an icon');
            }
        }
    }

    /** "All" is first, and goes back to the top. */
    public function test_the_tab_bar_can_undo_itself(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0);
        $this->section($menu, $schema, 'Drinks', 1);
        $menu->update(['settings' => ['section_nav' => 'tabs']]);

        $html = $this->publicPage($link);

        // A jump bar you cannot get out of is how somebody loses the start
        // of the menu.
        $this->assertMatchesRegularExpression('/class="sn-tab"[^>]*>\s*All/s', $html);
    }

    // ── Falling back rather than leaving a gap ────────────────────

    public function test_a_section_with_no_icon_falls_back_to_its_number(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0, 'mug-hot');
        $this->section($menu, $schema, 'Drinks', 1);   // no icon
        $menu->update(['settings' => ['section_marker' => 'icon', 'section_nav' => 'tabs']]);

        $html = $this->publicPage($link);

        $this->assertStringContainsString('fa-mug-hot', $html);
        $this->assertMatchesRegularExpression(
            '/<span class="sn-n">\s*2\s*<\/span>/',
            $html,
            'a section with no icon rendered a gap — the creator cannot tell what went wrong'
        );
    }

    public function test_an_icon_the_catalogue_does_not_have_is_not_drawn(): void
    {
        $this->assertNull(MenuSectionNav::icon('definitely-not-an-icon'));
        $this->assertNull(MenuSectionNav::icon(null));
        $this->assertSame('leaf', MenuSectionNav::icon('leaf'));

        [$link, $menu, $schema] = $this->page();
        $cat = $this->section($menu, $schema, 'Tiffins', 0);
        // Straight into the row, as a bad migration or an old export might.
        $cat->forceFill(['icon' => 'fa-solid fa-skull'])->saveQuietly();
        $menu->update(['settings' => ['section_marker' => 'icon']]);

        $html = $this->publicPage($link);

        $this->assertStringNotContainsString('fa-skull', $html, 'an unknown icon reached the page');
        $this->assertMatchesRegularExpression(
            '/<span class="sn-n">\s*1\s*<\/span>/',
            $html,
            'and it did not fall back to the number'
        );
    }

    // ── Anchors ───────────────────────────────────────────────────

    /**
     * Two sections called "Specials" are two anchors.
     *
     * A slug of the name would collide, and renaming a section would break
     * every link anybody shared to it.
     */
    public function test_anchors_come_from_the_row_not_the_name(): void
    {
        [$link, $menu, $schema] = $this->page();
        $a = $this->section($menu, $schema, 'Specials', 0);
        $b = $this->section($menu, $schema, 'Specials', 1);

        $targets = MenuSectionNav::targets($this->tree($menu, $schema));

        $this->assertNotSame($targets[0]['anchor'], $targets[1]['anchor'], 'two sections share one anchor');
        $this->assertSame('sec-'.$a->id, $targets[0]['anchor']);

        // And the anchor survives a rename.
        $a->update(['name' => 'Chef Picks']);
        $this->assertSame(
            'sec-'.$a->id,
            MenuSectionNav::targets($this->tree($menu->fresh(), $schema))[0]['anchor'],
            'renaming a section broke every link pointing at it'
        );
    }

    // ── Not drawn when it would be noise ──────────────────────────

    public function test_one_section_gets_no_jump_bar(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Everything', 0);
        $menu->update(['settings' => ['section_nav' => 'tabs']]);

        $this->assertFalse(MenuSectionNav::worthDrawing('tabs', 1));
        $this->assertStringNotContainsString(
            'class="sn ',
            $this->publicPage($link),
            'a one-section menu drew a map of itself'
        );
    }

    public function test_the_default_is_tabs_and_numbers(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0);
        $this->section($menu, $schema, 'Drinks', 1);

        // No settings at all — what every existing menu looks like.
        $html = $this->publicPage($link);

        $this->assertStringContainsString('sn-tabs', $html, 'the default is not the tab bar');
        $this->assertMatchesRegularExpression('/<span class="sn-n">\s*1\s*<\/span>/', $html, 'the default marker is not numbers');
    }

    // ── Parity, and the editor ────────────────────────────────────

    public function test_the_store_gets_the_same_jumping(): void
    {
        [$link, $menu, $schema] = $this->page('store');
        $this->section($menu, $schema, 'Mugs', 0);
        $this->section($menu, $schema, 'Plates', 1);

        $html = $this->publicPage($link);

        $this->assertStringContainsString('sn-tabs', $html, 'store/restaurant parity: the store has no jump bar');
        $this->assertStringContainsString('#sec-', $html);
    }

    /** Every shape the catalogue holds is offered by the editor. */
    public function test_the_editor_offers_every_shape(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Tiffins', 0);

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.editor', $link))
            ->assertOk()->getContent();

        foreach (MenuSectionNav::NAVS as $key => $meta) {
            $this->assertStringContainsString(
                'value="'.$key.'"',
                $html,
                $key.': in the catalogue and not in the editor'
            );
            $this->assertStringContainsString(e($meta['label']), $html);
        }

        foreach (MenuSectionNav::MARKERS as $key => $meta) {
            $this->assertStringContainsString('value="'.$key.'"', $html, $key.': marker missing from the editor');
        }
    }

    /** A section's icon is validated AND saved. */
    public function test_a_section_icon_is_saved_not_just_accepted(): void
    {
        [$link, $menu, $schema] = $this->page();

        $this->actingAs($this->owner)
            ->postJson(route('user.links.restaurant.categories.store', $link), [
                'name' => 'Drinks',
                'icon' => 'mug-hot',
            ])->assertSuccessful();

        $this->assertSame(
            'mug-hot',
            $schema['category']::where('menu_id', $menu->id)->where('name', 'Drinks')->value('icon'),
            'the icon passed validation and was never written — a control that exists and does nothing'
        );
    }

    public function test_navigation_colours_survive_saving_reloading_and_public_rendering(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            [$link, $menu, $schema] = $this->page($kind);
            $this->section($menu, $schema, 'Breakfast', 0);
            $this->section($menu, $schema, 'Lunch', 1);
            $colours = [
                'section_nav_text_color' => '#713f12',
                'section_nav_background_color' => '#fff7ed',
                'section_nav_border_color' => '#fb923c',
            ];
            $this->actingAs($this->owner)->postJson(
                route('user.links.'.$kind.'.settings', $link),
                $colours + ['mode' => $menu->mode, 'currency' => $menu->currency,
                    'section_nav' => 'vertical', 'section_marker' => 'none']
            )->assertSuccessful();
            foreach ($colours as $key => $colour) {
                $this->assertSame($colour, $menu->fresh()->settings[$key]);
            }
            $public = $this->publicPage($link);
            $this->assertStringContainsString('--sn-text: #713f12', $public);
            $this->assertStringContainsString('--sn-bg: #fff7ed', $public);
            $this->assertStringContainsString('--sn-border: #fb923c', $public);
            $editor = $this->get(route('user.links.'.$kind.'.editor', $link))->assertOk()->getContent();
            foreach ($colours as $key => $colour) {
                $this->assertStringContainsString('"'.$key.'":"'.$colour.'"', $editor);
                $this->assertStringContainsString($key.':this.menu.'.$key, $editor);
            }
            $this->assertStringContainsString('"section_nav":"vertical"', $editor);
            $this->assertStringContainsString('section_nav:this.menu.section_nav', $editor);
            $this->assertStringContainsString('section_marker:this.menu.section_marker', $editor);
            $this->postJson(route('user.links.'.$kind.'.settings', $link), [
                'mode' => $menu->mode, 'currency' => $menu->currency,
                'section_nav_text_color' => 'red; background:url(example.com)',
            ])->assertStatus(422);
            $this->assertSame('#713f12', $menu->fresh()->settings['section_nav_text_color']);
        }
    }

    public function test_unconfigured_navigation_uses_readable_colours(): void
    {
        [$link, $menu, $schema] = $this->page();
        $this->section($menu, $schema, 'Breakfast', 0);
        $this->section($menu, $schema, 'Lunch', 1);
        $html = $this->publicPage($link);
        $this->assertStringContainsString('--sn-text: #262626', $html);
        $this->assertStringContainsString('--sn-bg: #ffffff', $html);
    }

    public function test_section_text_can_be_hidden_independently_without_hiding_items(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            [$link, $menu, $schema] = $this->page($kind);
            $parent = $this->section($menu, $schema, 'Breakfast', 0);
            $child = $this->section($menu, $schema, 'Idli', 0);
            $child->update(['parent_id' => $parent->id]);
            $this->section($menu, $schema, 'Lunch', 1);

            foreach ([[false, false], [true, false], [false, true], [true, true]] as [$heading, $description]) {
                $parent->update(['description' => 'Breakfast description', 'hide_heading' => $heading, 'hide_description' => $description]);
                $child->update(['description' => 'Idli description', 'hide_heading' => $heading, 'hide_description' => $description]);
                $html = $this->publicPage($link);
                $this->assertStringContainsString('Breakfast dish', $html);
                $this->assertStringContainsString('Idli dish', $html);
                $this->assertStringContainsString('Lunch dish', $html);
                $this->assertStringContainsString('id="sec-'.$parent->id.'"', $html);
                $this->assertStringContainsString('href="#sec-'.$parent->id.'"', $html);
                $this->assertSame(! $heading, preg_match('/<h2>.*?Breakfast.*?<\/h2>/s', $html) === 1);
                $this->assertSame(! $heading, str_contains($html, '<h3>Idli</h3>'));
                $this->assertSame(! $description, str_contains($html, '<p class="cdesc">Breakfast description</p>'));
                $this->assertSame(! $description, str_contains($html, '<p class="cdesc">Idli description</p>'));
            }

            // Hiding the entire section keeps its previous meaning.
            $parent->update(['is_active' => false]);
            $html = $this->publicPage($link);
            $this->assertStringNotContainsString('Breakfast dish', $html);
            $this->assertStringNotContainsString('Idli dish', $html);
            $this->assertStringContainsString('Lunch dish', $html);
        }
    }

    public function test_section_text_visibility_is_saved_and_reloaded_in_both_editors(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            [$link, $menu, $schema] = $this->page($kind);
            $this->actingAs($this->owner)->postJson(route('user.links.'.$kind.'.categories.store', $link), [
                'name' => 'Breakfast', 'hide_heading' => true, 'hide_description' => true,
            ])->assertCreated();
            $category = $schema['category']::where('menu_id', $menu->id)->firstOrFail();
            $this->assertTrue($category->hide_heading);
            $this->assertTrue($category->hide_description);
            $this->putJson(route('user.links.'.$kind.'.categories.update', [$link, $category]), [
                'hide_heading' => false,
            ])->assertOk();
            $this->assertFalse($category->fresh()->hide_heading);
            $this->assertTrue($category->fresh()->hide_description);
            $editor = $this->get(route('user.links.'.$kind.'.editor', $link))->assertOk()->getContent();
            $this->assertStringContainsString('"hide_heading":false', $editor);
            $this->assertStringContainsString('"hide_description":true', $editor);
            $this->assertStringContainsString('x-model="catModal.hide_heading"', $editor);
            $this->assertStringContainsString('x-model="catModal.hide_description"', $editor);
            $this->assertStringContainsString('hide_heading:!!this.catModal.hide_heading', $editor);
            $this->putJson(route('user.links.'.$kind.'.categories.update', [$link, $category]), [
                'hide_heading' => 'not-a-boolean',
            ])->assertStatus(422);
            $this->assertFalse($category->fresh()->hide_heading);
        }
    }

    public function test_an_icon_outside_the_catalogue_is_refused(): void
    {
        [$link] = $this->page();

        $this->actingAs($this->owner)
            ->postJson(route('user.links.restaurant.categories.store', $link), [
                'name' => 'Drinks',
                'icon' => '<script>alert(1)</script>',
            ])->assertStatus(422);
    }
}
