<?php

namespace Tests\Feature;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Behaviour-level parity guard for the "My Calendar" surface: the on-screen
 * agenda/grid ({@see \App\Modules\User\Controllers\CalendarController::myCalendar})
 * and the downloadable ICS/CSV export
 * ({@see \App\Modules\User\Controllers\CalendarController::myCalendarExport})
 * now share one filter/query builder (`buildMyCalendarQuery`). That removes the
 * old code-level duplication, but nothing directly asserted the two surfaces
 * return the *same set of events* for the same filters.
 *
 * This test locks the guarantee in at the behaviour level: for a range of
 * filter combinations (source, a specific calendar, a tag, a from/to range,
 * past on/off) across every view (agenda / month / week / day) it asserts the
 * event ids the screen would render exactly equal the event ids present in the
 * export. Any future change to the shared builder — or an accidental re-fork of
 * the two code paths — is caught immediately.
 *
 * It deliberately drives both surfaces through their real HTTP routes (under the
 * same auth + workspace.scope middleware as {@see MyCalendarExportTest}) so the
 * comparison reflects exactly what a user gets, not an internal query.
 */
class MyCalendarExportParityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function makeCalendar(User $owner, array $attrs = []): Calendar
    {
        // calendars.link_id is a required 1:1 bridge to the owning links row.
        $link = Link::create([
            'user_id' => $owner->id,
            'type'    => Link::TYPE_CALENDAR,
            'alias'   => 'cal-' . Str::random(10),
            'title'   => $attrs['title'] ?? 'Owner Calendar',
        ]);

        return Calendar::create(array_merge([
            'user_id'         => $owner->id,
            'link_id'         => $link->id,
            'title'           => 'Owner Calendar',
            'is_public'       => true,
            'followers_count' => 0,
            'timezone'        => 'UTC',
        ], $attrs));
    }

    private function makeEvent(Calendar $cal, array $attrs = []): CalendarEvent
    {
        return CalendarEvent::create(array_merge([
            'calendar_id' => $cal->id,
            'user_id'     => $cal->user_id,
            'title'       => 'Event ' . Str::random(4),
            'start_at'    => Carbon::now('UTC')->addDays(3),
            'timezone'    => 'UTC',
        ], $attrs));
    }

    /** Bind a workspace + owner so workspace.scope resolves in user-module routes. */
    private function bind(User $u): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $ws;
    }

    /**
     * The event ids the on-screen agenda/grid would render, read from the view
     * data (agenda paginates into `events`; grid views collect into
     * `gridEvents`), independent of blade markup.
     *
     * @return array<int> sorted, de-duplicated ids
     */
    private function viewIds(User $user, array $query = []): array
    {
        $this->bind($user);

        $res = $this->actingAs($user)
            ->get(route('user.calendars.mine', $query))
            ->assertOk();

        $ids = collect();

        $events = $res->viewData('events');
        if ($events !== null) {
            // Paginator (agenda view) — items() are the models on this page.
            $ids = $ids->merge(collect($events->items())->pluck('id'));
        }

        $grid = $res->viewData('gridEvents');
        if ($grid !== null) {
            $ids = $ids->merge(collect($grid)->pluck('id'));
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * The event ids present in the export, parsed from the ICS `UID:ev-{id}@1inme`
     * lines the shared exporter emits.
     *
     * @return array<int> sorted, de-duplicated ids
     */
    private function exportIds(User $user, array $query = []): array
    {
        $this->bind($user);

        $body = $this->actingAs($user)
            ->get(route('user.calendars.mine.export', $query))
            ->assertOk()
            ->streamedContent();

        preg_match_all('/UID:ev-(\d+)@1inme/', $body, $m);

        return collect($m[1])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * Assert both surfaces resolve to the identical event-id set for a filter
     * combination, across every view. The `view` query param is injected here
     * so a single case exercises agenda + month + week + day.
     */
    private function assertParityAcrossViews(User $user, array $filters): void
    {
        foreach (['agenda', 'month', 'week', 'day'] as $view) {
            $query = $filters + ['view' => $view];

            $this->assertSame(
                $this->viewIds($user, $query),
                $this->exportIds($user, $query),
                "screen vs export drifted for view={$view}, filters=" . json_encode($filters)
            );
        }
    }

    public function test_screen_and_export_agree_for_source_calendar_tag_range_and_past_filters(): void
    {
        // Pin "now" so the default forward-looking clamp is deterministic and a
        // couple of events land on either side of it (past vs future).
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00', 'UTC'));

        $user  = $this->makeUser(['timezone' => 'UTC']);
        $other = $this->makeUser(['timezone' => 'UTC']);

        $calA = $this->makeCalendar($user,  ['title' => 'Cal A']);
        $calB = $this->makeCalendar($user,  ['title' => 'Cal B']);
        $followed = $this->makeCalendar($other, ['title' => 'Theirs']);
        CalendarFollow::create([
            'calendar_id' => $followed->id,
            'follower_id' => $user->id,
            'created_at'  => now(),
        ]);

        // A spread of events across calendars, tags, and dates (past + future).
        $this->makeEvent($calA, ['title' => 'A-past',   'start_at' => Carbon::parse('2026-07-05 09:00:00', 'UTC'), 'hashtags' => CalendarEvent::normalizeHashtags('#music')]);
        $this->makeEvent($calA, ['title' => 'A-future', 'start_at' => Carbon::parse('2026-07-18 09:00:00', 'UTC'), 'hashtags' => CalendarEvent::normalizeHashtags('#music')]);
        $this->makeEvent($calA, ['title' => 'A-sports', 'start_at' => Carbon::parse('2026-07-20 09:00:00', 'UTC'), 'hashtags' => CalendarEvent::normalizeHashtags('#sports')]);
        $this->makeEvent($calB, ['title' => 'B-future', 'start_at' => Carbon::parse('2026-07-16 09:00:00', 'UTC')]);
        $this->makeEvent($calB, ['title' => 'B-allday', 'start_at' => Carbon::parse('2026-07-17 00:00:00', 'UTC'), 'all_day' => true]);
        $this->makeEvent($followed, ['title' => 'F-future', 'start_at' => Carbon::parse('2026-07-19 09:00:00', 'UTC')]);

        // source = all / owned / followed (default forward-looking clamp on).
        $this->assertParityAcrossViews($user, []);
        $this->assertParityAcrossViews($user, ['source' => 'owned']);
        $this->assertParityAcrossViews($user, ['source' => 'followed']);

        // A specific owned calendar.
        $this->assertParityAcrossViews($user, ['calendar' => $calA->id]);

        // A tag filter.
        $this->assertParityAcrossViews($user, ['tag' => 'music']);

        // An explicit from/to range (spanning past + future).
        $this->assertParityAcrossViews($user, ['from' => '2026-07-01', 'to' => '2026-07-31']);

        // Past toggle on (default clamp lifted).
        $this->assertParityAcrossViews($user, ['past' => 1]);

        // Tag + source + past combined.
        $this->assertParityAcrossViews($user, ['source' => 'owned', 'tag' => 'sports', 'past' => 1]);

        Carbon::setTestNow();
    }

    public function test_screen_and_export_agree_on_an_empty_result(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00', 'UTC'));

        $user = $this->makeUser(['timezone' => 'UTC']);
        $cal  = $this->makeCalendar($user);
        // Only a past event exists; the default forward-looking agenda hides it,
        // so both surfaces must resolve to the same empty set.
        $this->makeEvent($cal, ['title' => 'Old', 'start_at' => Carbon::parse('2026-07-01 09:00:00', 'UTC')]);

        $this->assertSame([], $this->viewIds($user));
        $this->assertSame([], $this->exportIds($user));
        $this->assertSame($this->viewIds($user), $this->exportIds($user));

        Carbon::setTestNow();
    }
}
