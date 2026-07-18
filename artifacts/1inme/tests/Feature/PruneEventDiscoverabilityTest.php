<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * events:prune-discoverability must delete opt-in rows whose expires_at is
 * comfortably in the past (grace window, default 7 days) while leaving
 * active rows, never-expiring rows, and recently expired rows untouched.
 */
class PruneEventDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeLink(User $user): Link
    {
        return Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => 'Event ' . Str::random(4),
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);
    }

    private function optIn(User $user, Link $link, ?\DateTimeInterface $expiresAt): EventDiscoverability
    {
        return EventDiscoverability::create([
            'user_id'    => $user->id,
            'link_id'    => $link->id,
            'expires_at' => $expiresAt,
            'lat'        => 12.34,
            'lng'        => 56.78,
        ]);
    }

    public function test_prunes_long_expired_rows_and_keeps_the_rest(): void
    {
        $user = User::factory()->create();

        $longExpired     = $this->optIn($user, $this->makeLink($user), now()->subDays(30));
        $justPastCutoff  = $this->optIn($user, $this->makeLink($user), now()->subDays(8));
        $recentlyExpired = $this->optIn($user, $this->makeLink($user), now()->subDays(2));
        $stillActive     = $this->optIn($user, $this->makeLink($user), now()->addDays(3));
        $neverExpires    = $this->optIn($user, $this->makeLink($user), null);

        $this->artisan('events:prune-discoverability')->assertSuccessful();

        $this->assertDatabaseMissing('event_discoverability', ['id' => $longExpired->id]);
        $this->assertDatabaseMissing('event_discoverability', ['id' => $justPastCutoff->id]);
        $this->assertDatabaseHas('event_discoverability', ['id' => $recentlyExpired->id]);
        $this->assertDatabaseHas('event_discoverability', ['id' => $stillActive->id]);
        $this->assertDatabaseHas('event_discoverability', ['id' => $neverExpires->id]);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $user = User::factory()->create();
        $row  = $this->optIn($user, $this->makeLink($user), now()->subDays(30));

        $this->artisan('events:prune-discoverability', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('event_discoverability', ['id' => $row->id]);
    }

    public function test_custom_grace_window_is_honoured(): void
    {
        $user = User::factory()->create();
        $row  = $this->optIn($user, $this->makeLink($user), now()->subDays(2));

        $this->artisan('events:prune-discoverability', ['--days' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('event_discoverability', ['id' => $row->id]);
    }
}
