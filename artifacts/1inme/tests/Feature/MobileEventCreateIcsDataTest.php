<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile / REST parity for the "Event invite" create flow (Task #3680).
 *
 * The Expo app posts POST /api/v1/links with type "event" and a loose
 * settings.event {start,end,location} blob. Historically that stored a bare
 * link with no IcsData row, so the public event page + RSVP flow (which read
 * from ics_data) rendered nothing — unlike a web-created event. The API
 * store() path now maps type "event" -> "ics" and builds the companion
 * IcsData row so a mobile-created event resolves to the same page as a
 * web-created one.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware, so we mint a real token.
 */
class MobileEventCreateIcsDataTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_event_create_maps_to_ics_and_builds_ics_data(): void
    {
        $user  = $this->makeUser();
        $alias = 'ev' . Str::lower(Str::random(6));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'event',
                'alias'    => $alias,
                'title'    => 'Summer Launch Party',
                'settings' => [
                    'event' => [
                        'start'    => '2026-08-01T18:00:00Z',
                        'end'      => '2026-08-01T21:00:00Z',
                        'location' => 'Rooftop Bar',
                    ],
                ],
            ]);

        $resp->assertStatus(201);

        // Stored as the canonical event type, not a bare "event".
        $link = Link::where('alias', $alias)->first();
        $this->assertNotNull($link, 'the event link must be created');
        $this->assertSame('ics', $link->type);

        // Companion IcsData row exists with the mobile-supplied details.
        $ics = IcsData::where('link_id', $link->id)->first();
        $this->assertNotNull($ics, 'a companion ics_data row must be created');
        $this->assertSame('Summer Launch Party', $ics->event_name);
        $this->assertSame('Rooftop Bar', $ics->location);
        $this->assertSame(
            '2026-08-01 18:00:00',
            $ics->start_date->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-08-01 21:00:00',
            $ics->end_date->format('Y-m-d H:i:s')
        );
    }

    public function test_event_create_defaults_end_to_one_hour_after_start(): void
    {
        $user  = $this->makeUser();
        $alias = 'ev' . Str::lower(Str::random(6));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'event',
                'alias'    => $alias,
                'title'    => 'Standup',
                'settings' => ['event' => ['start' => '2026-08-01T09:00:00Z']],
            ]);

        $resp->assertStatus(201);

        $link = Link::where('alias', $alias)->firstOrFail();
        $ics  = IcsData::where('link_id', $link->id)->firstOrFail();
        // No end supplied -> defaults to start + 1 hour so the NOT NULL
        // end_date column always has a sensible value.
        $this->assertSame(
            '2026-08-01 10:00:00',
            $ics->end_date->format('Y-m-d H:i:s')
        );
    }

    public function test_event_create_requires_a_title(): void
    {
        $user = $this->makeUser();

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'event',
                'settings' => ['event' => ['start' => '2026-08-01T09:00:00Z']],
            ]);

        // A fresh event must carry a name (public page title) and start.
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_duplicate_reposts_ics_type_and_still_builds_ics_data(): void
    {
        // Mobile "duplicate" re-posts the stored type, which is now "ics".
        // That path must be accepted and still create a companion row so the
        // copy renders as an event too.
        $user  = $this->makeUser();
        $alias = 'ev' . Str::lower(Str::random(6));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'ics',
                'alias'    => $alias,
                'title'    => 'Copied Event',
                'settings' => [
                    'event' => [
                        'start' => '2026-09-10T12:00:00Z',
                        'end'   => '2026-09-10T13:30:00Z',
                    ],
                ],
            ]);

        $resp->assertStatus(201);

        $link = Link::where('alias', $alias)->firstOrFail();
        $this->assertSame('ics', $link->type);
        $ics = IcsData::where('link_id', $link->id)->first();
        $this->assertNotNull($ics, 'duplicating an event must copy the ics_data row');
        $this->assertSame('Copied Event', $ics->event_name);
    }
}
