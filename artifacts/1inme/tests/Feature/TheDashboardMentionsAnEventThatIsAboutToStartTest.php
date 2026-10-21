<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Support\EventsHappeningSoon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A creator with a door opening in an hour, and a guest holding a seat for
 * it, have the same problem: the event is the most urgent thing in their
 * account and the dashboard has never mentioned it.
 *
 * The window is deliberately narrow -- running now, or starting inside a
 * day -- so most of these tests are about what stays out. A panel that is
 * always populated stops being read, and the dashboard belongs to the
 * numbers people open it for.
 */
class TheDashboardMentionsAnEventThatIsAboutToStartTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        // The dashboard sits behind onboarding.gate, which redirects a brand
        // new account to onboarding rather than serving the page.
        return User::factory()->create(['onboarded_at' => now()->subMonths(2)])->fresh();
    }

    private function event(User $owner, string $start, ?string $end = null, array $settings = []): Link
    {
        // ics_data.end_date is NOT NULL, so every event has to have one.
        $end = $end ?: \Illuminate\Support\Carbon::parse($start)->addHours(2)->toDateTimeString();

        $link = new Link([
            'user_id'   => $owner->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Founder Sessions',
            'is_active' => true,
        ]);
        $link->workspace_id = $owner->ownedWorkspaces()->first()->id;
        if ($settings) {
            $link->settings = $settings;
        }
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Founder Sessions',
            'start_date' => $start,
            'end_date'   => $end,
            'timezone'   => 'UTC',
        ]);

        return $link->fresh();
    }

    public function test_an_event_starting_in_an_hour_is_surfaced_to_its_host(): void
    {
        $host = $this->user();
        $this->event($host, now()->addHour()->toDateTimeString());

        $rows = EventsHappeningSoon::for($host);

        $this->assertCount(1, $rows);
        $this->assertSame('host', $rows[0]['role']);
        $this->assertFalse($rows[0]['live']);
    }

    public function test_an_event_under_way_is_marked_live(): void
    {
        $host = $this->user();
        $this->event($host, now()->subMinutes(20)->toDateTimeString(), now()->addHour()->toDateTimeString());

        $rows = EventsHappeningSoon::for($host);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]['live']);
    }

    public function test_a_guest_sees_the_event_they_rsvped_to(): void
    {
        $host = $this->user();
        $guest = $this->user();
        $link = $this->event($host, now()->addHours(2)->toDateTimeString());

        Rsvp::create([
            'link_id'  => $link->id,
            'user_id'  => $guest->id,
            'name'     => 'Priya',
            'email'    => 'priya@example.com',
            'response' => 'yes',
            'status'   => 'confirmed',
        ]);

        $rows = EventsHappeningSoon::for($guest);

        $this->assertCount(1, $rows);
        $this->assertSame('guest', $rows[0]['role']);
    }

    public function test_a_host_who_also_rsvped_sees_their_event_once(): void
    {
        $host = $this->user();
        $link = $this->event($host, now()->addHours(2)->toDateTimeString());

        Rsvp::create([
            'link_id' => $link->id, 'user_id' => $host->id, 'name' => 'Me',
            'email' => $host->email, 'response' => 'yes', 'status' => 'confirmed',
        ]);

        $rows = EventsHappeningSoon::for($host);

        $this->assertCount(1, $rows);
        $this->assertSame('host', $rows[0]['role'], 'Hosting should win over attending.');
    }

    public function test_next_week_is_a_calendar_question_not_a_dashboard_one(): void
    {
        $host = $this->user();
        $this->event($host, now()->addDays(6)->toDateTimeString());

        $this->assertCount(0, EventsHappeningSoon::for($host));
    }

    public function test_an_event_that_already_finished_is_gone(): void
    {
        $host = $this->user();
        $this->event($host, now()->subDays(2)->toDateTimeString(), now()->subDays(2)->addHours(3)->toDateTimeString());

        $this->assertCount(0, EventsHappeningSoon::for($host));
    }

    public function test_a_cancelled_event_is_not_announced_on_the_morning_it_would_have_run(): void
    {
        $host = $this->user();
        $this->event($host, now()->addHour()->toDateTimeString(), null, ['event_cancelled' => true]);

        $this->assertCount(0, EventsHappeningSoon::for($host));
    }

    public function test_somebody_elses_event_is_not_my_business(): void
    {
        $host = $this->user();
        $stranger = $this->user();
        $this->event($host, now()->addHour()->toDateTimeString());

        $this->assertCount(0, EventsHappeningSoon::for($stranger));
    }

    public function test_a_declined_rsvp_does_not_put_it_on_my_dashboard(): void
    {
        $host = $this->user();
        $guest = $this->user();
        $link = $this->event($host, now()->addHour()->toDateTimeString());

        Rsvp::create([
            'link_id' => $link->id, 'user_id' => $guest->id, 'name' => 'Priya',
            'email' => 'priya@example.com', 'response' => 'no', 'status' => 'confirmed',
        ]);

        $this->assertCount(0, EventsHappeningSoon::for($guest));
    }

    public function test_a_waitlisted_guest_has_no_seat_yet(): void
    {
        $host = $this->user();
        $guest = $this->user();
        $link = $this->event($host, now()->addHour()->toDateTimeString());

        Rsvp::create([
            'link_id' => $link->id, 'user_id' => $guest->id, 'name' => 'Priya',
            'email' => 'priya@example.com', 'response' => 'yes', 'status' => 'waitlist',
        ]);

        $this->assertCount(0, EventsHappeningSoon::for($guest));
    }

    public function test_the_dashboard_says_nothing_when_there_is_nothing_to_say(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get(route('user.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Events happening now or soon');
    }

    public function test_the_dashboard_shows_the_event_when_there_is_one(): void
    {
        $host = $this->user();
        $this->event($host, now()->addHour()->toDateTimeString());

        $response = $this->actingAs($host)->get(route('user.dashboard'));

        $response->assertOk();
        $response->assertSee('Founder Sessions');
        $response->assertSee('Starts soon');
    }
}
