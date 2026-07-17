<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5052 — "My swaps": attendees can list their own pending/accepted
 * contact-swap requests at an event (API + web JSON), and a pending
 * request can be withdrawn — but only by its sender, and only while it is
 * still pending. Accepted rows must expose accepted_at.
 */
class EventMySwapsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        $u = User::factory()->create(['name' => $name]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function makeLiveEvent(User $host): Link
    {
        $link = Link::create([
            'user_id'    => $host->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Meetup Night',
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => now()->subHour()->toDateTimeString(),
            'end_date'   => now()->addHours(3)->toDateTimeString(),
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);

        return $link;
    }

    private function exchange(Link $link, User $from, User $to, string $status = EventContactExchange::STATUS_PENDING, ?\Illuminate\Support\Carbon $acceptedAt = null): EventContactExchange
    {
        return EventContactExchange::create([
            'link_id'      => $link->id,
            'requester_id' => $from->id,
            'recipient_id' => $to->id,
            'status'       => $status,
            'accepted_at'  => $acceptedAt,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_my_swaps_lists_pending_and_accepted_only_with_accepted_at(): void
    {
        $host  = $this->makeUser('Holly Host');
        $alice = $this->makeUser('Alice');
        $bob   = $this->makeUser('Bob');
        $carol = $this->makeUser('Carol');

        $link = $this->makeLiveEvent($host);

        $pending  = $this->exchange($link, $alice, $bob);
        $accepted = $this->exchange($link, $carol, $alice, EventContactExchange::STATUS_ACCEPTED, now()->subMinutes(5));
        // Declined and other-people's rows must not appear.
        $this->exchange($link, $alice, $carol, EventContactExchange::STATUS_DECLINED);
        $this->exchange($link, $bob, $carol);

        $resp = $this->withToken($this->token($alice))
            ->getJson('/api/v1/events/' . $link->alias . '/my-swaps');
        $this->flushHeaders();

        $resp->assertOk()->assertJsonPath('data.total', 2);
        $items = collect($resp->json('data.items'))->keyBy('exchange_id');

        $this->assertTrue($items->has($pending->id));
        $this->assertTrue($items->has($accepted->id));

        $p = $items[$pending->id];
        $this->assertSame('pending', $p['status']);
        $this->assertTrue($p['sent_by_me']);
        $this->assertTrue($p['can_cancel']);
        $this->assertSame($bob->id, $p['other']['id']);

        $a = $items[$accepted->id];
        $this->assertSame('accepted', $a['status']);
        $this->assertFalse($a['sent_by_me']);
        $this->assertFalse($a['can_cancel']);
        $this->assertNotNull($a['accepted_at']);
        $this->assertSame($carol->id, $a['other']['id']);
    }

    public function test_sender_can_cancel_pending_request_and_row_is_deleted(): void
    {
        $host  = $this->makeUser('Holly Host');
        $alice = $this->makeUser('Alice');
        $bob   = $this->makeUser('Bob');
        $link  = $this->makeLiveEvent($host);

        $pending = $this->exchange($link, $alice, $bob);

        $resp = $this->withToken($this->token($alice))
            ->postJson('/api/v1/me/contact-exchanges/' . $pending->id . '/cancel');
        $this->flushHeaders();

        $resp->assertOk()->assertJsonPath('data.cancelled', true);
        $this->assertNull(EventContactExchange::find($pending->id));
    }

    public function test_recipient_cannot_cancel_and_accepted_cannot_be_cancelled(): void
    {
        $host  = $this->makeUser('Holly Host');
        $alice = $this->makeUser('Alice');
        $bob   = $this->makeUser('Bob');
        $carol = $this->makeUser('Carol');
        $link  = $this->makeLiveEvent($host);

        // Unique (requester, recipient, link) — the accepted row must be a
        // different pair from the pending one.
        $pending  = $this->exchange($link, $alice, $bob);
        $accepted = $this->exchange($link, $alice, $carol, EventContactExchange::STATUS_ACCEPTED, now());

        // Recipient tries to cancel the pending request → 403.
        $this->withToken($this->token($bob))
            ->postJson('/api/v1/me/contact-exchanges/' . $pending->id . '/cancel')
            ->assertStatus(403);
        $this->flushHeaders();

        // Sender tries to cancel an accepted request → 409.
        $this->withToken($this->token($alice))
            ->postJson('/api/v1/me/contact-exchanges/' . $accepted->id . '/cancel')
            ->assertStatus(409);
        $this->flushHeaders();

        // Nonexistent id → 404.
        $this->withToken($this->token($alice))
            ->postJson('/api/v1/me/contact-exchanges/999999/cancel')
            ->assertStatus(404);
        $this->flushHeaders();

        $this->assertNotNull(EventContactExchange::find($pending->id));
        $this->assertNotNull(EventContactExchange::find($accepted->id));
    }

    public function test_web_json_endpoints_mirror_the_api(): void
    {
        $host  = $this->makeUser('Holly Host');
        $alice = $this->makeUser('Alice');
        $bob   = $this->makeUser('Bob');
        $link  = $this->makeLiveEvent($host);

        $pending = $this->exchange($link, $alice, $bob);

        $list = $this->actingAs($alice, 'web')
            ->getJson('/user/event-swaps/' . $link->alias);
        $list->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.exchange_id', $pending->id)
            ->assertJsonPath('items.0.can_cancel', true);

        $cancel = $this->actingAs($alice, 'web')
            ->postJson('/user/contact-exchanges/' . $pending->id . '/cancel');
        $cancel->assertOk()->assertJsonPath('cancelled', true);
        $this->assertNull(EventContactExchange::find($pending->id));
    }
}
