<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Support\PlatformTimezone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #3480 made IST the platform's fallback timezone and swept the app
 * layer for hardcoded UTC. It did not reach the form controls, so a new
 * event still opened on "UTC" -- which is nobody's timezone, and is the
 * one value guaranteed to be wrong for the person filling the form in.
 *
 * The rule, in order: the timezone the browser reports, then the one the
 * user saved on their profile, then the platform default. Never UTC.
 *
 * Detection is JavaScript, so the server still has to render a sensible
 * default underneath it -- these tests are about that server-rendered
 * value, plus the opt-in attribute that lets the script improve on it.
 *
 * The edit form deliberately does NOT opt in. A stored timezone is a
 * decision the organiser already made and attendees already read; it
 * must not change because the organiser opened the editor from another
 * country.
 */
class EventFormsStartInYourTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function user(?string $timezone = null): User
    {
        // users.timezone is NOT NULL, and the factory forces every passed
        // attribute into the INSERT -- so passing null explicitly is a
        // constraint violation rather than "leave it to the default".
        // Omit the key entirely to get the model's own default.
        $attributes = ['onboarded_at' => now()->subMonths(6)];

        if ($timezone !== null) {
            $attributes['timezone'] = $timezone;
        }

        return User::factory()->create($attributes)->fresh();
    }

    private function event(User $host, string $timezone): Link
    {
        $link = new Link([
            'user_id' => $host->id, 'type' => 'ics',
            'alias' => 'evt' . uniqid(), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);
        $link->workspace_id = $host->ownedWorkspaces()->first()->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Founder Sessions',
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date'   => now()->addDay()->addHours(2)->toDateTimeString(),
            'timezone'   => $timezone,
        ]);

        return $link->fresh();
    }

    private function selectedTimezone(string $html): ?string
    {
        // The option the browser will show before any script runs.
        return preg_match('~<option value="([^"]+)"\s+selected~', $html, $m) ? $m[1] : null;
    }

    public function test_a_new_event_starts_in_the_platform_timezone_not_utc(): void
    {
        $html = (string) $this->actingAs($this->user())
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertSame(PlatformTimezone::platformDefault(), $this->selectedTimezone($html));
        $this->assertNotSame('UTC', $this->selectedTimezone($html));
    }

    public function test_a_new_event_prefers_the_timezone_the_user_saved(): void
    {
        $html = (string) $this->actingAs($this->user('America/New_York'))
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertSame('America/New_York', $this->selectedTimezone($html));
    }

    public function test_the_new_event_form_lets_the_browser_improve_on_that(): void
    {
        $html = (string) $this->actingAs($this->user())
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tz-autodetect', $html, 'the new-event form does not opt into detection');
        $this->assertStringContainsString('resolvedOptions', $html, 'the detection script did not render');
    }

    public function test_editing_an_event_keeps_the_timezone_it_was_created_with(): void
    {
        $host = $this->user('America/New_York');
        $link = $this->event($host, 'Europe/London');

        $html = (string) $this->actingAs($host)
            ->get(route('user.links.ics.edit', $link, false))
            ->assertOk()
            ->getContent();

        // Neither the host's own timezone nor the platform default wins
        // here -- the event's does.
        $this->assertSame('Europe/London', $this->selectedTimezone($html));
        $this->assertStringNotContainsString(
            'data-tz-autodetect',
            $html,
            'the edit form must not let a browser override a stored event timezone'
        );
    }

    public function test_an_event_with_no_timezone_falls_back_to_ist_not_utc(): void
    {
        $host = $this->user();
        $link = $this->event($host, '');

        $html = (string) $this->actingAs($host)
            ->get(route('user.links.ics.edit', $link, false))
            ->assertOk()
            ->getContent();

        $this->assertSame(PlatformTimezone::platformDefault(), $this->selectedTimezone($html));
    }

    public function test_an_account_still_storing_utc_keeps_utc_on_the_server(): void
    {
        // Not an aspiration -- a record of what actually happens, and why
        // the browser half of this matters.
        //
        // users.timezone has carried a column default of 'UTC' since
        // 2024. Task #3480 made the MODEL default IST but never changed
        // the column, so every account predating it has a literal 'UTC'
        // stored. PlatformTimezone::resolve() substitutes only for an
        // EMPTY value, and rightly so: a stored 'UTC' cannot be told
        // apart from someone who chose UTC on purpose, and rewriting it
        // would move their events by five and a half hours.
        //
        // So the server keeps UTC for these accounts, and the form opts
        // into browser detection to ask the one party that knows.
        $html = (string) $this->actingAs($this->user('UTC'))
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertSame('UTC', $this->selectedTimezone($html));
        $this->assertStringContainsString('data-tz-autodetect', $html);
    }

    public function test_an_empty_timezone_is_what_falls_back_to_ist(): void
    {
        $html = (string) $this->actingAs($this->user(''))
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertSame(PlatformTimezone::platformDefault(), $this->selectedTimezone($html));
    }

    public function test_no_timezone_control_hardcodes_a_default(): void
    {
        // Source-level, because the next one of these will be added by
        // copying an existing control rather than by reading the memory
        // note about the sweep.
        $views = [
            'user/links/create-ics',
            'user/links/edit-ics',
            'user/links/directory/editor',
            'user/links/catalog/editor',
        ];

        foreach ($views as $view) {
            $src = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertDoesNotMatchRegularExpression(
                "~timezone[^\n]{0,80}\\?\\?\\s*'(UTC|Asia/Kolkata)'~",
                $src,
                "{$view} hardcodes a timezone fallback instead of using PlatformTimezone"
            );
            $this->assertDoesNotMatchRegularExpression(
                "~old\\('timezone'[^\n]{0,60}'UTC'~",
                $src,
                "{$view} still defaults a timezone control to UTC"
            );
        }
    }

    public function test_the_selected_repeat_pill_is_readable_on_a_light_ground(): void
    {
        // .ics-freq-on sits on a translucent tint, so its text takes the
        // colour of whatever is behind it. A hardcoded #fff read fine in
        // dark mode and vanished in light mode.
        //
        // Its neighbour .day-chip-on is deliberately left alone: that one
        // has a SOLID accent fill, where white is correct in both themes.
        foreach (['user/links/create-ics', 'user/links/edit-ics'] as $view) {
            $src = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertTrue(
                (bool) preg_match('~\.ics-freq-on\s*\{([^}]*)\}~', $src, $m),
                "{$view} has no .ics-freq-on rule"
            );
            $this->assertStringNotContainsString(
                '#fff',
                $m[1],
                "{$view} paints the selected pill with a hardcoded white on a translucent ground"
            );
        }
    }
}
