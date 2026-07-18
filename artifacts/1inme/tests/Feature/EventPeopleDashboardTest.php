<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5010 — Organizer "People & connections" dashboard.
 *
 * Covers:
 *  - web dashboard page renders for the owner and 403s for others
 *  - web stats JSON aggregates opt-ins + exchanges correctly
 *  - API /links/{link}/exchange-stats returns the same aggregates and
 *    enforces the organizer boundary
 */
class EventPeopleDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $u = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function makeIcsLink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => 'ics',
            'alias'   => 'evt' . Str::random(8),
            'title'   => 'Networking Night',
        ]);
    }

    private function seedActivity(Link $link): array
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        // Two active opt-ins + one expired.
        EventDiscoverability::create(['user_id' => $a->id, 'link_id' => $link->id, 'expires_at' => now()->addHour()]);
        EventDiscoverability::create(['user_id' => $b->id, 'link_id' => $link->id, 'expires_at' => now()->addHour()]);
        EventDiscoverability::create(['user_id' => $c->id, 'link_id' => $link->id, 'expires_at' => now()->subHour()]);

        // One accepted, one pending exchange.
        EventContactExchange::create([
            'requester_id' => $a->id, 'recipient_id' => $b->id, 'link_id' => $link->id,
            'status' => EventContactExchange::STATUS_ACCEPTED, 'accepted_at' => now(),
        ]);
        EventContactExchange::create([
            'requester_id' => $b->id, 'recipient_id' => $c->id, 'link_id' => $link->id,
            'status' => EventContactExchange::STATUS_PENDING,
        ]);

        return [$a, $b, $c];
    }

    public function test_owner_sees_dashboard_page(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeIcsLink($owner);

        $this->actingAs($owner)
            ->get(route('user.links.ics.people', $link))
            ->assertOk()
            ->assertSee('People &amp; connections', false);
    }

    public function test_non_owner_gets_403_on_dashboard(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeIcsLink($owner);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('user.links.ics.people', $link))
            ->assertForbidden();
    }

    public function test_web_stats_json_aggregates(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeIcsLink($owner);
        $this->seedActivity($link);

        $res = $this->actingAs($owner)
            ->getJson(route('user.links.ics.people.stats', $link))
            ->assertOk()
            ->json();

        $this->assertSame(2, $res['opted_in_active']);
        $this->assertSame(3, $res['opted_in_total']);
        $this->assertSame(2, $res['requests_total']);
        $this->assertSame(1, $res['accepted_total']);
        $this->assertSame(1, $res['pending_total']);
        $this->assertSame(0, $res['declined_total']);
        $this->assertSame(50, $res['acceptance_rate']);
        $this->assertCount(1, $res['recent_accepted']);
    }

    public function test_api_owner_stats_endpoint(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeIcsLink($owner);
        $this->seedActivity($link);

        $token = $owner->createToken('t')->plainTextToken;

        $res = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/links/' . $link->id . '/exchange-stats')
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $res['opted_in_active']);
        $this->assertSame(2, $res['requests_total']);
        $this->assertSame(1, $res['accepted_total']);
        $this->assertSame(50, $res['acceptance_rate']);
        $this->assertSame($link->id, $res['event']['id']);
    }

    public function test_api_stats_forbidden_for_non_organizer(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeIcsLink($owner);

        $stranger = User::factory()->create();
        $token = $stranger->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/links/' . $link->id . '/exchange-stats')
            ->assertNotFound();
    }
}
