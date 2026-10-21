<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventDiscoverability;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Support\Hallway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attendees who turn on discoverability may hand over a coordinate. It has
 * been stored since Task #5008 and never read back, so the question was
 * not how to show it but what showing it is allowed to mean.
 *
 * Agreeing to be listed at an event is not agreeing to be placed on a map
 * of it. A pin says where you are standing, continuously, to a room of
 * people you have not met. So the answer is a band, and the test that
 * matters most here is the one asserting no coordinate appears in the
 * response at all -- because that is the property a future change is
 * most likely to break by accident.
 */
class HallwayTellsYouHowFarNotWhereTest extends TestCase
{
    use RefreshDatabase;

    // Two points about 300m apart, and one across a city.
    private const LAT_A = 17.4356000;
    private const LNG_A = 78.4480000;
    private const LAT_B = 17.4383000;  // ~300m north of A
    private const LNG_B = 78.4480000;
    private const LAT_FAR = 17.3850000; // ~6km away
    private const LNG_FAR = 78.4867000;

    private function user(string $name): User
    {
        return User::factory()->create(['name' => $name, 'onboarded_at' => now()->subMonth()])->fresh();
    }

    private function liveEvent(User $host): Link
    {
        $link = new Link([
            'user_id' => $host->id, 'type' => 'ics',
            'alias' => 'evt' . uniqid(), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);
        $link->workspace_id = $host->ownedWorkspaces()->first()->id;
        $link->save();

        IcsData::create([
            'link_id' => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => now()->subHour()->toDateTimeString(),
            'end_date'   => now()->addHours(2)->toDateTimeString(),
            'timezone'   => 'UTC',
        ]);

        return $link->fresh();
    }

    private function attend(User $user, Link $link): void
    {
        Rsvp::create([
            'link_id' => $link->id, 'user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'response' => 'yes', 'status' => 'confirmed',
        ]);
    }

    private function beDiscoverable(User $user, Link $link, ?float $lat = null, ?float $lng = null): void
    {
        EventDiscoverability::create([
            'user_id' => $user->id, 'link_id' => $link->id,
            'expires_at' => now()->addHours(6), 'lat' => $lat, 'lng' => $lng,
        ]);
    }

    // ---- the band itself -------------------------------------------------

    public function test_the_same_room_is_one_place(): void
    {
        // Identical coordinates, and a few metres apart, must read the same.
        // Splitting a hall into nearer and further people is both useless
        // and faintly unpleasant.
        $this->assertSame('here', Hallway::band(self::LAT_A, self::LNG_A, self::LAT_A, self::LNG_A));
        $this->assertSame('here', Hallway::band(self::LAT_A, self::LNG_A, 17.4356500, 78.4480500));
    }

    public function test_a_few_streets_away_is_not_the_same_room(): void
    {
        $this->assertSame('close', Hallway::band(self::LAT_A, self::LNG_A, self::LAT_B, self::LNG_B));
    }

    public function test_across_the_city_is_further_than_a_walk(): void
    {
        $band = Hallway::band(self::LAT_A, self::LNG_A, self::LAT_FAR, self::LNG_FAR);

        $this->assertSame('nearby', $band);
    }

    public function test_the_other_side_of_the_world_falls_off_the_end(): void
    {
        $this->assertSame(
            Hallway::BAND_DISTANT,
            Hallway::band(self::LAT_A, self::LNG_A, -33.8688, 151.2093)
        );
    }

    public function test_a_missing_coordinate_is_unknown_rather_than_far(): void
    {
        // The common case. Reading it as "far away" would invent a fact.
        $this->assertNull(Hallway::band(null, null, self::LAT_A, self::LNG_A));
        $this->assertNull(Hallway::band(self::LAT_A, self::LNG_A, null, null));
        $this->assertNull(Hallway::band(self::LAT_A, null, self::LAT_B, self::LNG_B));
    }

    public function test_the_band_is_symmetric(): void
    {
        $there = Hallway::band(self::LAT_A, self::LNG_A, self::LAT_FAR, self::LNG_FAR);
        $back  = Hallway::band(self::LAT_FAR, self::LNG_FAR, self::LAT_A, self::LNG_A);

        $this->assertSame($there, $back);
    }

    // ---- what reaches the browser ---------------------------------------

    public function test_the_list_says_how_far(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($me, $link, self::LAT_A, self::LNG_A);
        $this->beDiscoverable($them, $link, self::LAT_B, self::LNG_B);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.items.0.distance', 'close');
        $response->assertJsonPath('data.my_location_shared', true);
    }

    public function test_the_list_never_says_where(): void
    {
        // The property most likely to be broken by a later change: someone
        // adds a field to the attendee shape and a coordinate rides along.
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($me, $link, self::LAT_A, self::LNG_A);
        $this->beDiscoverable($them, $link, self::LAT_FAR, self::LNG_FAR);

        $response = $this->actingAs($me)
            ->getJson(route('user.event-people.index', $link->alias))
            ->assertOk();

        // No key anywhere in the payload names a coordinate. A blunt
        // substring search for "lat" was the first version of this and it
        // reads the random e-mail the user factory generates, so it would
        // have failed roughly once in ten thousand runs for no reason.
        // Walking the keys is both stricter and not a coin flip.
        $this->assertSame([], $this->coordinateKeysIn($response->json()));

        // And no coordinate rides along under some other name. These are
        // the far attendee's, the one the viewer is measured against.
        $body = $response->getContent();
        $this->assertStringNotContainsString('17.385', $body);
        $this->assertStringNotContainsString('78.486', $body);
    }

    public function test_someone_who_shared_no_location_still_appears(): void
    {
        // Being listed and being measurable are separate choices. Declining
        // the second must not remove you from the room.
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($me, $link, self::LAT_A, self::LNG_A);
        $this->beDiscoverable($them, $link);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.items.0.user.name', 'Ravi');
        $response->assertJsonPath('data.items.0.distance', null);
    }

    public function test_a_viewer_without_a_location_measures_nothing(): void
    {
        $host = $this->user('Host');
        $link = $this->liveEvent($host);

        $me = $this->user('Priya');
        $them = $this->user('Ravi');
        $this->attend($me, $link);
        $this->attend($them, $link);
        $this->beDiscoverable($me, $link);
        $this->beDiscoverable($them, $link, self::LAT_A, self::LNG_A);

        $response = $this->actingAs($me)->getJson(route('user.event-people.index', $link->alias));

        $response->assertOk();
        $response->assertJsonPath('data.items.0.distance', null);
        $response->assertJsonPath('data.my_location_shared', false);
    }

    /**
     * Every key path in a decoded payload whose last segment names a
     * position. Recursive on purpose: the way this breaks later is someone
     * adding a nested object that carries the row through wholesale.
     */
    private function coordinateKeysIn($value, string $path = ''): array
    {
        if (!is_array($value)) {
            return [];
        }

        $found = [];

        foreach ($value as $key => $child) {
            $here = $path === '' ? (string) $key : $path . '.' . $key;

            if (is_string($key) && preg_match('/^(lat|lng|latitude|longitude|coord|coords|coordinates|position|geo)$/i', $key)) {
                $found[] = $here;
            }

            $found = array_merge($found, $this->coordinateKeysIn($child, $here));
        }

        return $found;
    }
}
