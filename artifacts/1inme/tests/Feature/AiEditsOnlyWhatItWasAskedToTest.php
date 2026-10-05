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
use App\Modules\User\Models\MenuItemMark;
use App\Modules\User\Support\MenuItemMarks;
use App\Modules\User\Support\MenuPresentation;
use App\Services\AI\Builder\AiRestaurantMenuBuilderService;
use App\Services\AI\Builder\AiStoreMenuBuilderService;
use App\Services\AI\Editor\MenuEditPlan;
use App\Services\AI\Editor\MenuEditVocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "it should not be modify whole.. it should able to
 * update as per instructions... it should able to change backgroud, add and
 * modify items, add stickers or any blocks or update price or anything
 * features available".
 *
 * The previous shape sent the whole menu to the model and asked it to
 * return the whole menu back with one thing different. That is not a
 * modification; it is a rebuild with a polite request attached, and it
 * stakes eighty dishes on one model's willingness to copy seventy-nine rows
 * back correctly.
 *
 * What this file guards, hardest first:
 *
 *   - A ROW NOT NAMED IS NOT WRITTEN TO. Not re-saved with the same values.
 *     Not touched. This is the whole claim the word "modify" makes, and
 *     everything else here is detail;
 *   - "anything features available" is kept true by GENERATION, not by a
 *     list. Every colour, layout, divider, heading and price style the
 *     editor offers is addressable by the AI, because both halves read the
 *     same catalogue -- and a test walks the catalogue rather than a copy
 *     of it, so a seventh colour is covered the day it is added;
 *   - an operation that cannot be applied is REPORTED, not silently
 *     dropped. A tool that says "done" having done three of five is the
 *     thing that teaches people not to use it;
 *   - an ambiguous name is skipped rather than guessed. Picking the first
 *     of two dishes called "Chicken Curry" puts a price on the wrong row
 *     and reports success while doing it;
 *   - restaurant and store behave identically, from one list of assertions.
 */
