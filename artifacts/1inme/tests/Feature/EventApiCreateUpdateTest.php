<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Organizer event (ics link) create/update over the mobile REST API
 * (EventApiController). Mirrors the essential subset of the web
 * IcsLinkController: title, start/end, timezone, location, description,
 * capacity, RSVP on/off. Advanced settings stay web-only and must never be
 * clobbered by a mobile essentials save.
 */
class EventApiCreateUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function auth(User $user): void
    {
        $this->withToken($user->createToken('test')->plainTextToken);
    }

    /** Link has no factory — create it directly + bind the workspace. */
    private function makeEvent(User $user, array $overrides = []): Link
    {
        $ws = $user->ownedWorkspaces()->first();
        $link = new Link(array_merge([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Existing Event',
            'is_active' => true,
        ], $overrides));
        $link->workspace_id = $ws->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ]);

        return $link->fresh('icsData');
    }

    public function test_create_event_persists_link_ics_and_active_workspace(): void
    {
        $user = $this->makeUser();
        $ws   = $user->ownedWorkspaces()->first();
        $this->auth($user);

        $resp = $this->postJson('/api/v1/events', [
            'title'        => 'Launch Party',
            'description'  => 'Come celebrate',
            'location'     => 'HQ Rooftop',
            'start_date'   => '2030-06-01T18:00',
            'end_date'     => '2030-06-01T21:00',
            'timezone'     => 'America/New_York',
            'capacity'     => 120,
            'rsvp_enabled' => true,
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.title', 'Launch Party');
        $resp->assertJsonPath('data.rsvp_enabled', true);
        $resp->assertJsonPath('data.capacity', 120);

        $id = $resp->json('data.id');
        $link = Link::withoutGlobalScope('workspace')->find($id);
        $this->assertNotNull($link);
        $this->assertSame('ics', $link->type);
        // Explicitly resolved workspace (API path skips SetActiveWorkspace).
        $this->assertSame($ws->id, $link->workspace_id);

        $ics = IcsData::where('link_id', $id)->first();
        $this->assertNotNull($ics);
        $this->assertSame('Launch Party', $ics->event_name);
        $this->assertSame('HQ Rooftop', $ics->location);
        $this->assertSame('America/New_York', $ics->timezone);

        // Capacity stored under rsvp_settings, RSVP left enabled (not disabled).
        $s = (array) $link->settings;
        $this->assertSame(120, (int) ($s['rsvp_settings']['capacity'] ?? 0));
        $this->assertFalse((bool) ($s['rsvp_disabled'] ?? false));
    }

    public function test_create_requires_title_and_valid_dates(): void
    {
        $user = $this->makeUser();
        $this->auth($user);

        // The API wraps validation errors under {error:{details:{...}}}.
        $this->postJson('/api/v1/events', [
            'start_date' => '2030-06-01T18:00',
            'end_date'   => '2030-06-01T21:00',
            'timezone'   => 'UTC',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['title']]]);
    }

    public function test_create_rejects_end_far_after_start(): void
    {
        $user = $this->makeUser();
        $this->auth($user);

        // > 36h apart — the shared cross-midnight rule must reject it.
        $this->postJson('/api/v1/events', [
            'title'      => 'Too Long',
            'start_date' => '2030-06-01T18:00',
            'end_date'   => '2030-06-05T18:00',
            'timezone'   => 'UTC',
        ])->assertStatus(422)->assertJsonStructure(['error' => ['details' => ['end_date']]]);
    }

    public function test_update_event_edits_essentials_and_toggles_rsvp_off(): void
    {
        $user = $this->makeUser();
        $this->auth($user);
        $link = $this->makeEvent($user);

        $resp = $this->patchJson("/api/v1/links/{$link->id}/event", [
            'title'        => 'Renamed Event',
            'description'  => 'Updated details',
            'location'     => 'New Venue',
            'start_date'   => '2030-01-02T10:00',
            'end_date'     => '2030-01-02T12:00',
            'timezone'     => 'Europe/London',
            'rsvp_enabled' => false,
        ]);

        $resp->assertStatus(200);
        $resp->assertJsonPath('data.title', 'Renamed Event');
        $resp->assertJsonPath('data.rsvp_enabled', false);

        $link->refresh();
        $this->assertSame('Renamed Event', $link->title);
        $this->assertTrue((bool) (((array) $link->settings)['rsvp_disabled'] ?? false));

        $ics = $link->icsData()->first();
        $this->assertSame('Renamed Event', $ics->event_name);
        $this->assertSame('New Venue', $ics->location);
        $this->assertSame('Europe/London', $ics->timezone);
    }

    public function test_update_preserves_advanced_settings(): void
    {
        $user = $this->makeUser();
        $this->auth($user);
        $link = $this->makeEvent($user, []);
        // Seed advanced (web-only) settings the mobile save must not wipe.
        $link->settings = [
            'calendar_sync_mode' => 'keep_in_sync',
            'rsvp_settings'      => ['questions' => [['label' => 'Dietary needs', 'type' => 'text']]],
        ];
        $link->save();

        $this->patchJson("/api/v1/links/{$link->id}/event", [
            'title'        => 'Kept Advanced',
            'start_date'   => '2030-01-01T18:00',
            'end_date'     => '2030-01-01T20:00',
            'timezone'     => 'UTC',
            'capacity'     => 50,
            'rsvp_enabled' => true,
        ])->assertStatus(200);

        $link->refresh();
        $s = (array) $link->settings;
        $this->assertSame('keep_in_sync', $s['calendar_sync_mode']);
        // The custom RSVP question survives; capacity is merged in alongside it.
        $this->assertCount(1, $s['rsvp_settings']['questions']);
        $this->assertSame(50, (int) $s['rsvp_settings']['capacity']);
    }

    public function test_create_is_blocked_when_over_plan_event_limit(): void
    {
        // Plan allows events but caps at a single one (unlimited links so only
        // the event cap can trip). Mirrors the web CheckPlanLimit:events gate.
        $slug = 'p' . Str::lower(Str::random(6));
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => [
                'events'     => true,
                'max_events' => 1,
                'max_links'  => -1,
            ],
        ]);

        $user = User::factory()->create(['plan_id' => $plan->id])->fresh();
        $this->auth($user);

        // First event succeeds (0 -> under cap).
        $this->postJson('/api/v1/events', [
            'title'      => 'First',
            'start_date' => '2030-06-01T18:00',
            'end_date'   => '2030-06-01T21:00',
            'timezone'   => 'UTC',
        ])->assertStatus(201);

        // Second event is over the cap: plan-gate error envelope (402) with the
        // recommended-upgrade code the mobile upgrade screen consumes.
        $this->postJson('/api/v1/events', [
            'title'      => 'Second',
            'start_date' => '2030-06-02T18:00',
            'end_date'   => '2030-06-02T21:00',
            'timezone'   => 'UTC',
        ])
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'plan_upgrade_required')
            ->assertJsonPath('error.details.feature', 'max_events');

        // Only the first event actually persisted.
        $this->assertSame(
            1,
            Link::withoutGlobalScope('workspace')
                ->where('user_id', $user->id)->where('type', 'ics')->count()
        );
    }

    public function test_cannot_edit_another_users_event(): void
    {
        $owner = $this->makeUser();
        $link  = $this->makeEvent($owner);

        $intruder = $this->makeUser();
        $this->auth($intruder);

        $this->patchJson("/api/v1/links/{$link->id}/event", [
            'title'      => 'Hijacked',
            'start_date' => '2030-01-01T18:00',
            'end_date'   => '2030-01-01T20:00',
            'timezone'   => 'UTC',
        ])->assertStatus(404);
    }
}
