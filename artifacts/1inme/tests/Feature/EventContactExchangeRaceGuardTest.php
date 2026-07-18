<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #5011 — exchange requests must not be spammable when the rate limit
 * is bypassed by a burst/race. The DB unique index on
 * (requester_id, recipient_id, link_id) is the hard guard; the controller
 * must treat a unique-violation on insert idempotently (return the winning
 * row) instead of 500ing or letting duplicates through.
 */
class EventContactExchangeRaceGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeLiveEvent(User $host): Link
    {
        $link = Link::create([
            'user_id'    => $host->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Live Meetup',
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

    private function attend(User $user, Link $link): void
    {
        Rsvp::create([
            'link_id'  => $link->id,
            'name'     => $user->name,
            'email'    => $user->email,
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);
    }

    private function optIn(User $user, Link $link): void
    {
        EventDiscoverability::create([
            'user_id'    => $user->id,
            'link_id'    => $link->id,
            'expires_at' => now()->addHours(3),
        ]);
    }

    public function test_race_duplicate_insert_is_idempotent_not_a_500(): void
    {
        $host      = $this->makeUser();
        $requester = $this->makeUser();
        $recipient = $this->makeUser();

        $link = $this->makeLiveEvent($host);
        $this->attend($requester, $link);
        $this->attend($recipient, $link);
        $this->optIn($recipient, $link);

        // Simulate a concurrent twin: right before the controller's insert
        // runs (i.e. AFTER its duplicate pre-check has already passed), a
        // racing request commits the same pending row. The controller's own
        // insert then hits the unique index.
        $raceFired = false;
        EventContactExchange::creating(function () use (&$raceFired, $requester, $recipient, $link) {
            if ($raceFired) return;
            $raceFired = true;
            DB::table('event_contact_exchanges')->insert([
                'requester_id' => $requester->id,
                'recipient_id' => $recipient->id,
                'link_id'      => $link->id,
                'status'       => EventContactExchange::STATUS_PENDING,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        $token = $requester->createToken('test')->plainTextToken;

        $res = $this->withToken($token)->postJson(
            "/api/v1/events/{$link->alias}/exchange",
            ['recipient_id' => $recipient->id],
        );

        $res->assertSuccessful();
        $this->assertTrue($raceFired, 'race listener never fired');

        // Exactly one row — the winner's — and the response points at it.
        $rows = EventContactExchange::where('link_id', $link->id)
            ->where('requester_id', $requester->id)
            ->where('recipient_id', $recipient->id)
            ->get();
        $this->assertCount(1, $rows);
        $this->assertSame(EventContactExchange::STATUS_PENDING, $rows->first()->status);
        $this->assertSame($rows->first()->id, (int) $res->json('data.exchange_id'));
        $this->assertSame(EventContactExchange::STATUS_PENDING, $res->json('data.status'));
    }

    public function test_sequential_duplicate_request_still_conflicts(): void
    {
        $host      = $this->makeUser();
        $requester = $this->makeUser();
        $recipient = $this->makeUser();

        $link = $this->makeLiveEvent($host);
        $this->attend($requester, $link);
        $this->attend($recipient, $link);
        $this->optIn($recipient, $link);

        $token = $requester->createToken('test')->plainTextToken;

        $first = $this->withToken($token)->postJson(
            "/api/v1/events/{$link->alias}/exchange",
            ['recipient_id' => $recipient->id],
        );
        $first->assertStatus(201);

        $second = $this->withToken($token)->postJson(
            "/api/v1/events/{$link->alias}/exchange",
            ['recipient_id' => $recipient->id],
        );
        $second->assertStatus(409);

        $this->assertSame(1, EventContactExchange::where('link_id', $link->id)->count());
    }
}
