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
use App\Modules\User\Support\MenuConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28: "if whatsapp no. give it should open whatsapp app or
 * web or anything that send [it] also with confirm order", and "i need
 * option to link any page url foir order confirmation page... also if no
 * page, then option should be there for message like thank you... make it
 * flexible".
 *
 * ---- The WhatsApp handoff ----------------------------------------------
 *
 * The link has been built server-side since the feature shipped, and both
 * pages already painted it onto a button at the bottom of the confirmation
 * sheet. A guest who does not notice that button never sends the order --
 * which, on a menu whose whole point is that the kitchen gets a WhatsApp
 * message, means the feature did not happen.
 *
 * So the chat opens by itself. The catch is the popup blocker: the URL is
 * not known until the server answers, and a `window.open` that far from the
 * tap is swallowed without a word. The tab is therefore reserved ON the
 * tap and pointed at the chat when the order comes back -- and closed again
 * if there turns out to be nothing to send.
 *
 * ---- The confirmation ---------------------------------------------------
 *
 * Three modes rather than one nullable URL field, because "collect in 20
 * minutes, pay at the counter" should not require the owner to go and build
 * a web page. A mode whose payload is missing resolves back to the bill, so
 * a half-filled setting can never leave a guest on a blank sheet.
 */
class WhatTheGuestSeesWhenTheOrderLandsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => $settings,
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

    private function store(array $settings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => $settings,
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

    /** @return array<string, string> Both public pages, rendered. */
    private function bothPages(array $settings = []): array
    {
        return [
            'restaurant' => $this->get('/'.$this->restaurant($settings)->alias)->assertOk()->getContent(),
            'store' => $this->get('/'.$this->store($settings)->alias)->assertOk()->getContent(),
        ];
    }

    private function shared(): string
    {
        return file_get_contents(
            resource_path('views/common/partials/menu-confirmation.blade.php')
        );
    }

    // ===== 1. The resolver ===============================================

    public function test_a_mode_with_nothing_to_show_falls_back_to_the_bill(): void
    {
        // Picked "send them to my page" and never typed the address.
        $this->assertSame(MenuConfirmation::BILL, MenuConfirmation::resolve([
            'confirmation' => ['mode' => 'url', 'url' => ''],
        ])['mode']);

        // Picked "show a message" and never wrote one.
        $this->assertSame(MenuConfirmation::BILL, MenuConfirmation::resolve([
            'confirmation' => ['mode' => 'message', 'message' => '   '],
        ])['mode']);

        // Nothing configured at all.
        $this->assertSame(MenuConfirmation::BILL, MenuConfirmation::resolve([])['mode']);

        // And a mode nobody has heard of.
        $this->assertSame(MenuConfirmation::BILL, MenuConfirmation::resolve([
            'confirmation' => ['mode' => 'teleport'],
        ])['mode']);
    }

    public function test_a_filled_in_mode_is_carried_out(): void
    {
        $url = MenuConfirmation::resolve([
            'confirmation' => ['mode' => 'url', 'url' => 'https://priyumm.example/thanks'],
        ]);
        $this->assertSame(MenuConfirmation::URL, $url['mode']);
        $this->assertSame('https://priyumm.example/thanks', $url['url']);

        $msg = MenuConfirmation::resolve([
            'confirmation' => ['mode' => 'message', 'message' => "Ready in 20 mins.\nPay at the counter."],
        ]);
        $this->assertSame(MenuConfirmation::MESSAGE, $msg['mode']);
        $this->assertStringContainsString('Pay at the counter', $msg['message']);
    }

    public function test_only_http_addresses_survive_the_settings_field(): void
    {
        // This value ends up in a navigation on a public page.
        foreach ([
            'javascript:alert(1)',
            'JavaScript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
            '/thanks',
            'yoursite.com/thanks',
            '',
        ] as $bad) {
            $this->assertNull(MenuConfirmation::url($bad), "[{$bad}] should not be usable as a confirmation page.");
        }

        $this->assertSame('https://a.example/x', MenuConfirmation::url('  https://a.example/x  '));
        $this->assertSame('http://a.example/x', MenuConfirmation::url('http://a.example/x'));
    }

    public function test_the_editor_keeps_a_half_filled_choice_the_resolver_would_drop(): void
    {
        // Someone picks "send them to my page", saves, and comes back to
        // type the address. The choice has to still be selected.
        $saved = MenuConfirmation::sanitize(['mode' => 'url', 'url' => '']);

        $this->assertSame(MenuConfirmation::URL, $saved['mode']);
        $this->assertNull($saved['url']);
    }

    // ===== 2. It saves, on both menus ====================================

    public function test_both_editors_can_save_a_confirmation(): void
    {
        $cases = [
            'restaurant' => ['link' => $this->restaurant(), 'path' => 'restaurant'],
            'store' => ['link' => $this->store(), 'path' => 'store'],
        ];

        foreach ($cases as $kind => $case) {
            $this->actingAs($this->user)
                ->postJson('/user/links/'.$case['link']->id.'/'.$case['path'].'/settings', [
                    'mode' => 'order',
                    'currency' => 'INR',
                    'confirm_mode' => 'message',
                    'confirm_message' => 'Thanks! Collect in 20 minutes.',
                    'confirm_headline' => 'All set',
                ])->assertOk();

            $settings = $case['link']->fresh()->{$kind === 'store' ? 'storeMenu' : 'restaurantMenu'}?->settings
                ?? $this->menuSettings($case['link']);

            $this->assertSame('message', $settings['confirmation']['mode'] ?? null,
                "The {$kind} editor did not save the mode.");
            $this->assertSame('Thanks! Collect in 20 minutes.', $settings['confirmation']['message'] ?? null);
            $this->assertSame('All set', $settings['confirmation']['headline'] ?? null);
        }
    }

    public function test_a_junk_address_is_not_stored_as_one(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'order',
                'currency' => 'INR',
                'confirm_mode' => 'url',
                'confirm_url' => 'javascript:alert(1)',
            ])->assertOk();

        $settings = $this->menuSettings($link);

        $this->assertSame('url', $settings['confirmation']['mode'] ?? null);
        $this->assertNull($settings['confirmation']['url'] ?? null,
            'A scheme we did not intend must not be stored as the confirmation page.');
    }

    private function menuSettings(Link $link): array
    {
        $menu = RestaurantMenu::where('link_id', $link->id)->first()
            ?: StoreMenu::where('link_id', $link->id)->first();

        return (array) ($menu?->settings ?? []);
    }

    // ===== 3. The pages act on it ========================================

    public function test_both_pages_carry_the_resolved_setting_rather_than_the_raw_one(): void
    {
        $settings = ['confirmation' => ['mode' => 'url', 'url' => 'not a url']];

        foreach ($this->bothPages($settings) as $kind => $html) {
            $this->assertStringContainsString('const CONFIRM =', $html,
                "The {$kind} page should know what to do on confirmation.");
            // Resolved: the unusable URL mode came back as the bill, so the
            // page has no half-configured state to guard against.
            $this->assertMatchesRegularExpression('/const CONFIRM = \{[^}]*"mode":"bill"/', $html,
                "The {$kind} page should have been handed the resolved mode.");
        }
    }

    public function test_a_page_with_a_message_hides_the_bill_behind_it(): void
    {
        $shared = $this->shared();

        // The bill elements are handed in and switched off together, rather
        // than each page listing its own by hand.
        $this->assertStringContainsString("cfg.mode === 'message'", $shared);
        $this->assertStringContainsString("el.style.display = 'none'", $shared);

        foreach ($this->bothPages() as $kind => $html) {
            foreach (['doneHead', 'doneMsg', 'doneStatusRow', 'doneTotalRow', 'doneNote'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html,
                    "The {$kind} page's confirmation is missing #{$id}, so it cannot be reshaped.");
            }
        }
    }

    public function test_a_redirect_stops_the_page_doing_anything_else(): void
    {
        $shared = $this->shared();

        $this->assertStringContainsString("window.location.href = cfg.url", $shared);
        $this->assertStringContainsString("return 'redirected'", $shared);

        // Both pages honour that answer instead of carrying on into a sheet
        // nobody will see, and stop polling a status that is off screen.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString("=== 'redirected') { return; }", $src,
                "common/{$view} keeps going after the guest has been sent away.");
            $this->assertStringContainsString("if (CONFIRM.mode === 'message') { return; }", $src,
                "common/{$view} polls a status it is not showing.");
        }
    }

    public function test_the_redirect_carries_nothing_with_it(): void
    {
        $shared = $this->shared();

        // The public token is what polls the order's status. Appending it to
        // someone else's URL hands it to that server's logs and to every
        // referrer the guest's browser sends onward.
        $this->assertStringNotContainsString('public_token', $shared);
        $this->assertStringNotContainsString('cfg.url +', $shared);
        $this->assertStringNotContainsString('?ref=', $shared);
    }

    // ===== 4. The WhatsApp handoff =======================================

    public function test_the_chat_tab_is_reserved_on_the_tap_not_after_the_answer(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $reserve = strpos($src, 'menuWhatsappReserve(WA_ON)');
            $post = strpos($src, 'await menuPost(ORDER_URL');
            $handoff = strpos($src, 'menuWhatsappHandoff(waWin, order.whatsapp)');

            $this->assertNotFalse($reserve, "common/{$view} never reserves the chat tab.");
            $this->assertNotFalse($post);
            $this->assertNotFalse($handoff);
            // The whole point: reserved BEFORE the request, pointed at the
            // chat AFTER it. Reserving after the await is a popup blocker
            // swallowing it without a word.
            $this->assertLessThan($post, $reserve,
                "common/{$view} opens the chat tab after the await, where a popup blocker eats it.");
            $this->assertGreaterThan($post, $handoff);
        }
    }

    public function test_a_reserved_tab_is_given_back_when_there_is_nothing_to_send(): void
    {
        $shared = $this->shared();

        $this->assertStringContainsString('win.close()', $shared,
            'A failed order should not leave a blank tab sitting open.');

        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('menuWhatsappHandoff(waWin, null)', $src,
                "common/{$view} leaves a blank tab open when the order fails.");
        }
    }

    public function test_a_menu_with_no_number_never_opens_a_tab(): void
    {
        foreach ($this->bothPages() as $kind => $html) {
            $this->assertMatchesRegularExpression('/const WA_ON = false/', $html,
                "The {$kind} page with no WhatsApp number should not reserve a tab.");
        }

        foreach ($this->bothPages(['whatsapp_number' => '919876543210']) as $kind => $html) {
            $this->assertMatchesRegularExpression('/const WA_ON = true/', $html,
                "The {$kind} page with a number should reserve one.");
        }
    }

    public function test_the_button_is_still_there_when_the_tab_is_blocked(): void
    {
        // The handoff can fail -- a blocker, a closed tab -- and when it
        // does the guest must still have a way to send the order.
        foreach ($this->bothPages() as $kind => $html) {
            $this->assertStringContainsString('id="waBtn"', $html,
                "The {$kind} page lost its WhatsApp fallback.");
        }

        $this->assertStringContainsString('return false;', $this->shared());
    }

    // ===== 5. One copy ===================================================

    public function test_neither_page_nor_editor_keeps_its_own_copy(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('common.partials.menu-confirmation', $src,
                "common/{$view} should draw the handoff from the shared partial.");
            $this->assertStringNotContainsString('window.open(\'\', \'_blank\')', $src,
                "common/{$view} should not carry its own copy of the reserve.");
        }

        foreach (['restaurant', 'store'] as $editor) {
            $src = file_get_contents(resource_path('views/user/links/'.$editor.'/editor.blade.php'));
            $this->assertStringContainsString('menu-confirmation-panel', $src,
                "The {$editor} editor should draw the panel from the shared partial.");
            $this->assertStringContainsString('confirm_mode:this.confirm.mode', $src,
                "The {$editor} editor should send the chosen mode when it saves.");
        }
    }
}
