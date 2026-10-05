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
use App\Modules\User\Support\MenuPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The guard, rather than the sixteenth individual fix.
 *
 * Sixteen times on this project a control has existed and done nothing, or
 * a capability has existed with no screen offering it. The menu colours
 * alone have produced it twice: the pickers that showed grey whatever was
 * saved, and -- found while adding the two colours Sana asked for on
 * 2026-10-05 -- validation rules typed by hand in each of the two menu
 * controllers while the editor and the save loop both read the catalogue.
 * A colour added to the catalogue got a picker, posted on save, and was
 * dropped by the validator without a word.
 *
 * So this test does not check two colours. It walks MenuPresentation::COLOURS
 * and asserts that EVERY entry survives the whole round trip on BOTH menu
 * types:
 *
 *     the editor offers it
 *       -> the validator accepts it
 *         -> the save stores it
 *           -> the page emits it
 *             -> and some CSS rule actually uses what was emitted
 *
 * The last step is the one that matters. A colour can pass every other
 * check and still be a setting that paints nothing, which is precisely
 * what "default always grey... doesnt seems right" was.
 *
 * Add a colour and forget any link in that chain, and this fails naming
 * the colour and the link.
 */
class EveryMenuColourReachesThePageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    /** A distinct, recognisable hex per colour, so one cannot stand in for another. */
    private const SWATCHES = [
        '#a10101', '#02a102', '#0303a1', '#a1a104', '#05a1a1',
        '#a106a1', '#7a0707', '#087a7a', '#7a7a09', '#0a0a7a',
    ];

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

    /** One colour key -> the swatch it is set to, stable across the test. */
    private function swatches(): array
    {
        $keys = array_keys(MenuPresentation::COLOURS);
        $this->assertLessThanOrEqual(
            count(self::SWATCHES),
            count($keys),
            'more colours than distinct swatches — add more to SWATCHES'
        );

        return array_combine($keys, array_slice(self::SWATCHES, 0, count($keys)));
    }

    private function restaurant(): array
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
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'description' => 'Served all day',
            'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'description' => 'Crisp, with chutney', 'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, 'restaurant'];
    }

    private function store(): array
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
            'menu_id' => $menu->id, 'name' => 'Mugs', 'description' => 'Fired locally',
            'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'description' => 'Holds a lot', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, 'store'];
    }

    private function both(): array
    {
        return [$this->restaurant(), $this->store()];
    }

    /** Save every colour at once through the real settings endpoint. */
    private function saveColours(Link $link, string $kind, array $swatches): void
    {
        $resp = $this->actingAs($this->owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson("/user/links/{$link->id}/{$kind}/settings", array_merge([
                'mode'     => 'order',
                'currency' => 'INR',
            ], $swatches));

        $resp->assertOk();
    }

    // ── 1. The editor offers every colour ─────────────────────────

    public function test_the_editor_offers_every_colour_in_the_catalogue(): void
    {
        foreach ($this->both() as [$link, , $kind]) {
            $html = $this->actingAs($this->owner)
                ->get("/user/links/{$link->id}/{$kind}?pane=design")
                ->assertOk()->getContent();

            foreach (MenuPresentation::COLOURS as $key => $meta) {
                $this->assertStringContainsString(
                    e($meta['label']),
                    $html,
                    "$kind: no picker labelled for '$key'"
                );
            }
        }
    }

    // ── 2. The validator accepts it and the save stores it ────────

    public function test_every_colour_survives_the_save(): void
    {
        $swatches = $this->swatches();

        foreach ($this->both() as [$link, $menu, $kind]) {
            $this->saveColours($link, $kind, $swatches);

            $settings = $menu->fresh()->settings ?? [];
            foreach ($swatches as $key => $hex) {
                $this->assertArrayHasKey(
                    $key,
                    $settings,
                    "$kind: '$key' was posted and the save answered success, but nothing was stored — "
                    .'check it is in the validation rules, which used to be typed by hand'
                );
                $this->assertSame($hex, $settings[$key], "$kind: '$key' stored a different colour");
            }
        }
    }

    // ── 3. The page emits it, and something uses it ───────────────

    public function test_every_saved_colour_reaches_the_page_and_is_used_by_a_rule(): void
    {
        $swatches = $this->swatches();

        foreach ($this->both() as [$link, , $kind]) {
            $this->saveColours($link, $kind, $swatches);

            $html = $this->get('/'.$link->alias)->assertOk()->getContent();
            $css  = implode("\n", $this->styleBlocks($html));

            foreach ($swatches as $key => $hex) {
                // Emitted...
                $this->assertStringContainsString(
                    $hex,
                    $css,
                    "$kind: '$key' is saved but never reaches the page's CSS"
                );

                // ...into a variable...
                $this->assertMatchesRegularExpression(
                    '/(--[a-z-]+)\s*:\s*'.preg_quote($hex, '/').'\s*;/i',
                    $css,
                    "$kind: '$key' reaches the page but not as a variable anything could read"
                );
                preg_match('/(--[a-z-]+)\s*:\s*'.preg_quote($hex, '/').'\s*;/i', $css, $m);
                $var = $m[1];

                // ...that some rule actually reads. This is the step that
                // "default always grey" failed: a setting that is stored,
                // emitted, and painted on nothing.
                $this->assertMatchesRegularExpression(
                    '/var\(\s*'.preg_quote($var, '/').'\b/',
                    $css,
                    "$kind: '$key' is emitted as $var and no CSS rule uses it — it is a setting that paints nothing"
                );
            }
        }
    }

    // ── 4. The two menu types cannot drift apart ──────────────────

    public function test_both_menu_types_emit_the_same_variables(): void
    {
        $swatches = $this->swatches();
        $seen = [];

        foreach ($this->both() as [$link, , $kind]) {
            $this->saveColours($link, $kind, $swatches);
            $css = implode("\n", $this->styleBlocks($this->get('/'.$link->alias)->assertOk()->getContent()));

            preg_match_all('/(--ink-[a-z-]+|--step-edge|--rule)\s*:/i', $css, $m);
            $vars = array_values(array_unique($m[1]));
            sort($vars);
            $seen[$kind] = $vars;
        }

        // "also make sure everything synchronized witrh store pages also",
        // as a test rather than as a thing to remember.
        $this->assertSame(
            $seen['restaurant'],
            $seen['store'],
            'the two menu types no longer emit the same ink variables'
        );
    }

    // ── 5. The two Sana actually asked for ────────────────────────

    public function test_the_category_description_and_the_stepper_are_settable(): void
    {
        $this->assertArrayHasKey('cat_desc_color', MenuPresentation::COLOURS);
        $this->assertArrayHasKey('stepper_color', MenuPresentation::COLOURS);

        foreach ($this->both() as [$link, , $kind]) {
            $this->saveColours($link, $kind, [
                'cat_desc_color' => '#123456',
                'stepper_color'  => '#654321',
            ]);

            $css = implode("\n", $this->styleBlocks($this->get('/'.$link->alias)->assertOk()->getContent()));

            // The category description had no colour of its own at all.
            $this->assertMatchesRegularExpression('/\.cat \.cdesc[^}]*color:\s*var\(--ink-cdesc\)/', $css, "$kind: category description");
            // The − 1 + control took its glyph from the page and its border
            // from a hard-coded rgba.
            $this->assertMatchesRegularExpression('/\.qbtn[^}]*color:\s*var\(--ink-step\)/', $css, "$kind: stepper glyph");
            $this->assertMatchesRegularExpression('/\.qbtn[^}]*border:[^;]*var\(--step-edge\)/', $css, "$kind: stepper border");
            // Scoped to the stepper's own rule: the same literal is still
            // on .field and .ful-opt, which are different controls and not
            // what Sana asked about.
            preg_match('/\.qbtn\s*\{[^}]*\}/', $css, $qm);
            $this->assertNotEmpty($qm, "$kind: no .qbtn rule at all");
            $this->assertStringNotContainsString(
                'rgba(0,0,0,.18)',
                $qm[0],
                "$kind: the stepper went back to a hard-coded border"
            );
        }
    }

    public function test_an_unset_stepper_follows_the_item_name_rather_than_the_page(): void
    {
        // Nobody ships a menu whose dish names cannot be read, so the item
        // colour is the one safe thing to inherit. Inheriting the page is
        // what produced "shown in light color".
        foreach ($this->both() as [$link, , $kind]) {
            $css = implode("\n", $this->styleBlocks($this->get('/'.$link->alias)->assertOk()->getContent()));
            $this->assertStringContainsString('--ink-step:  var(--ink-item, inherit);', $css, "$kind: stepper default");
        }
    }

    /** Every <style> block on the page. */
    private function styleBlocks(string $html): array
    {
        preg_match_all('#<style[^>]*>(.*?)</style>#si', $html, $m);

        return $m[1];
    }
}
