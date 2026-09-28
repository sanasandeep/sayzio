<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28, of the menu editor's right-hand column: "right column
 * fix the ui".
 *
 * Eight pickers on that panel, and eight copies of the same option markup --
 * each one restating the same padding, radius, gap and border inline, in four
 * partials and both editors. They had drifted the way copies do.
 *
 * ---- What was actually wrong on screen ---------------------------------
 *
 * 1. The chips had no visible edge. The border was var(--border-glass),
 *    which light mode sets to #e3e0da -- near-invisible on a white card. So
 *    a group of three options read as three loose radio buttons with a pale
 *    blue rectangle floating behind whichever one was selected, rather than
 *    as a set of chips with one of them on. The panel's theme already has a
 *    weight for an edge you are meant to see, --border-strong, and that is
 *    what these use now.
 *
 * 2. The grids were fixed column counts. Three columns in a ~308px card
 *    gives ~98px each, and "Just the number" does not fit in 98px: it wrapped
 *    to two lines, and since grid rows stretch, it made all three chips
 *    twice as tall. "Before the number" / "After the number" did the same.
 *    The grids reflow now, and those two labels lost the words the hint was
 *    already carrying.
 *
 * 3. Accent color was a full-width 42px colour bar sitting two rows above
 *    four colour rows that each drew a 46px swatch beside a name and a hint.
 *    The same control, two shapes, in one card.
 *
 * 4. Shortening "Before the number" to "Before" left two identical-looking
 *    rows under one "Price format" label, which reads as five options for
 *    one question instead of two questions. The second row has its own
 *    heading now -- the short label is what makes it necessary.
 */
class TheMenuSettingsPanelIsOneSetOfControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);

        return $link->fresh();
    }

    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);

        return $link->fresh();
    }

    private function editor(Link $link, string $kind): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/'.$kind)
            ->assertOk()->getContent();
    }

    private function bothEditors(): array
    {
        return [
            'restaurant' => $this->editor($this->restaurant(), 'restaurant'),
            'store' => $this->editor($this->store(), 'store'),
        ];
    }

    private function blades(): array
    {
        return [
            'restaurant editor' => resource_path('views/user/links/restaurant/editor.blade.php'),
            'store editor' => resource_path('views/user/links/store/editor.blade.php'),
            'money picker' => resource_path('views/user/links/partials/menu-money-picker.blade.php'),
            'card design' => resource_path('views/user/links/partials/menu-card-design.blade.php'),
            'divider picker' => resource_path('views/user/links/partials/menu-divider-picker.blade.php'),
            'fulfilment panel' => resource_path('views/user/links/partials/menu-fulfilment-panel.blade.php'),
        ];
    }

    // ===== 1. One chip, not eight =========================================

    public function test_no_picker_writes_the_option_chip_out_for_itself(): void
    {
        foreach ($this->blades() as $name => $path) {
            $src = file_get_contents($path);

            $this->assertStringNotContainsString(
                'padding:8px 10px;border-radius:10px;cursor:pointer',
                $src,
                "The {$name} still carries its own copy of the option chip."
            );
            $this->assertStringNotContainsString(
                'border-color:#7f9cff;background:rgba(127,156,255,.1);',
                $src,
                "The {$name} still paints its own selected state."
            );
            $this->assertStringNotContainsString(
                'grid-template-columns:repeat(2,minmax(0,1fr))',
                $src,
                "The {$name} still pins a fixed column count."
            );
            $this->assertStringNotContainsString(
                'grid-template-columns:repeat(3,minmax(0,1fr))',
                $src,
                "The {$name} still pins a fixed column count."
            );
        }
    }

    public function test_every_picker_uses_the_shared_chip(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            // Price format, position, handover, layout, section titles,
            // prices, dividers — all of them.
            $this->assertGreaterThanOrEqual(
                12,
                substr_count($html, 'class="rm-opt"'),
                "The {$kind} editor should draw every option as the shared chip."
            );
            $this->assertStringContainsString("'on':", $html, "The {$kind} editor lost the selected state.");
        }
    }

    // ===== 2. The chip has an edge you can see ============================

    public function test_a_chip_has_a_visible_border_rather_than_the_faint_one(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertMatchesRegularExpression(
                '/\.rm-opt\s*\{[^}]*border:\s*1px solid var\(--border-strong\)/s',
                $html,
                "The {$kind} editor's chips should use the theme's visible edge weight."
            );
            // The one that made an unselected chip indistinguishable from
            // plain text on a white card.
            $this->assertDoesNotMatchRegularExpression(
                '/\.rm-opt\s*\{[^}]*border:\s*1px solid var\(--border-glass\)/s',
                $html,
                "The {$kind} editor's chips are back on the near-invisible border."
            );
        }
    }

    public function test_the_grids_reflow_instead_of_cutting_a_label_in_half(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertMatchesRegularExpression(
                '/\.rm-opts\s*\{[^}]*auto-fit/s',
                $kind === 'restaurant' ? $html : $html,
                "The {$kind} editor's option grid should reflow to the column it is in."
            );
        }
    }

    // ===== 3. The labels fit the chips they are in ========================

    public function test_the_long_labels_were_shortened_not_left_to_wrap(): void
    {
        // "Just the number" did not fit a ~98px chip. The hint still says it
        // in full, which is where the long form belongs.
        $this->assertSame('Number', MenuMoney::DISPLAYS['none']['label']);
        $this->assertStringContainsString('Just the number', MenuMoney::DISPLAYS['none']['hint']);

        $picker = file_get_contents(resource_path('views/user/links/partials/menu-money-picker.blade.php'));
        $this->assertStringNotContainsString(
            "{{ \$pv['label'] }} the number",
            $picker,
            'The position chips should not re-lengthen the label the picker shortened.'
        );
    }

    public function test_the_second_row_of_chips_says_what_it_decides(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            // Two identical-looking rows under one label read as one group of
            // five. "Before" on its own does not say before what.
            $this->assertStringContainsString(
                'Which side of the number',
                $html,
                "The {$kind} editor should head the position chips separately."
            );
            $this->assertMatchesRegularExpression(
                '/\.rm-sublabel\s*\{/',
                $html,
                "The {$kind} editor should style that heading."
            );
        }
    }

    // ===== 4. One shape for a colour ======================================

    public function test_accent_colour_is_drawn_like_the_colour_rows_under_it(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertMatchesRegularExpression(
                '/\.rm-colour\s*\{/',
                $html,
                "The {$kind} editor should have one colour-row shape."
            );
            // The full-width bar.
            $this->assertStringNotContainsString(
                'x-model="menu.accent_color" @change="saveSettings()" style="height:42px;padding:4px"',
                $html,
                "The {$kind} editor still draws the accent as a full-width bar."
            );
            $this->assertStringContainsString('Buttons, prices and highlights on the page.', $html);
        }
    }

    public function test_the_settings_column_leaves_room_for_the_support_bubble(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertStringContainsString(
                'padding-bottom:72px;',
                $html,
                "The {$kind} editor's settings column should clear the fixed support bubble."
            );
        }
    }

    // ===== 5. Nothing lost in the tidy-up =================================

    public function test_every_option_that_was_offered_is_still_offered(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            foreach (array_keys(MenuMoney::DISPLAYS) as $k) {
                $this->assertStringContainsString(
                    'x-model="menu.price_display" @change="saveSettings()"',
                    $html,
                    "The {$kind} editor lost the price-format picker."
                );
                $this->assertStringContainsString('value="'.$k.'"', $html, "The {$kind} editor lost display option {$k}.");
            }
            foreach (array_keys(MenuMoney::POSITIONS) as $k) {
                $this->assertStringContainsString('value="'.$k.'"', $html, "The {$kind} editor lost position {$k}.");
            }
            // And the controls each picker binds to.
            foreach ([
                'menu.price_display', 'menu.price_position', 'menu.price_decimals',
                'menu.layout', 'menu.heading_style', 'menu.price_style',
                'menu.divider', 'menu.accent_color',
            ] as $binding) {
                $this->assertStringContainsString(
                    $binding,
                    $html,
                    "The {$kind} editor lost the binding {$binding}."
                );
            }
        }
    }
}
