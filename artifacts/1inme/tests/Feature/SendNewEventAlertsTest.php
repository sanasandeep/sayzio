<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventNewAlertSent;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the `events:send-new-alerts` scheduled command.
 *
 * The key regression path is: when a recipient has a nearby upcoming event,
 * the command must eager-load the event host via `user:id,name,handle,...`.
 * The `users` table has no `username` column; using it would throw
 * SQLSTATE[42703] and exit non-zero. All tests below reach the eager-load
 * query so a bad column name would cause them to fail immediately.
 */
class SendNewEventAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name'     => 'User ' . Str::random(4),
            'email'    => Str::random(8) . '@test.example',
            'handle'   => Str::random(12),
            'password' => bcrypt('secret'),
            'status'   => 'active',
        ], $overrides));
    }

    /**
     * Create a public, active `ics` link with icsData placed at the given
     * lat/lng and starting tomorrow — eligible for immediate dispatch.
     */
    private function makeEventLink(User $host, float $lat, float $lng): Link
    {
        $link = Link::create([
            'user_id'    => $host->id,
            'type'       => 'ics',
            'alias'      => 'ev-' . Str::random(8),
            'title'      => 'Test Event',
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Test Event',
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date'   => now()->addDay()->addHours(2)->toDateTimeString(),
            'latitude'   => $lat,
            'longitude'  => $lng,
        ]);

        return $link;
    }

    /**
     * Core regression: a recipient with a nearby upcoming event triggers the
     * host eager-load (user:id,name,handle,...). If "username" were still
     * present in the column list Postgres would throw 42703 and the command
     * would exit non-zero.
     */
    public function test_command_exits_zero_and_records_sent_marker_for_nearby_event(): void
    {
        Mail::fake();

        $host = $this->makeUser();

        // Recipient opted in with a 100 km radius centred on (0°, 0°).
        $recipient = $this->makeUser([
            'event_alerts_enabled'   => true,
            'event_alert_latitude'   => 0.0,
            'event_alert_longitude'  => 0.0,
            'event_alert_radius_km'  => 100,
            'event_alert_frequency'  => 'instant',
            'timezone'               => 'UTC',
        ]);

        // Event at (0.1°, 0.1°) ≈ 15.7 km away — well within the 100 km radius.
        $this->makeEventLink($host, 0.1, 0.1);

        $this->artisan('events:send-new-alerts')->assertExitCode(0);

        // Idempotency marker must be written for the (link, recipient) pair.
        $this->assertDatabaseCount('event_new_alerts_sent', 1);
        $this->assertDatabaseHas('event_new_alerts_sent', ['user_id' => $recipient->id]);
    }

    /**
     * No opted-in recipients → early return, still exits zero.
     */
    public function test_command_exits_zero_with_no_opted_in_recipients(): void
    {
        $this->makeUser(); // exists but not opted in
        $this->artisan('events:send-new-alerts')->assertExitCode(0);
        $this->assertDatabaseCount('event_new_alerts_sent', 0);
    }

    /**
     * Event is outside the recipient's radius → skipped, no sent marker.
     */
    public function test_command_skips_event_outside_recipient_radius(): void
    {
        Mail::fake();

        $host      = $this->makeUser();
        $recipient = $this->makeUser([
            'event_alerts_enabled'   => true,
            'event_alert_latitude'   => 0.0,
            'event_alert_longitude'  => 0.0,
            'event_alert_radius_km'  => 10,   // 10 km
            'event_alert_frequency'  => 'instant',
            'timezone'               => 'UTC',
        ]);

        // (2°, 2°) ≈ 314 km away — well outside the 10 km radius.
        $this->makeEventLink($host, 2.0, 2.0);

        $this->artisan('events:send-new-alerts')->assertExitCode(0);
        $this->assertDatabaseCount('event_new_alerts_sent', 0);
    }

    /**
     * An already-alerted event must not be re-delivered (idempotency guard).
     */
    public function test_command_does_not_resend_already_alerted_event(): void
    {
        Mail::fake();

        $host      = $this->makeUser();
        $recipient = $this->makeUser([
            'event_alerts_enabled'   => true,
            'event_alert_latitude'   => 0.0,
            'event_alert_longitude'  => 0.0,
            'event_alert_radius_km'  => 100,
            'event_alert_frequency'  => 'instant',
            'timezone'               => 'UTC',
        ]);

        $link = $this->makeEventLink($host, 0.1, 0.1);

        EventNewAlertSent::create([
            'link_id' => $link->id,
            'user_id' => $recipient->id,
            'sent_at' => now(),
        ]);

        $this->artisan('events:send-new-alerts')->assertExitCode(0);

        // Count must still be exactly one — not doubled.
        $this->assertDatabaseCount('event_new_alerts_sent', 1);
    }
}
