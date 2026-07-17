<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventContactExchange;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * events:prune-contact-exchanges must delete pending/declined exchange rows
 * for events that ended well in the past (default > 30 days), keep accepted
 * rows forever, keep rows for recent/ongoing events, and fall back to row age
 * (default > 90 days) for events with no end date.
 */
class PruneEventContactExchangesTest extends TestCase
{
    use RefreshDatabase;

    private function makeEventLink(User $user, ?\DateTimeInterface $endDate): Link
    {
        $link = Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Event ' . Str::random(4),
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);

        if ($endDate !== null) {
            IcsData::create([
                'link_id'    => $link->id,
                'event_name' => $link->title,
                'start_date' => now()->subDays(200),
                'end_date'   => $endDate,
            ]);
        }

        return $link;
    }

    private function exchange(User $a, User $b, Link $link, string $status, ?\DateTimeInterface $createdAt = null): EventContactExchange
    {
        $row = EventContactExchange::create([
            'requester_id' => $a->id,
            'recipient_id' => $b->id,
            'link_id'      => $link->id,
            'status'       => $status,
            'accepted_at'  => $status === EventContactExchange::STATUS_ACCEPTED ? now() : null,
        ]);

        if ($createdAt) {
            EventContactExchange::where('id', $row->id)->update(['created_at' => $createdAt]);
        }

        return $row;
    }

    public function test_prunes_stale_non_accepted_rows_and_keeps_the_rest(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $oldEvent    = $this->makeEventLink($a, now()->subDays(60));
        $recentEvent = $this->makeEventLink($a, now()->subDays(5));
        $liveEvent   = $this->makeEventLink($a, now()->addDays(1));

        $stalePending  = $this->exchange($a, $b, $oldEvent, EventContactExchange::STATUS_PENDING);
        $staleDeclined = $this->exchange($b, $a, $oldEvent, EventContactExchange::STATUS_DECLINED);
        $oldAccepted   = $this->exchange($a, $b, $this->makeEventLink($a, now()->subDays(60)), EventContactExchange::STATUS_ACCEPTED);

        $recentPending = $this->exchange($a, $b, $recentEvent, EventContactExchange::STATUS_PENDING);
        $livePending   = $this->exchange($a, $b, $liveEvent, EventContactExchange::STATUS_PENDING);

        $this->artisan('events:prune-contact-exchanges')->assertSuccessful();

        $this->assertDatabaseMissing('event_contact_exchanges', ['id' => $stalePending->id]);
        $this->assertDatabaseMissing('event_contact_exchanges', ['id' => $staleDeclined->id]);
        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $oldAccepted->id]);
        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $recentPending->id]);
        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $livePending->id]);
    }

    public function test_no_end_date_events_use_row_age_fallback(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $noEndEvent = $this->makeEventLink($a, null);

        $veryOldPending = $this->exchange($a, $b, $noEndEvent, EventContactExchange::STATUS_PENDING, now()->subDays(120));
        $newishPending  = $this->exchange($b, $a, $noEndEvent, EventContactExchange::STATUS_PENDING, now()->subDays(30));
        $veryOldAccepted = $this->exchange(
            $a,
            $b,
            $this->makeEventLink($a, null),
            EventContactExchange::STATUS_ACCEPTED,
            now()->subDays(120),
        );

        $this->artisan('events:prune-contact-exchanges')->assertSuccessful();

        $this->assertDatabaseMissing('event_contact_exchanges', ['id' => $veryOldPending->id]);
        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $newishPending->id]);
        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $veryOldAccepted->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $row = $this->exchange($a, $b, $this->makeEventLink($a, now()->subDays(60)), EventContactExchange::STATUS_PENDING);

        $this->artisan('events:prune-contact-exchanges', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('event_contact_exchanges', ['id' => $row->id]);
    }

    public function test_custom_days_window_is_honoured(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $row = $this->exchange($a, $b, $this->makeEventLink($a, now()->subDays(3)), EventContactExchange::STATUS_PENDING);

        $this->artisan('events:prune-contact-exchanges', ['--days' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('event_contact_exchanges', ['id' => $row->id]);
    }
}
