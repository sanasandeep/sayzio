<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Controllers\ReviewsController;
use App\Modules\User\Controllers\UpdatesController;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the mobile/API link-create path (Api\LinkController::store) for the
 * page types added alongside the Zio Browser "+ Create" popover: restaurant
 * menu, store menu, service booking, calendar, reviews and updates. Each
 * create must plan-gate on its module toggle and seed the same creation
 * defaults the web create flow does (companion builder rows / Calendar row /
 * settings defaults) so the public page renders immediately.
 *
 * NOTE: auth uses a real Bearer token, NOT Sanctum::actingAs — the latter
 * skips the TouchSessionToken middleware the API path relies on.
 */
class ApiNewPageTypeCreateTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features, int $monthlyPrice = 0): Plan
    {
        return Plan::create([
            'name'          => 'Plan ' . Str::random(4),
            'slug'          => 'plan-' . Str::lower(Str::random(6)),
            'monthly_price' => $monthlyPrice,
            'annual_price'  => $monthlyPrice * 10,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => $features,
        ]);
    }

    private function userOn(Plan $plan): User
    {
        $user = User::create([
            'name'     => 'Api ' . Str::random(4),
            'email'    => 'api-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        return $user->fresh();
    }

    private function authOnPlanWith(array $features): User
    {
        $user = $this->userOn($this->plan($features));
        $this->withToken($user->createToken('test')->plainTextToken);
        return $user;
    }

    public function test_restaurant_menu_create_seeds_companion_menu(): void
    {
        $user = $this->authOnPlanWith(['module_restaurant_menu' => true]);

        $resp = $this->postJson('/api/v1/links', ['type' => 'restaurant_menu', 'title' => 'My Diner']);

        $resp->assertStatus(201);
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'restaurant_menu')->firstOrFail();
        $menu = RestaurantMenu::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(RestaurantMenu::MODE_DISPLAY, $menu->mode);
        $this->assertSame('USD', $menu->currency);
    }

    public function test_store_menu_create_seeds_companion_store(): void
    {
        $user = $this->authOnPlanWith(['module_store_menu' => true]);

        $resp = $this->postJson('/api/v1/links', ['type' => 'store_menu', 'title' => 'My Shop']);

        $resp->assertStatus(201);
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'store_menu')->firstOrFail();
        $store = StoreMenu::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(StoreMenu::MODE_DISPLAY, $store->mode);
        $this->assertSame('USD', $store->currency);
    }

    public function test_service_booking_create_seeds_booking_defaults(): void
    {
        $user = $this->authOnPlanWith(['module_service_booking' => true]);

        $resp = $this->postJson('/api/v1/links', ['type' => 'service_booking', 'title' => 'Book me']);

        $resp->assertStatus(201);
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'service_booking')->firstOrFail();
        $booking = ServiceBooking::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(ServiceBooking::MODE_BOOKING, $booking->mode);
        $this->assertSame(30, (int) $booking->slot_length_minutes);
        $this->assertSame(120, (int) $booking->lead_time_minutes);
        $this->assertSame(30, (int) $booking->max_days_ahead);
    }

    public function test_calendar_create_seeds_public_calendar_row(): void
    {
        $user = $this->authOnPlanWith(['module_calendar' => true]);

        $resp = $this->postJson('/api/v1/links', ['type' => 'calendar', 'title' => 'Gigs']);

        $resp->assertStatus(201);
        $link = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'calendar')->firstOrFail();
        $calendar = Calendar::where('link_id', $link->id)->firstOrFail();
        $this->assertSame('Gigs', $calendar->title);
        $this->assertSame($link->alias, $calendar->slug);
        $this->assertSame('#3d6bff', $calendar->accent_color);
        $this->assertTrue((bool) $calendar->is_public);
        $this->assertSame($calendar->id, $link->calendar_id);
        $this->assertSame('public', $link->visibility);
    }

    public function test_reviews_and_updates_creates_seed_default_settings(): void
    {
        $user = $this->authOnPlanWith(['module_reviews' => true, 'module_updates' => true]);

        $this->postJson('/api/v1/links', ['type' => 'reviews', 'title' => 'Wall of love'])->assertStatus(201);
        $this->postJson('/api/v1/links', ['type' => 'updates', 'title' => 'Changelog'])->assertStatus(201);

        $reviews = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'reviews')->firstOrFail();
        $updates = Link::withoutGlobalScopes()->where('user_id', $user->id)->where('type', 'updates')->firstOrFail();

        foreach (array_keys(ReviewsController::DEFAULT_SETTINGS) as $key) {
            $this->assertArrayHasKey($key, (array) ($reviews->settings['reviews'] ?? []));
        }
        foreach (array_keys(UpdatesController::DEFAULT_SETTINGS) as $key) {
            $this->assertArrayHasKey($key, (array) ($updates->settings['updates'] ?? []));
        }
    }

    public function test_module_toggle_off_plan_gates_new_types_with_402(): void
    {
        $this->authOnPlanWith(['module_restaurant_menu' => false]);
        $this->plan(['module_restaurant_menu' => true], 1500);

        $resp = $this->postJson('/api/v1/links', ['type' => 'restaurant_menu', 'title' => 'Nope']);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $resp->assertJsonPath('error.details.feature', 'module_restaurant_menu');
    }

    public function test_numeric_cap_exceeded_plan_gates_with_402(): void
    {
        $user = $this->authOnPlanWith(['module_calendar' => true, 'max_calendars' => 1]);

        $this->postJson('/api/v1/links', ['type' => 'calendar', 'title' => 'First'])->assertStatus(201);
        $resp = $this->postJson('/api/v1/links', ['type' => 'calendar', 'title' => 'Second']);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $resp->assertJsonPath('error.details.feature', 'max_calendars');
    }
}