class AiEditsOnlyWhatItWasAskedToTest extends TestCase
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

        // The builder screen 404s with the engine off, so without this the
        // screen tests would assert nothing at all. ::put() because
        // AppSetting keeps an in-process key set that a direct row write
        // does not update.
        \App\Modules\Admin\Models\AppSetting::put(\App\Services\AI\AiEngineSettings::KEY_ENABLED, '1');
        $this->assertTrue(\App\Services\AI\AiEngineSettings::isEnabled(), 'could not switch the AI engine on');
    }

    // ── Building the two page types ───────────────────────────────

    /**
     * Both menus, from one shape.
     *
     * @return array{0: Link, 1: object, 2: array}  link, menu row, schema
     */
    private function page(string $kind): array
    {
        if ($kind === 'restaurant') {
            $link = Link::create([
                'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
                'alias' => Link::generateAlias(), 'title' => 'Priyumm Tiffins', 'is_active' => true,
            ]);
            $menu = RestaurantMenu::create([
                'link_id' => $link->id, 'user_id' => $this->owner->id,
                'mode' => 'order', 'currency' => 'INR', 'settings' => [],
            ]);
            $schema = [
                'category' => RestaurantMenuCategory::class,
                'item' => RestaurantMenuItem::class,
                'sold_out' => 'is_sold_out', 'item_noun' => 'item',
            ];
        } else {
            $link = Link::create([
                'user_id' => $this->owner->id, 'type' => 'store_menu',
                'alias' => Link::generateAlias(), 'title' => 'Clay & Co', 'is_active' => true,
            ]);
            $menu = StoreMenu::create([
                'link_id' => $link->id, 'user_id' => $this->owner->id,
                'mode' => 'order', 'currency' => 'INR', 'settings' => [],
            ]);
            $schema = [
                'category' => StoreCategory::class,
                'item' => StoreProduct::class,
                'sold_out' => 'is_out_of_stock', 'item_noun' => 'product',
            ];
        }

        $tiffins = $schema['category']::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        $drinks = $schema['category']::create([
            'menu_id' => $menu->id, 'name' => 'Drinks', 'sort_order' => 1, 'is_active' => true,
        ]);

        foreach ([['Masala Dosa', 120, $tiffins], ['Poori', 80, $tiffins], ['Filter Coffee', 30, $drinks]] as [$n, $p, $c]) {
            $schema['item']::create([
                'menu_id' => $menu->id, 'category_id' => $c->id, 'name' => $n,
                'description' => 'As it was', 'price' => $p, 'currency' => 'INR',
                'sort_order' => 0, 'is_active' => true,
            ]);
        }

        return [$link, $menu, $schema];
    }

    /** Both page types, so parity is run rather than hoped for. */
    private function bothKinds(): array
    {
        return ['restaurant', 'store'];
    }

    private function apply(array $ops, $menu, Link $link, array $schema): array
    {
        return MenuEditPlan::apply($ops, $menu, $link, $schema);
    }

    // ── The claim the word "modify" makes ─────────────────────────

    /**
     * The headline. One operation changes one row and nothing else.
     *
     * Asserted on the stored values AND on updated_at, because "we rewrote
     * it with the same values" is not the same promise as "we did not touch
     * it" -- and only the second one is safe to make about somebody's live
     * menu.
     */
    public function test_a_row_nobody_named_is_not_written_to(): void
    {
        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);

            $others = $schema['item']::where('menu_id', $menu->id)
                ->where('name', '!=', 'Masala Dosa')->get()
                ->mapWithKeys(fn ($i) => [$i->id => $i->updated_at?->toISOString()])->all();

            // A full second, so a same-second write cannot hide behind the
            // timestamp's own resolution.
            sleep(1);

            $report = $this->apply([
                ['op' => 'item.update', 'item' => 'Masala Dosa', 'product' => 'Masala Dosa', 'price' => 60],
            ], $menu, $link, $schema);

            $this->assertCount(1, $report['applied'], $kind.': one instruction, one change');
            $this->assertSame([], $report['skipped'], $kind.': nothing should have been skipped');

            $this->assertEquals(
                60.0,
                (float) $schema['item']::where('menu_id', $menu->id)->where('name', 'Masala Dosa')->value('price'),
                $kind.': the named row did not change'
            );

            foreach ($others as $id => $stamp) {
                $this->assertSame(
                    $stamp,
                    $schema['item']::find($id)->updated_at?->toISOString(),
                    $kind.': a row nobody named was written to — this is the whole promise of "modify"'
                );
            }

            // And the descriptions nobody mentioned are still the ones the
            // creator wrote, not the model's improved version of them.
            $this->assertSame(
                ['As it was', 'As it was', 'As it was'],
                $schema['item']::where('menu_id', $menu->id)->orderBy('id')->pluck('description')->all(),
                $kind.': a description nobody asked about was rewritten'
            );
        }
    }

    public function test_the_menu_is_not_emptied_and_rewritten(): void
    {
        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);
            $ids = $schema['item']::where('menu_id', $menu->id)->orderBy('id')->pluck('id')->all();

            $this->apply([
                ['op' => 'item.update', 'item' => 'Poori', 'product' => 'Poori', 'price' => 95],
            ], $menu, $link, $schema);

            $this->assertSame(
                $ids,
                $schema['item']::where('menu_id', $menu->id)->orderBy('id')->pluck('id')->all(),
                $kind.': the rows were deleted and recreated — every order history pointing at them is now orphaned'
            );
        }
    }

    // ── The content operations ────────────────────────────────────

    public function test_items_can_be_added_renamed_hidden_and_removed(): void
    {
        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);
            $noun = $schema['item_noun'];

            $report = $this->apply([
                ['op' => 'item.add', 'category' => 'Drinks', 'name' => 'Masala Chai', 'price' => 25],
                ['op' => 'item.update', $noun => 'Poori', 'name' => 'Poori (2 pcs)', 'hidden' => true],
                ['op' => 'item.update', $noun => 'Filter Coffee', 'sold_out' => true],
                ['op' => 'item.remove', $noun => 'Masala Dosa'],
            ], $menu, $link, $schema);

            $this->assertSame([], $report['skipped'], $kind.': '.json_encode($report['skipped']));
            $this->assertCount(4, $report['applied']);

            $items = $schema['item']::where('menu_id', $menu->id)->get()->keyBy('name');

            $this->assertTrue($items->has('Masala Chai'), $kind.': the added item is missing');
            $this->assertEquals(25.0, (float) $items['Masala Chai']->price);
            $this->assertSame('INR', $items['Masala Chai']->currency, $kind.': a new item took the wrong currency');

            $this->assertTrue($items->has('Poori (2 pcs)'), $kind.': the rename did not take');
            $this->assertFalse((bool) $items['Poori (2 pcs)']->is_active, $kind.': hidden did not take');

            $this->assertTrue((bool) $items['Filter Coffee']->{$schema['sold_out']}, $kind.': sold out did not take');

            $this->assertFalse($items->has('Masala Dosa'), $kind.': the removal did not take');
        }
    }

    public function test_a_section_can_be_added_renamed_and_removed_with_its_items(): void
    {
        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);

            $report = $this->apply([
                ['op' => 'category.add', 'name' => 'Desserts', 'description' => 'Sweet things'],
                ['op' => 'category.update', 'category' => 'Tiffins', 'name' => 'Breakfast'],
                ['op' => 'category.remove', 'category' => 'Drinks'],
            ], $menu, $link, $schema);

            $this->assertSame([], $report['skipped'], $kind.': '.json_encode($report['skipped']));

            $cats = $schema['category']::where('menu_id', $menu->id)->pluck('name')->all();
            $this->assertContains('Desserts', $cats);
            $this->assertContains('Breakfast', $cats);
            $this->assertNotContains('Drinks', $cats);

            // The items went with the section. A dish left behind when its
            // section is deleted belongs to nothing: invisible on the page
            // and still counted in every total.
            $this->assertSame(
                0,
                $schema['item']::where('menu_id', $menu->id)->where('name', 'Filter Coffee')->count(),
                $kind.': removing a section left its items orphaned'
            );
            // And the other section's items are untouched.
            $this->assertSame(
                2,
                $schema['item']::where('menu_id', $menu->id)->count(),
                $kind.': removing one section took items from another with it'
            );
        }
    }

    public function test_a_new_item_lands_in_the_section_it_was_given(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $this->apply([
            ['op' => 'item.add', 'category' => 'Drinks', 'name' => 'Lassi', 'price' => 50],
        ], $menu, $link, $schema);

        $drinks = $schema['category']::where('menu_id', $menu->id)->where('name', 'Drinks')->first();

        $this->assertSame(
            $drinks->id,
            (int) $schema['item']::where('menu_id', $menu->id)->where('name', 'Lassi')->value('category_id'),
            'a new item was filed under the wrong section'
        );
    }

    // ── "anything features available", kept true by generation ────

    /**
     * The guard that stops this feature going stale.
     *
     * Every appearance control the EDITOR offers is walked, set through the
     * AI's own operation, and read back out of the saved settings. Nothing
     * here lists a key: the list is the catalogue the editor's own <select>
     * and colour inputs loop over, so a seventh colour or a sixth layout is
     * covered the afternoon somebody adds it.
     *
     * This is the sixteenth time in this codebase that a control existed
     * and did nothing. It is the second time a guard has been written to
     * stop it happening again in this exact place.
     */
    public function test_every_appearance_control_the_editor_offers_can_be_set_by_ai(): void
    {
        $catalogue = [
            'colours'  => array_keys(MenuPresentation::COLOURS),
            'layout'   => array_keys(MenuPresentation::LAYOUTS),
            'divider'  => array_keys(MenuPresentation::DIVIDERS),
            'heading'  => array_keys(MenuPresentation::HEADINGS),
            'price'    => array_keys(MenuPresentation::PRICES),
        ];

        $addressable = MenuEditVocabulary::appearanceKeys();
        $prompt      = MenuEditVocabulary::prompt();

        [$link, $menu, $schema] = $this->page('restaurant');

        foreach ($catalogue['colours'] as $key) {
            $this->assertArrayHasKey($key, $addressable, $key.' is offered in the editor and cannot be asked for');
            $this->assertStringContainsString($key, $prompt, $key.' is accepted but the model is never told about it');

            $this->apply([['op' => 'appearance.set', 'key' => $key, 'value' => '#ab12cd']], $menu, $link, $schema);

            $this->assertSame(
                '#ab12cd',
                $menu->fresh()->settings[$key] ?? null,
                $key.': the AI set it and it did not reach the saved settings'
            );
        }

        foreach (['layout' => 'layout', 'divider' => 'divider', 'heading_style' => 'heading', 'price_style' => 'price'] as $key => $bucket) {
            $this->assertArrayHasKey($key, $addressable, $key.' is a setting with no AI operation');

            foreach ($catalogue[$bucket] as $value) {
                $this->assertStringContainsString($value, $prompt, $key.'='.$value.' is allowed but never offered to the model');

                $this->apply([['op' => 'appearance.set', 'key' => $key, 'value' => $value]], $menu, $link, $schema);

                $this->assertSame(
                    $value,
                    $menu->fresh()->settings[$key] ?? null,
                    $key.'='.$value.': accepted and not saved'
                );
            }
        }
    }

    /** "it should able to change backgroud" — the first thing he asked for. */
    public function test_the_page_background_and_font_can_be_changed(): void
    {
        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);

            $report = $this->apply([
                ['op' => 'appearance.set', 'key' => 'background_color', 'value' => '#fdf6e3'],
                ['op' => 'appearance.set', 'key' => 'font_family', 'value' => 'Playfair Display'],
                ['op' => 'appearance.set', 'key' => 'background_gradient', 'value' => 'linear-gradient(180deg, #fff 0%, #eee 100%)'],
            ], $menu, $link, $schema);

            $this->assertSame([], $report['skipped'], $kind.': '.json_encode($report['skipped']));

            $bs = $link->fresh()->settings['biolink'] ?? [];

            $this->assertSame('#fdf6e3', $bs['background_color'] ?? null, $kind.': the background colour did not save');
            $this->assertSame('Playfair Display', $bs['font_family'] ?? null, $kind.': the font did not save');
            $this->assertStringContainsString('linear-gradient', $bs['background_gradient'] ?? '', $kind.': the gradient did not save');
        }
    }

    /**
     * Page settings and menu settings are two different JSON columns, and
     * an edit that writes one must not blank the other -- or anything else
     * already in it.
     */
    public function test_setting_one_thing_does_not_wipe_the_settings_around_it(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $menu->update(['settings' => ['layout' => 'grid', 'whatsapp_number' => '+911234567890']]);
        $link->update(['settings' => ['biolink' => ['font_family' => 'Inter'], 'seo' => ['title' => 'Keep me']]]);

        $this->apply([
            ['op' => 'appearance.set', 'key' => 'price_color', 'value' => '#004400'],
            ['op' => 'appearance.set', 'key' => 'background_color', 'value' => '#ffffff'],
        ], $menu->fresh(), $link->fresh(), $schema);

        $settings = $menu->fresh()->settings;
        $this->assertSame('grid', $settings['layout'] ?? null, 'an unrelated menu setting was lost');
        $this->assertSame('+911234567890', $settings['whatsapp_number'] ?? null, 'the WhatsApp number was lost');
        $this->assertSame('#004400', $settings['price_color'] ?? null);

        $all = $link->fresh()->settings;
        $this->assertSame('Keep me', $all['seo']['title'] ?? null, 'a sibling key in link.settings was lost');
        $this->assertSame('Inter', $all['biolink']['font_family'] ?? null, 'a sibling key in the page settings was lost');
        $this->assertSame('#ffffff', $all['biolink']['background_color'] ?? null);
    }

    /** Two appearance operations in one plan; the second must not eat the first. */
    public function test_two_settings_changes_in_one_plan_both_survive(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $this->apply([
            ['op' => 'appearance.set', 'key' => 'heading_color', 'value' => '#111111'],
            ['op' => 'appearance.set', 'key' => 'price_color', 'value' => '#222222'],
            ['op' => 'appearance.set', 'key' => 'layout', 'value' => 'compact'],
        ], $menu, $link, $schema);

        $s = $menu->fresh()->settings;
        $this->assertSame('#111111', $s['heading_color'] ?? null, 'the first settings change was overwritten by the second');
        $this->assertSame('#222222', $s['price_color'] ?? null);
        $this->assertSame('compact', $s['layout'] ?? null);
    }

    // ── What it refuses, and says so ──────────────────────────────

    public function test_an_ambiguous_name_is_skipped_rather_than_guessed(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');
        $drinks = $schema['category']::where('menu_id', $menu->id)->where('name', 'Drinks')->first();

        $schema['item']::create([
            'menu_id' => $menu->id, 'category_id' => $drinks->id, 'name' => 'Filter Coffee Large',
            'price' => 45, 'currency' => 'INR', 'sort_order' => 1, 'is_active' => true,
        ]);

        $report = $this->apply([
            ['op' => 'item.update', 'item' => 'Filter Coff', 'price' => 999],
        ], $menu, $link, $schema);

        $this->assertSame([], $report['applied'], 'an ambiguous name was resolved by guessing');
        $this->assertCount(1, $report['skipped']);
        $this->assertStringContainsString('matches 2', $report['skipped'][0]['why']);

        // And neither of them moved.
        $this->assertEqualsWithDelta(30.0, (float) $schema['item']::where('menu_id', $menu->id)->where('name', 'Filter Coffee')->value('price'), 0.01);
        $this->assertEqualsWithDelta(45.0, (float) $schema['item']::where('menu_id', $menu->id)->where('name', 'Filter Coffee Large')->value('price'), 0.01);
    }

    public function test_a_name_that_matches_nothing_is_reported_not_invented(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');
        $before = $schema['item']::where('menu_id', $menu->id)->count();

        $report = $this->apply([
            ['op' => 'item.update', 'item' => 'Pizza Margherita', 'price' => 400],
        ], $menu, $link, $schema);

        $this->assertSame([], $report['applied']);
        $this->assertStringContainsString('no item called', $report['skipped'][0]['why']);
        $this->assertSame($before, $schema['item']::where('menu_id', $menu->id)->count(), 'a failed update created a row');
    }

    public function test_one_bad_operation_does_not_stop_the_good_ones(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $report = $this->apply([
            ['op' => 'item.update', 'item' => 'Masala Dosa', 'price' => 65],
            ['op' => 'appearance.set', 'key' => 'nonsense_color', 'value' => '#fff'],
            ['op' => 'teleport.menu', 'to' => 'mars'],
            ['op' => 'item.update', 'item' => 'Poori', 'price' => 85],
        ], $menu, $link, $schema);

        $this->assertCount(2, $report['applied'], 'a bad operation took the good ones down with it');
        $this->assertCount(2, $report['skipped']);

        $whys = implode(' | ', array_column($report['skipped'], 'why'));
        $this->assertStringContainsString('is not a setting this page has', $whys);
        $this->assertStringContainsString('not an operation this page understands', $whys);
    }

    public function test_a_colour_that_is_not_a_colour_is_refused(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $report = $this->apply([
            ['op' => 'appearance.set', 'key' => 'price_color', 'value' => 'reddish'],
            ['op' => 'appearance.set', 'key' => 'layout', 'value' => 'hexagonal'],
            // CSS from a model is CSS from a stranger.
            ['op' => 'appearance.set', 'key' => 'background_gradient', 'value' => 'red; } body { display:none'],
        ], $menu, $link, $schema);

        $this->assertSame([], $report['applied'], 'a value the editor would reject was saved anyway');
        $this->assertCount(3, $report['skipped']);
        $this->assertArrayNotHasKey('price_color', $menu->fresh()->settings ?? []);
    }

    public function test_an_empty_plan_fails_loudly_so_the_charge_is_refunded(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        // A RuntimeException is what generate() catches to trigger the
        // refund. A silent "nothing to do" would charge for nothing.
        $this->expectException(\RuntimeException::class);
        $this->apply([], $menu, $link, $schema);
    }

    public function test_a_runaway_plan_is_capped(): void
    {
        [$link, $menu, $schema] = $this->page('restaurant');

        $ops = array_fill(0, MenuEditPlan::MAX_OPERATIONS + 20, [
            'op' => 'category.add', 'name' => 'Section '.Str::random(6),
        ]);

        $report = $this->apply($ops, $menu, $link, $schema);

        $this->assertLessThanOrEqual(
            MenuEditPlan::MAX_OPERATIONS,
            count($report['applied']) + count($report['skipped']),
            'a runaway answer was allowed to rewrite the catalogue'
        );
    }

    // ── Marks: the field that was missing, and what that cost ─────

    /**
     * Sana, 2026-10-05: "i told to update all items marks with veg, non
     * veg and others also.... but it modified with description. how?"
     *
     * Because `marks` was not in the vocabulary. Asked for a change it had
     * no operation for, the model did the nearest thing it could and wrote
     * "Non-Veg" into the DESCRIPTION, where it appeared on the page
     * looking almost right -- which is the worst kind of wrong.
     *
     * The deeper cause is the one this file already had an essay about:
     * only the APPEARANCE half was generated from a catalogue. The item
     * fields were hand-listed. So this walks the marks table the same way
     * the appearance test walks the colours.
     */
    public function test_every_mark_the_picker_offers_can_be_set_by_ai(): void
    {
        MenuItemMarks::forget();
        $pickable = MenuItemMarks::pickable();

        $this->assertGreaterThan(0, $pickable->count(), 'no marks are set up — this test would assert nothing');

        $prompt = MenuEditVocabulary::prompt();
        [$link, $menu, $schema] = $this->page('restaurant');

        foreach ($pickable as $mark) {
            $this->assertStringContainsString(
                '"'.$mark->key.'"',
                $prompt,
                $mark->key.' is offered in the picker and the AI is never told it exists'
            );

            $report = $this->apply([
                ['op' => 'item.update', 'item' => 'Masala Dosa', 'marks' => [$mark->key]],
            ], $menu, $link, $schema);

            $this->assertSame([], $report['skipped'], $mark->key.': '.json_encode($report['skipped']));

            $stored = $schema['item']::where('menu_id', $menu->id)->where('name', 'Masala Dosa')->value('marks');
            $keys = array_column(is_array($stored) ? $stored : (json_decode((string) $stored, true) ?: []), 'key');

            $this->assertContains($mark->key, $keys, $mark->key.': the AI set it and it did not reach the row');
        }
    }

    /** The bug exactly as he hit it: the mark must not land in the text. */
    public function test_setting_marks_does_not_touch_the_description(): void
    {
        MenuItemMarks::forget();
        $mark = MenuItemMarks::pickable()->first();
        $this->assertNotNull($mark);

        foreach ($this->bothKinds() as $kind) {
            [$link, $menu, $schema] = $this->page($kind);

            $this->apply([
                ['op' => 'item.update', 'item' => 'Masala Dosa', 'product' => 'Masala Dosa', 'marks' => [$mark->key]],
            ], $menu, $link, $schema);

            $row = $schema['item']::where('menu_id', $menu->id)->where('name', 'Masala Dosa')->first();

            $this->assertSame(
                'As it was',
                $row->description,
                $kind.': the mark was written into the description — the badge stays unset and the word lands in the wrong place'
            );
        }
    }

    public function test_a_mark_that_does_not_exist_is_refused_rather_than_clearing_the_rest(): void
    {
        MenuItemMarks::forget();
        $real = MenuItemMarks::pickable()->first();
        [$link, $menu, $schema] = $this->page('restaurant');

        $this->apply([
            ['op' => 'item.update', 'item' => 'Masala Dosa', 'marks' => [$real->key]],
        ], $menu, $link, $schema);

        $report = $this->apply([
            ['op' => 'item.update', 'item' => 'Masala Dosa', 'marks' => ['definitely-not-a-mark']],
        ], $menu, $link, $schema);

        $this->assertSame([], $report['applied']);
        $this->assertStringContainsString('no mark here is called', $report['skipped'][0]['why']);

        // And the badge it already had is still on it. Clearing a dish's
        // marks because the model guessed the wrong word is a change
        // nobody asked for.
        $stored = $schema['item']::where('menu_id', $menu->id)->where('name', 'Masala Dosa')->value('marks');
        $keys = array_column(is_array($stored) ? $stored : (json_decode((string) $stored, true) ?: []), 'key');

        $this->assertContains($real->key, $keys, 'a bad mark wiped the marks that were already there');
    }

    public function test_the_prompt_forbids_putting_a_mark_in_the_description(): void
    {
        $prompt = MenuEditVocabulary::prompt();

        // The model reached for the description because nothing told it not
        // to and nothing offered it the right field.
        $this->assertStringContainsString('A mark NEVER goes in "description"', $prompt);
        $this->assertStringContainsString('"marks"', $prompt);
    }

    // ── What a job costs ──────────────────────────────────────────

    /**
     * Sana, 2026-10-05: "for any changes or creating also, 7 coins are
     * used.. is it fixed? or make it realistic actual use".
     *
     * The charge is metered -- computed from the tokens the call really
     * used. But a build and an edit were reserving the same 4000-token
     * ceiling, and that ceiling is what the up-front quote is computed
     * from, so "change one price" was quoted as though it might return an
     * eighty-dish menu. An operations list cannot be that big: the applier
     * caps a plan at 40 operations.
     */
    public function test_an_edit_is_not_quoted_as_though_it_returns_a_whole_menu(): void
    {
        $service = app(AiRestaurantMenuBuilderService::class);

        $this->assertLessThan(
            $service::MAX_OUTPUT_TOKENS,
            $service::EDIT_MAX_OUTPUT_TOKENS,
            'an edit reserves as much room as a full build, so every job is quoted the same'
        );

        [$link] = $this->page('restaurant');

        $this->assertSame($service::EDIT_MAX_OUTPUT_TOKENS, $service->outputBudget(true));
        $this->assertSame($service::MAX_OUTPUT_TOKENS, $service->outputBudget(false));

        // And the budget follows what the run is actually doing, rather
        // than being chosen somewhere the generate path never reads.
        $this->assertTrue($service->isEditing($link));
    }

    // ── The wiring, which is where the damage would be ────────────

    /**
     * The one that matters most.
     *
     * A test of MenuEditPlan is not a test of the pipeline. If generate()
     * still routed a menu through materialize(), every assertion above
     * would pass while the live feature deleted the catalogue exactly as
     * before -- which is the sabotage that got through last time this
     * screen was changed.
     *
     * So this drives the real generate() with a stand-in client that
     * returns an edit plan, and asserts the MENU IS STILL THERE.
     */
    public function test_generate_applies_a_plan_instead_of_rebuilding_the_menu(): void
    {
        $fake = new class extends \App\Services\AI\OpenAiService
        {
            public static array $captured = [];

            public function __construct($ignored = null) {}

            public function chat(User $user, string $model, array $messages, array $opts = []): array
            {
                self::$captured = $messages;

                return [
                    'content' => json_encode(['operations' => [
                        ['op' => 'item.update', 'item' => 'Masala Dosa', 'price' => 60],
                        ['op' => 'appearance.set', 'key' => 'price_color', 'value' => '#004400'],
                    ]]),
                    'credits_spent' => 1,
                ];
            }

            public function estimateChatCoins(string $model, array $messages, int $maxOutputTokens = 4096, ?User $user = null): int
            {
                return 1;
            }
        };
        $fake::$captured = [];

        [$link, $menu, $schema] = $this->page('restaurant');

        $service = new AiRestaurantMenuBuilderService($fake, app(\App\Services\AI\AiUsageCharger::class));

        $this->assertTrue($service->isEditing($link), 'a filled menu is not routed to the edit path at all');

        $result = $service->generate($this->owner, $link, 'Make the masala dosa 60 and the prices dark green', [], [], []);

        // Still three items. A rebuild would have left two -- the ones the
        // "catalogue" in that response contained, which is none.
        $this->assertSame(3, RestaurantMenuItem::where('menu_id', $menu->id)->count(), 'generate() rebuilt the menu instead of editing it');
        $this->assertEqualsWithDelta(60.0, (float) RestaurantMenuItem::where('menu_id', $menu->id)->where('name', 'Masala Dosa')->value('price'), 0.01);
        $this->assertSame('#004400', $menu->fresh()->settings['price_color'] ?? null);

        $this->assertArrayHasKey('applied', $result['summary'], 'the report never reaches the caller');
        $this->assertCount(2, $result['summary']['applied']);

        // And the prompt it was sent is the EDIT contract, not the build one.
        $system = collect($fake::$captured)->firstWhere('role', 'system')['content'] ?? '';
        $this->assertStringContainsString('"operations"', $system, 'the model was asked for a page, not for changes');
        $this->assertStringNotContainsString('Anything you leave out is deleted', $system);
    }

    public function test_an_empty_menu_still_builds_rather_than_editing(): void
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => Link::generateAlias(), 'title' => 'Brand new', 'is_active' => true,
        ]);
        RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'display', 'currency' => 'INR', 'settings' => [],
        ]);

        $service = app(AiRestaurantMenuBuilderService::class);

        // Nothing to edit means nothing to name, and an edit plan against an
        // empty page can only ever return nothing.
        $this->assertFalse($service->isEditing($link), 'a first build was routed through the editor');
    }

    public function test_the_store_is_on_the_same_path_as_the_restaurant(): void
    {
        [$storeLink] = $this->page('store');

        $this->assertTrue(
            app(AiStoreMenuBuilderService::class)->isEditing($storeLink),
            'store/restaurant parity: the store still rebuilds while the restaurant edits'
        );
    }

    // ── The screen ────────────────────────────────────────────────

    private function screen(Link $link): string
    {
        return $this->actingAs($this->owner)
            ->get(route('user.links.ai-type-builder', $link))
            ->assertOk()->getContent();
    }

    public function test_the_screen_no_longer_warns_about_a_rewrite_that_does_not_happen(): void
    {
        [$link] = $this->page('restaurant');
        $html = $this->screen($link);

        $this->assertStringContainsString('Modify with AI', $html);

        // The old warning was true of the old behaviour and is a lie about
        // this one. A warning nobody needs is how people learn to ignore
        // the ones they do.
        $this->assertStringNotContainsString('rewrites the whole', $html);
        $this->assertStringContainsString('Only what you ask for changes', $html);
    }

    /**
     * The screen's list of what can be changed is GENERATED, so it cannot
     * promise something the AI will not do -- or stay quiet about something
     * it will.
     */
    public function test_the_screen_lists_what_can_actually_be_changed(): void
    {
        [$link] = $this->page('restaurant');
        $html = $this->screen($link);

        foreach (MenuEditVocabulary::abilities('item') as $line) {
            $this->assertStringContainsString(
                e($line),
                $html,
                'the screen does not mention something the AI can do: '.$line
            );
        }

        // The count in that list comes from the catalogue, not a number
        // somebody typed.
        $this->assertStringContainsString(
            (string) count(MenuPresentation::COLOURS).' separate colours',
            $html
        );
    }

    public function test_the_screen_shows_what_was_done_and_what_was_not(): void
    {
        [$link] = $this->page('restaurant');
        $html = $this->screen($link);

        // The report panel, and both halves of it. A run that silently
        // drops three of five instructions and lands the creator in the
        // editor with no word about it is the failure this guards.
        $this->assertStringContainsString('ai-edit-report', $html);
        $this->assertStringContainsString('What changed', $html);
        $this->assertStringContainsString('Not done', $html);
        $this->assertMatchesRegularExpression('/report\s*\?\.\s*applied|report\?\.applied/', $html);
    }
}
