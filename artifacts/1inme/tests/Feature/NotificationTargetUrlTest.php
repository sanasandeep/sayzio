<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards every consumer of UserNotification::resolveTargetUrl() (task #1708).
 *
 * The resolver is the single source of truth for "the thing a notification
 * is about". The push payload already has coverage (PushDeepLinkTest); this
 * locks down the three remaining surfaces so a change to the resolver can't
 * silently break where any of them point:
 *
 *   - the web open-redirect route (user.notifications.open)
 *   - the REST notifications feed (GET /api/v1/notifications)
 *   - the in-app notification row markup
 */
class NotificationTargetUrlTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Dev',
            'role' => 'user',
        ]);
    }

    private function makeNotification(User $user, string $type, array $data): UserNotification
    {
        return UserNotification::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'data'       => $data,
            'created_at' => now(),
        ]);
    }

    public function test_web_open_route_redirects_to_url_bearing_target_and_marks_read(): void
    {
        $user = $this->makeUser();
        // Relative path so it survives the open() safe-redirect host check.
        $target = '/user/links/9/restaurant/orders';
        $n = $this->makeNotification($user, 'restaurant.new_order', ['url' => $target]);

        $resp = $this->actingAs($user)->get(route('user.notifications.open', $n->id));

        $resp->assertRedirect($target);
        $this->assertSame($target, $n->fresh()->targetUrl());
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_web_open_route_redirects_to_type_derived_target(): void
    {
        $user = $this->makeUser();
        // workspace_access_request stores no URL — resolveTargetUrl() derives
        // the team page from the type, and the open route must honour it.
        $n = $this->makeNotification($user, 'workspace_access_request', []);

        $resp = $this->actingAs($user)->get(route('user.notifications.open', $n->id));

        $resp->assertRedirect(route('user.team.index'));
        $this->assertSame(route('user.team.index'), $n->fresh()->targetUrl());
    }

    public function test_web_open_route_falls_back_to_feed_when_no_target(): void
    {
        $user = $this->makeUser();
        // new_follower stores no URL and derives none — nothing to open, so
        // the route falls back to the notifications feed.
        $n = $this->makeNotification($user, 'new_follower', ['follower_id' => 42]);

        $resp = $this->actingAs($user)->get(route('user.notifications.open', $n->id));

        $resp->assertRedirect(route('user.notifications.index'));
        $this->assertNull($n->fresh()->targetUrl());
    }

    public function test_rest_feed_returns_resolver_target_url_per_notification(): void
    {
        $user = $this->makeUser();

        $urlBearing  = $this->makeNotification($user, 'restaurant.new_order', ['url' => '/user/links/9/restaurant/orders']);
        $typeDerived = $this->makeNotification($user, 'workspace_access_request', []);
        $noTarget    = $this->makeNotification($user, 'new_follower', ['follower_id' => 42]);

        // A real bearer token — Sanctum::actingAs() bypasses the token
        // middleware the API relies on, so we mint a genuine token.
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $resp = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/notifications');

        $resp->assertOk();
        $items = collect($resp->json('data.items'))->keyBy('id');

        $this->assertSame(
            UserNotification::resolveTargetUrl($urlBearing->data, $urlBearing->type),
            $items[$urlBearing->id]['url'],
        );
        $this->assertSame(
            UserNotification::resolveTargetUrl($typeDerived->data, $typeDerived->type),
            $items[$typeDerived->id]['url'],
        );
        $this->assertSame(
            UserNotification::resolveTargetUrl($noTarget->data, $noTarget->type),
            $items[$noTarget->id]['url'],
        );
        // Sanity: the type-derived row carries a non-null target and the
        // no-target row carries null — proving the resolver is in play.
        $this->assertNotNull($items[$typeDerived->id]['url']);
        $this->assertNull($items[$noTarget->id]['url']);
    }

    public function test_dismissed_feed_returns_resolver_target_url_per_notification(): void
    {
        $user = $this->makeUser();

        // A type-derived notification stores no URL — only the resolver knows
        // its target. Dismiss it so it surfaces in the dismissed feed.
        $typeDerived = $this->makeNotification($user, 'workspace_access_request', []);
        $typeDerived->delete();

        $token = $user->createToken('test', ['*'])->plainTextToken;

        $resp = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ])->getJson('/api/v1/notifications/dismissed');

        $resp->assertOk();
        $items = collect($resp->json('data.items'))->keyBy('id');

        // The dismissed payload must point to the same resolved target the
        // active feed would — not the raw (and here null) stored url field.
        $this->assertSame(
            UserNotification::resolveTargetUrl($typeDerived->data, $typeDerived->type),
            $items[$typeDerived->id]['url'],
        );
        $this->assertNotNull($items[$typeDerived->id]['url']);
    }

    public function test_in_app_row_links_to_resolved_target(): void
    {
        $user = $this->makeUser();
        $n = $this->makeNotification($user, 'workspace_access_request', []);

        $resp = $this->actingAs($user)->get(route('user.notifications.index'));

        $resp->assertOk();
        // The whole-row stretched link points at the open route, which itself
        // redirects through resolveTargetUrl().
        $resp->assertSee(route('user.notifications.open', $n->id), false);
    }
}
