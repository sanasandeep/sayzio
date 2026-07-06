<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3676 — live/end-to-end coverage for the default-RSVP-availability
 * change (Task #3674), which was previously verified only via php -l,
 * Blade-compile checks, and mobile typecheck. These tests exercise a
 * freshly created *plain, non-ticketed* `ics` event end-to-end:
 *
 *   1. The public event page (`GET /{alias}`) renders the RSVP CTA and the
 *      organizer ("Hosted by") card with NO rsvp_enabled/ticketing settings
 *      at all — i.e. RSVP is on by default (isRsvpAvailable() returns true).
 *   2. The standalone RSVP form page (`GET /{alias}/rsvp`) renders and
 *      carries the organizer card too.
 *   3. "More from this host" lists the organizer's other public events.
 *   4. A guest RSVP ("yes") mints a tier-less QR check-in ticket, the
 *      owner's check-in API scans it successfully, and the owner ticket
 *      list (guest list) reflects the checked-in attendee.
 */
class FreeEventRsvpCheckinRenderTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Ada Organizer'): User
    {
        $u = User::factory()->create([
            'name'     => $name,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    /**
     * A *plain* non-ticketed ics link: no ticketing_enabled, no tiers, no
     * rsvp_disabled, no rsvp_enabled — the "free event" default case.
     * Safe-prefix alias ("evt") keeps it inside the public alias regex.
     */
    private function makeFreeEvent(User $user, array $settings = [], string $title = 'Launch Party'): Link
    {
        return Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => $title,
            'settings'   => $settings,
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
            'timezone'   => 'UTC',
            'all_day'    => false,
        ], $overrides));
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_free_event_public_page_renders_rsvp_cta_and_organizer_card(): void
    {
        $host = $this->makeUser('Grace Host');
        $link = $this->makeFreeEvent($host);
        $this->makeIcsData($link);

        // A plain ics link with no settings must still be RSVP-available by
        // default (Task #3674) — the whole point of this verification.
        $this->assertTrue(\App\Modules\Common\Controllers\RedirectController::isRsvpAvailable($link->fresh()));

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        // RSVP CTA points at the standalone RSVP form and shows the label.
        $resp->assertSee(route('redirect.rsvp.form', $link->alias), false);
        $resp->assertSee('RSVP now');
        // Organizer card renders the host name under "Hosted by".
        $resp->assertSee('Hosted by');
        $resp->assertSee('Grace Host');
    }

    public function test_free_event_rsvp_form_page_renders_with_organizer_card(): void
    {
        $host = $this->makeUser('Rosa Host');
        $link = $this->makeFreeEvent($host);
        $this->makeIcsData($link);

        $resp = $this->get('/' . $link->alias . '/rsvp');
        $resp->assertOk();
        $resp->assertSee('Hosted by');
        $resp->assertSee('Rosa Host');
    }

    /**
     * Task #3769: "More from this host" is a recommendation widget backed by
     * a short-TTL cache. On a cache miss the page itself renders instantly
     * WITHOUT it (a pending placeholder + `data-pending="1"` instead), and
     * the browser lazy-fetches the real content off the render path via
     * `GET /{alias}/event-extras`. So the first page load must NOT block on
     * it, and the separate fragment endpoint is what actually surfaces it.
     */
    public function test_more_from_this_host_lists_other_public_events(): void
    {
        $host = $this->makeUser('Mars Host');
        $link = $this->makeFreeEvent($host, [], 'Main Gig');
        $this->makeIcsData($link);

        // A second upcoming public event by the same host must surface.
        $other = $this->makeFreeEvent($host, [], 'Side Show');
        $this->makeIcsData($other, ['event_name' => 'Side Show']);

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $resp->assertDontSee('Side Show');
        $resp->assertSee('id="event-recommendations"', false);
        $resp->assertSee('data-pending="1"', false);

        $fragment = $this->get('/' . $link->alias . '/event-extras');
        $fragment->assertOk();
        $fragment->assertSee('More from this host');
        $fragment->assertSee('Side Show');
    }

    /**
     * Task #3769: once the recommendation extras are cached (e.g. after the
     * lazy fragment fetch above populates them), a subsequent page load
     * renders "More from this host" inline immediately — no second
     * client-side fetch needed.
     */
    public function test_more_from_this_host_renders_inline_once_cached(): void
    {
        $host = $this->makeUser('Nova Host');
        $link = $this->makeFreeEvent($host, [], 'Main Gig');
        $this->makeIcsData($link);
        $other = $this->makeFreeEvent($host, [], 'Encore Show');
        $this->makeIcsData($other, ['event_name' => 'Encore Show']);

        // Warm the per-link recommendation cache the same way the browser's
        // lazy fetch would.
        $this->get('/' . $link->alias . '/event-extras')->assertOk();

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $resp->assertSee('data-pending="0"', false);
        $resp->assertSee('More from this host');
        $resp->assertSee('Encore Show');
    }

    /**
     * Task #3769 regression guard: even when the recommendation cache is
     * completely cold, the core RSVP page must render fast and complete
     * (never a blank/502 page) — the whole point of deferring the
     * recommendation lookups off the render path.
     */
    public function test_rsvp_page_renders_fully_on_cold_recommendation_cache(): void
    {
        $host = $this->makeUser('Cold Cache Host');
        $link = $this->makeFreeEvent($host, [], 'Cold Start Party');
        $this->makeIcsData($link);

        $resp = $this->get('/' . $link->alias . '/rsvp');
        $resp->assertOk();
        $resp->assertSee('Hosted by');
        $resp->assertSee('data-pending="1"', false);
    }

    public function test_guest_rsvp_mints_checkin_ticket_and_owner_can_check_in(): void
    {
        Mail::fake();
        $host = $this->makeUser('Kit Host');
        $link = $this->makeFreeEvent($host);
        $this->makeIcsData($link);

        // Guest (not signed in) RSVPs "yes" via the public JSON endpoint.
        $submit = $this->postJson('/' . $link->alias . '/rsvp', [
            'name'     => 'Guesty McGuest',
            'email'    => 'guest@ex.com',
            'response' => 'yes',
        ]);
        $submit->assertOk();
        $submit->assertJson(['success' => true, 'status' => 'confirmed']);

        $rsvp = Rsvp::where('link_id', $link->id)->where('email', 'guest@ex.com')->firstOrFail();
        $this->assertSame('confirmed', $rsvp->status);
        $this->assertSame('yes', $rsvp->response);

        // A confirmed "yes" RSVP mints a tier-less QR check-in ticket.
        $ticket = EventTicket::where('rsvp_id', $rsvp->id)->firstOrFail();
        $this->assertNull($ticket->tier_id);
        $this->assertSame($link->id, $ticket->link_id);
        $this->assertSame(EventTicket::STATUS_VALID, $ticket->status);
        $this->assertSame('rsvp', $ticket->gateway);
        $this->assertNotEmpty($ticket->code);

        // Owner scans the QR code at the door via the check-in API.
        $token = $this->token($host);
        $checkin = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/links/' . $link->id . '/event-checkin', ['code' => $ticket->code]);
        $checkin->assertOk();
        $checkin->assertJson(['data' => ['ok' => true, 'status' => 'checked_in']]);

        $this->assertSame(EventTicket::STATUS_CHECKED_IN, $ticket->fresh()->status);

        // Re-scanning the same code reports it's already checked in.
        $again = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/links/' . $link->id . '/event-checkin', ['code' => $ticket->code]);
        $again->assertOk();
        $again->assertJson(['data' => ['ok' => false, 'status' => 'already_checked_in']]);

        // The owner guest list reflects the attendee + their checked-in state.
        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/links/' . $link->id . '/event-tickets');
        $list->assertOk();
        $codes = collect($list->json('data.items'))->pluck('code')->all();
        $this->assertContains($ticket->code, $codes);
    }
}
