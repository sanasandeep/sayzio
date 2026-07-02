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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for {@see CalendarController::myCalendarExport} — the
 * downloadable ICS / CSV export of the cross-calendar "My Calendar" agenda.
 *
 * The export replicates the filter logic of {@see CalendarController::myCalendar}
 * in a separate code path, so these tests pin down that:
 *
 *  - an empty result still emits a syntactically valid VCALENDAR (no VEVENTs);
 *  - all-day events use the VALUE=DATE + exclusive DTEND shape;
 *  - special characters in title/description (semicolons, commas, newlines)
 *    are RFC 5545 escaped, not emitted raw;
 *  - the CSV export guards against spreadsheet formula injection;
 *  - the source / calendar / tag / date-range filters are all honoured and
 *    match what the on-screen agenda would show;
 *  - format=csv returns a CSV response with the expected header row.
 *
 * The route lives under the auth + workspace.scope middleware group, so every
 * request binds a workspace via {@see bind()} the same way the sibling
 * calendar event-CRUD tests do.
 */
class MyCalendarExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Cal ' . Str::random(4),
            'email'    => 'cal' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
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

    private function export(User $user, array $query = []): \Illuminate\Testing\TestResponse
    {
        $this->bind($user);

        return $this->actingAs($user)
            ->get(route('user.calendars.mine.export', $query));
    }

    // ── Empty result ───────────────────────────────────────────────────────

    public function test_ics_export_of_empty_calendar_is_a_valid_but_empty_vcalendar(): void
    {
        $user = $this->makeUser();
        $this->makeCalendar($user); // owned, but no events

        $res  = $this->export($user)->assertOk();
        $res->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');

        $body = $res->streamedContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
        $this->assertStringContainsString('VERSION:2.0', $body);
        // No events ⇒ no VEVENT blocks at all.
        $this->assertStringNotContainsString('BEGIN:VEVENT', $body);
    }

    // ── All-day event shape ────────────────────────────────────────────────

    public function test_ics_export_all_day_event_uses_value_date_with_exclusive_dtend(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);
        $this->makeEvent($cal, [
            'title'    => 'Conference Day',
            'start_at' => Carbon::now('UTC')->addDays(5)->startOfDay(),
            'all_day'  => true,
        ]);

        $day  = Carbon::now('UTC')->addDays(5);
        $next = $day->copy()->addDay();

        $body = $this->export($user)->assertOk()->streamedContent();

        $this->assertStringContainsString('DTSTART;VALUE=DATE:' . $day->format('Ymd'), $body);
        // DTEND is exclusive (start + 1 day) for all-day events.
        $this->assertStringContainsString('DTEND;VALUE=DATE:' . $next->format('Ymd'), $body);
        // No time component / TZID for an all-day event.
        $this->assertStringNotContainsString('DTSTART;TZID', $body);
    }

    // ── Special-character escaping (RFC 5545 §3.3.11) ──────────────────────

    public function test_ics_export_escapes_special_characters_in_title_and_description(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);
        $this->makeEvent($cal, [
            'title'       => 'Sale; half, off',
            'description' => "Line one\nLine two, with comma; and semicolon",
        ]);

        $body = $this->export($user)->assertOk()->streamedContent();

        // Semicolons and commas are backslash-escaped in SUMMARY.
        $this->assertStringContainsString('SUMMARY:Sale\; half\, off', $body);
        // Newline becomes the literal "\n" sequence; commas/semicolons escaped.
        $this->assertStringContainsString('Line one\nLine two\, with comma\; and semicolon', $body);
        // The raw, unescaped title must NOT leak through.
        $this->assertStringNotContainsString('SUMMARY:Sale; half, off', $body);
    }

    // ── CSV formula-injection guard ────────────────────────────────────────

    public function test_csv_export_guards_against_formula_injection(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);
        $this->makeEvent($cal, [
            'title'    => '=SUM(A1:A9)',
            'location' => '+1234567890',
        ]);

        $res  = $this->export($user, ['format' => 'csv'])->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $res->streamedContent();
        // Dangerous leading tokens (= + - @) are prefixed with a single quote.
        $this->assertStringContainsString("'=SUM(A1:A9)", $body);
        $this->assertStringContainsString("'+1234567890", $body);
        // The bare formula must never appear at the start of a cell.
        $this->assertStringNotContainsString(',=SUM(A1:A9)', $body);
    }

    // ── CSV header row / valid CSV ─────────────────────────────────────────

    public function test_csv_export_returns_expected_header_row(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);
        $this->makeEvent($cal, ['title' => 'Kickoff']);

        $res  = $this->export($user, ['format' => 'csv'])->assertOk();
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $res->assertHeader('Content-Disposition', 'attachment; filename="my-calendar-agenda-' . Carbon::now()->format('Y-m-d') . '.csv"');

        $body  = $res->streamedContent();
        $lines = preg_split('/\r\n|\n/', trim($body));

        // Parse the header row back through the CSV reader so the assertion is
        // robust to fputcsv's field-quoting (it quotes cells containing spaces).
        $this->assertSame(
            ['Title', 'Calendar', 'Start', 'End', 'All Day', 'Location', 'Description', 'Tags', 'Ticket URL'],
            str_getcsv($lines[0])
        );
        $this->assertStringContainsString('Kickoff', $body);
    }

    // ── source filter (owned vs followed) ──────────────────────────────────

    public function test_source_filter_scopes_owned_vs_followed_calendars(): void
    {
        $user     = $this->makeUser();
        $other    = $this->makeUser();

        $owned    = $this->makeCalendar($user, ['title' => 'Mine']);
        $followed = $this->makeCalendar($other, ['title' => 'Theirs']);
        CalendarFollow::create([
            'calendar_id' => $followed->id,
            'follower_id' => $user->id,
            'created_at'  => now(),
        ]);

        $this->makeEvent($owned, ['title' => 'OwnedEvent']);
        $this->makeEvent($followed, ['title' => 'FollowedEvent']);

        // source=owned ⇒ only the owned calendar's events.
        $ownedBody = $this->export($user, ['source' => 'owned'])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:OwnedEvent', $ownedBody);
        $this->assertStringNotContainsString('SUMMARY:FollowedEvent', $ownedBody);

        // source=followed ⇒ only the followed calendar's events.
        $followedBody = $this->export($user, ['source' => 'followed'])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:FollowedEvent', $followedBody);
        $this->assertStringNotContainsString('SUMMARY:OwnedEvent', $followedBody);

        // default (all) ⇒ both.
        $allBody = $this->export($user)->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:OwnedEvent', $allBody);
        $this->assertStringContainsString('SUMMARY:FollowedEvent', $allBody);
    }

    // ── calendar filter ────────────────────────────────────────────────────

    public function test_calendar_filter_restricts_to_a_single_owned_calendar(): void
    {
        $user = $this->makeUser();
        $calA = $this->makeCalendar($user, ['title' => 'Cal A']);
        $calB = $this->makeCalendar($user, ['title' => 'Cal B']);

        $this->makeEvent($calA, ['title' => 'EventInA']);
        $this->makeEvent($calB, ['title' => 'EventInB']);

        $body = $this->export($user, ['calendar' => $calA->id])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:EventInA', $body);
        $this->assertStringNotContainsString('SUMMARY:EventInB', $body);
    }

    public function test_calendar_filter_ignores_a_calendar_the_user_cannot_see(): void
    {
        $user  = $this->makeUser();
        $owned = $this->makeCalendar($user, ['title' => 'Mine']);
        $this->makeEvent($owned, ['title' => 'MyEvent']);

        // A calendar owned by someone else and not followed.
        $stranger = $this->makeUser();
        $foreign  = $this->makeCalendar($stranger, ['title' => 'Foreign']);
        $this->makeEvent($foreign, ['title' => 'ForeignEvent']);

        // Passing a foreign calendar id ⇒ the filter is ignored, falling back
        // to the full accessible set (here: just the owned calendar).
        $body = $this->export($user, ['calendar' => $foreign->id])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:MyEvent', $body);
        $this->assertStringNotContainsString('SUMMARY:ForeignEvent', $body);
    }

    // ── tag filter ─────────────────────────────────────────────────────────

    public function test_tag_filter_matches_only_events_with_the_hashtag(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);

        $this->makeEvent($cal, ['title' => 'Tagged', 'hashtags' => CalendarEvent::normalizeHashtags('#music')]);
        $this->makeEvent($cal, ['title' => 'Untagged', 'hashtags' => CalendarEvent::normalizeHashtags('#sports')]);

        $body = $this->export($user, ['tag' => 'music'])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:Tagged', $body);
        $this->assertStringNotContainsString('SUMMARY:Untagged', $body);
    }

    // ── date-range filter ──────────────────────────────────────────────────

    public function test_from_to_date_range_filters_events_inclusively(): void
    {
        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);

        $this->makeEvent($cal, ['title' => 'BeforeWindow', 'start_at' => Carbon::parse('2026-07-01 12:00:00', 'UTC')]);
        $this->makeEvent($cal, ['title' => 'OnFrom',       'start_at' => Carbon::parse('2026-07-05 00:30:00', 'UTC')]);
        $this->makeEvent($cal, ['title' => 'Inside',       'start_at' => Carbon::parse('2026-07-07 18:00:00', 'UTC')]);
        $this->makeEvent($cal, ['title' => 'OnTo',         'start_at' => Carbon::parse('2026-07-10 23:00:00', 'UTC')]);
        $this->makeEvent($cal, ['title' => 'AfterWindow',  'start_at' => Carbon::parse('2026-07-12 09:00:00', 'UTC')]);

        $body = $this->export($user, ['from' => '2026-07-05', 'to' => '2026-07-10'])
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('SUMMARY:OnFrom', $body);
        $this->assertStringContainsString('SUMMARY:Inside', $body);
        $this->assertStringContainsString('SUMMARY:OnTo', $body);
        $this->assertStringNotContainsString('SUMMARY:BeforeWindow', $body);
        $this->assertStringNotContainsString('SUMMARY:AfterWindow', $body);
    }

    public function test_agenda_default_excludes_past_events_unless_past_flag_is_set(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00', 'UTC'));

        $user = $this->makeUser();
        $cal  = $this->makeCalendar($user);
        $this->makeEvent($cal, ['title' => 'PastEvent',   'start_at' => Carbon::parse('2026-07-01 10:00:00', 'UTC')]);
        $this->makeEvent($cal, ['title' => 'FutureEvent', 'start_at' => Carbon::parse('2026-07-20 10:00:00', 'UTC')]);

        // Default agenda is forward-looking ⇒ past event omitted.
        $default = $this->export($user)->assertOk()->streamedContent();
        $this->assertStringNotContainsString('SUMMARY:PastEvent', $default);
        $this->assertStringContainsString('SUMMARY:FutureEvent', $default);

        // past=1 includes it.
        $withPast = $this->export($user, ['past' => 1])->assertOk()->streamedContent();
        $this->assertStringContainsString('SUMMARY:PastEvent', $withPast);
        $this->assertStringContainsString('SUMMARY:FutureEvent', $withPast);

        Carbon::setTestNow();
    }
}
