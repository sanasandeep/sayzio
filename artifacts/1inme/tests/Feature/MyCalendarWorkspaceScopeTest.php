<?php

namespace Tests\Feature;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\PersonalCalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6619 — My Calendar is scoped to the ACTIVE workspace by default, with
 * an "All workspaces" (`?ws=all`) escape hatch. Covers:
 *
 *  - web default active-workspace filtering + the ws=all toggle;
 *  - switching the active workspace changes what My Calendar shows;
 *  - followed calendars are never workspace-filtered;
 *  - the API v1 my-calendar feed honours the same default + toggle;
 *  - backfill of pre-scoping calendars into the owner's Personal workspace;
 *  - new calendars are stamped with a workspace at creation.
 *
 * Authenticated API requests use a REAL personal access token, NOT
 * Sanctum::actingAs (which breaks TouchSessionToken — see FollowableCalendarTest).
 */
class MyCalendarWorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    /** Bind workspace context (mirrors FollowableCalendarTest::bind). */
    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $ws;
    }

    private function makeCalendar(User $owner, ?int $workspaceId, string $title): Calendar
    {
        return Calendar::create([
            'user_id'      => $owner->id,
            'title'        => $title,
            'slug'         => 'c-' . uniqid(),
            'is_public'    => true,
            'timezone'     => 'UTC',
            'workspace_id' => $workspaceId,
        ]);
    }

    private function makeEvent(Calendar $cal, string $title): CalendarEvent
    {
        return $cal->events()->create([
            'user_id'  => $cal->user_id,
            'title'    => $title,
            'start_at' => now()->addDay(),
            'end_at'   => now()->addDay()->addHour(),
            'timezone' => 'UTC',
        ]);
    }

    /** A second (team) workspace owned by $u. */
    private function makeTeamWorkspace(User $u): Workspace
    {
        return $u->ownedWorkspaces()->create([
            'name'        => 'Team',
            'slug'        => 'team-' . uniqid(),
            'is_personal' => false,
        ]);
    }

    public function test_web_my_calendar_defaults_to_active_workspace_and_ws_all_shows_everything(): void
    {
        $user     = $this->makeUser();
        $personal = $this->bind($user);
        $team     = $this->makeTeamWorkspace($user);

        $calPersonal = $this->makeCalendar($user, $personal->id, 'Personal Cal');
        $calTeam     = $this->makeCalendar($user, $team->id, 'Team Cal');
        $this->makeEvent($calPersonal, 'Personal event');
        $this->makeEvent($calTeam, 'Team event');

        // Followed calendar in someone else's world — always visible.
        $other       = $this->makeUser();
        $otherWs     = app(WorkspaceContext::class)->resolve($other);
        $calFollowed = $this->makeCalendar($other, $otherWs->id, 'Followed Cal');
        $this->makeEvent($calFollowed, 'Followed event');
        CalendarFollow::create(['calendar_id' => $calFollowed->id, 'follower_id' => $user->id, 'created_at' => now()]);

        // Re-bind our user as the acting workspace context (bind() for $other leaked).
        app()->instance('current_workspace', $personal);
        app()->instance('workspace_owner', $user);
        session([WorkspaceContext::SESSION_KEY => $personal->id]);

        $res = $this->actingAs($user)->get(route('user.calendars.mine'));
        $res->assertOk()
            ->assertSee('Personal event')
            ->assertDontSee('Team event')
            ->assertSee('Followed event');

        $res = $this->actingAs($user)->get(route('user.calendars.mine', ['ws' => 'all']));
        $res->assertOk()
            ->assertSee('Personal event')
            ->assertSee('Team event')
            ->assertSee('Followed event');
    }

    public function test_switching_active_workspace_changes_web_my_calendar(): void
    {
        $user     = $this->makeUser();
        $personal = $this->bind($user);
        $team     = $this->makeTeamWorkspace($user);

        $this->makeEvent($this->makeCalendar($user, $personal->id, 'P Cal'), 'Personal-only event');
        $this->makeEvent($this->makeCalendar($user, $team->id, 'T Cal'), 'Team-only event');

        session([WorkspaceContext::SESSION_KEY => $team->id]);
        app(WorkspaceContext::class)->set($team);
        app()->instance('current_workspace', $team);

        $res = $this->actingAs($user)->get(route('user.calendars.mine'));
        $res->assertOk()
            ->assertSee('Team-only event')
            ->assertDontSee('Personal-only event');
    }

    public function test_api_feed_defaults_to_active_workspace_and_supports_ws_all(): void
    {
        $user     = $this->makeUser();
        $personal = $this->bind($user);
        $team     = $this->makeTeamWorkspace($user);

        $this->makeEvent($this->makeCalendar($user, $personal->id, 'P Cal'), 'API personal event');
        $this->makeEvent($this->makeCalendar($user, $team->id, 'T Cal'), 'API team event');

        // Persisted pointer is what the stateless API resolves.
        \DB::table('users')->where('id', $user->id)->update(['active_workspace_id' => $personal->id]);

        $token   = $user->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];

        $titles = collect($this->getJson('/api/v1/my-calendar', $headers)->assertOk()->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('API personal event'));
        $this->assertFalse($titles->contains('API team event'));

        $titles = collect($this->getJson('/api/v1/my-calendar?ws=all', $headers)->assertOk()->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('API personal event'));
        $this->assertTrue($titles->contains('API team event'));

        // Calendars index honours the same scope.
        $names = collect($this->getJson('/api/v1/calendars', $headers)->assertOk()->json('data.items'))->pluck('title');
        $this->assertTrue($names->contains('P Cal'));
        $this->assertFalse($names->contains('T Cal'));
    }

    public function test_unscoped_legacy_calendars_stay_visible_in_any_workspace(): void
    {
        $user = $this->makeUser();
        $this->bind($user);

        $this->makeEvent($this->makeCalendar($user, null, 'Legacy Cal'), 'Legacy event');

        $this->actingAs($user)->get(route('user.calendars.mine'))
            ->assertOk()
            ->assertSee('Legacy event');
    }

    public function test_backfill_assigns_legacy_calendars_to_personal_workspace(): void
    {
        $user     = $this->makeUser();
        $personal = $this->bind($user);

        $legacy = $this->makeCalendar($user, null, 'Legacy Cal');
        $this->assertNull($legacy->workspace_id);

        $updated = Calendar::backfillMissingWorkspaces();

        $this->assertGreaterThanOrEqual(1, $updated);
        $this->assertSame($personal->id, $legacy->fresh()->workspace_id);
        $this->assertTrue($legacy->fresh()->workspace->is_personal);
    }

    public function test_new_personal_tasks_calendar_is_stamped_with_personal_workspace(): void
    {
        $user     = $this->makeUser();
        $personal = $this->bind($user);

        $cal = PersonalCalendarSync::ensureCalendar($user);

        $this->assertSame($personal->id, $cal->workspace_id);
    }
}
