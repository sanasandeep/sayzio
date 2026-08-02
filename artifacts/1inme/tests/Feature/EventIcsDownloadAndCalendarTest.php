<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the guest-facing add-to-calendar surface on the public event page:
 *
 *   1. The `?ics=1` download endpoint returns a text/calendar body with a
 *      proper DTSTART;TZID (organizer wall-clock time, NOT floating/UTC) and
 *      the correct DTSTART value for the event's timezone.
 *   2. The public event page renders a Google Calendar deep link.
 *
 * Link has no factory — created directly with a workspace bound into the
 * container (mirrors FreeEventRsvpCheckinRenderTest).
 */
class EventIcsDownloadAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Ada Organizer'): User
    {
        $u = User::factory()->create(['name' => $name]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function makeEvent(User $user, string $title = 'Launch Party'): Link
    {
        return Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => $title,
            'settings'   => [],
            'visibility' => 'public',
            'is_active'  => true,
        ]);
    }

    private function makeIcsData(Link $link, array $overrides = []): IcsData
    {
        return IcsData::create(array_merge([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'America/New_York',
            'all_day'    => false,
        ], $overrides));
    }

    public function test_ics_download_returns_calendar_with_tzid_and_correct_dtstart(): void
    {
        $host = $this->makeUser('Grace Host');
        $link = $this->makeEvent($host);
        // start_date is stored as UTC (app default); in America/New_York the
        // 09:00 UTC wall time is 05:00 local. The ICS must express the local
        // wall-clock time under the TZID, not the raw UTC value.
        $this->makeIcsData($link, [
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'America/New_York',
        ]);

        $resp = $this->get('/' . $link->alias . '?ics=1');
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

        $body = $resp->getContent();

        // Proper TZID present (not a floating or Z/UTC-only DTSTART).
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:', $body);
        $this->assertStringNotContainsString('DTSTART:20350601T090000Z', $body);
        // Wall-clock start in the event timezone: 09:00 UTC -> 05:00 EDT.
        $this->assertStringContainsString('DTSTART;TZID=America/New_York:20350601T050000', $body);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('END:VCALENDAR', $body);
    }

    public function test_event_page_renders_google_calendar_link(): void
    {
        $host = $this->makeUser('Rosa Host');
        $link = $this->makeEvent($host);
        $this->makeIcsData($link);

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $resp->assertSee('https://calendar.google.com/calendar/render', false);
        $resp->assertSee('action=TEMPLATE', false);
        // ctz carries the organizer timezone so Google interprets the UTC
        // dates against the right zone.
        $resp->assertSee('ctz=America', false);
        // The guest-local "your time" line is emitted (hidden client-side
        // unless the viewer's timezone differs).
        $resp->assertSee('id="ev-local-time"', false);
    }
}
