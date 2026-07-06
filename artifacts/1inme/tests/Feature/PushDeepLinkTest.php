<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\ExpoPushNotifier;
use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\User\Models\ApiUsageCounter;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards push deep-linking + mark-read (task #1706).
 *
 * The push payload assembled by NotificationService::pushToUser() must
 * carry two things so a tapped push behaves exactly like tapping the
 * matching in-app row:
 *
 *   - `url`: the resolved target, via UserNotification::resolveTargetUrl()
 *     (the single source of truth shared with the in-app row, the
 *     open-redirect route and the REST feed). Absent when the type has
 *     nothing meaningful to open.
 *   - `notification_id`: the id of the originating user_notifications row,
 *     so the mobile tap handler can mark that exact row read.
 *
 * We swap the ExpoPushNotifier transport for a recorder so we can assert
 * on the payload without hitting Expo or needing a registered device.
 */
class PushDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    private RecordingExpoPushNotifier $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new RecordingExpoPushNotifier();
        $this->app->instance(ExpoPushNotifier::class, $this->recorder);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Dev',
            'role' => 'user',
        ]);
    }

    public function test_push_payload_carries_url_for_url_bearing_type(): void
    {
        $user = $this->makeUser();

        app(NotificationService::class)->pushToUser(
            $user,
            'restaurant.new_order',
            'New order',
            'Table 4 · 2 item(s)',
            ['url' => 'https://1in.me/user/links/9/restaurant/orders', 'order_id' => 7],
        );

        $this->assertCount(1, $this->recorder->calls);
        $payload = $this->recorder->calls[0]['data'];
        $this->assertSame('https://1in.me/user/links/9/restaurant/orders', $payload['url']);
        $this->assertSame('restaurant.new_order', $payload['type']);
    }

    public function test_push_payload_resolves_type_derived_url_when_absent_in_data(): void
    {
        $user = $this->makeUser();

        // workspace_access_request has no stored URL — resolveTargetUrl()
        // derives it from the type, and pushToUser() must stamp it.
        app(NotificationService::class)->pushToUser(
            $user,
            'workspace_access_request',
            'Access request',
            'Someone wants in',
            [],
        );

        $this->assertCount(1, $this->recorder->calls);
        $payload = $this->recorder->calls[0]['data'];
        $this->assertSame(route('user.team.index'), $payload['url']);
    }

    public function test_push_payload_omits_url_when_no_target(): void
    {
        $user = $this->makeUser();

        // new_follower stores no URL and derives none from its type, so the
        // payload must NOT carry a `url` key (mobile falls back to the list).
        app(NotificationService::class)->pushToUser(
            $user,
            'new_follower',
            'New follower',
            'Someone followed you',
            ['follower_id' => 42],
        );

        $this->assertCount(1, $this->recorder->calls);
        $payload = $this->recorder->calls[0]['data'];
        $this->assertArrayNotHasKey('url', $payload);
        $this->assertSame('new_follower', $payload['type']);
    }

    public function test_restaurant_order_push_carries_url_and_matching_notification_id(): void
    {
        $owner = $this->makeUser();

        $link = Link::create([
            'user_id' => $owner->id,
            'type'    => 'restaurant_menu',
            'alias'   => Link::generateAlias(),
            'title'   => 'Cafe Bistro',
        ]);

        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => RestaurantMenu::MODE_ORDER,
            'currency' => 'USD',
        ]);

        $item = RestaurantMenuItem::create([
            'menu_id'   => $menu->id,
            'name'      => 'Espresso',
            'price'     => 3.50,
            'is_active' => true,
        ]);

        $table = RestaurantTable::create([
            'menu_id' => $menu->id,
            'label'   => '4',
        ]);

        app(RestaurantOrderService::class)->place($link, $menu, [
            'table_code' => $table->code,
            'items'      => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        // The in-app row that was created for the owner.
        $row = UserNotification::where('user_id', $owner->id)
            ->where('type', 'restaurant.new_order')
            ->firstOrFail();

        $this->assertCount(1, $this->recorder->calls);
        $payload = $this->recorder->calls[0]['data'];
        $this->assertSame($row->id, $payload['notification_id']);
        $this->assertSame(route('user.links.restaurant.orders', $link), $payload['url']);
    }

    public function test_api_usage_warning_push_carries_matching_notification_id(): void
    {
        $plan = Plan::create([
            'name'          => 'p' . Str::random(4),
            'slug'          => 'p' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => [
                'api_access'        => true,
                'api_calls_monthly' => 5,
            ],
        ]);

        $user = User::create([
            'name'     => 'Dev',
            'email'    => 'dev-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $plan->id,
        ]);

        $new = $user->createToken('key', ['*']);
        $new->accessToken->forceFill(['client_kind' => 'api_key'])->save();

        $hit = fn () => $this->withHeaders([
            'Authorization' => 'Bearer ' . $new->plainTextToken,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/auth/me');

        // Drive usage to 80% (allowance 5 → warning at the 4th call).
        for ($i = 0; $i < 4; $i++) {
            $hit()->assertOk();
        }

        $row = UserNotification::where('user_id', $user->id)
            ->where('type', 'api.usage_warning')
            ->orderBy('id')
            ->firstOrFail();

        // Exactly one push fired (the 80% warning); its payload must point
        // the tap handler at the row that was created.
        $this->assertCount(1, $this->recorder->calls);
        $payload = $this->recorder->calls[0]['data'];
        $this->assertSame('api.usage_warning', $payload['type']);
        $this->assertSame($row->id, $payload['notification_id']);

        // API-usage warnings have no per-row target URL — the mobile handler
        // falls back to the usage screen, so no `url` should be stamped.
        $this->assertArrayNotHasKey('url', $payload);

        // Sanity: the dedup stamp recorded so we don't re-warn.
        $counter = ApiUsageCounter::where('user_id', $user->id)
            ->where('period', ApiUsageCounter::currentPeriod())
            ->first();
        $this->assertNotNull($counter->warned_80_at);
    }
}

/**
 * Records every sendToUser() call so tests can inspect the assembled
 * payload without contacting Expo or needing a registered device.
 */
class RecordingExpoPushNotifier extends ExpoPushNotifier
{
    /** @var array<int, array{user_id:int, title:string, body:string, data:array<string,mixed>}> */
    public array $calls = [];

    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $this->calls[] = [
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'data'    => $data,
        ];

        return 1;
    }
}
