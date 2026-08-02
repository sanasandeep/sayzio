<?php

namespace Tests\Feature;

use App\Console\Commands\SendEventRsvpReminders;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\User\Models\EventBroadcast;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Services\EventBroadcastService;
use App\Modules\User\Services\WaitlistPromotionService;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Cancel event" flow for event organizers (Sayzio events).
 *
 * Covers: the web cancel action marks the event's settings state; the public
 * event page renders a cancellation banner and blocks RSVP/ticket sales;
 * reminders and waitlist auto-promotion skip cancelled events; the optional
 * "notify all guests" step fires the cancellation broadcast; and reactivate
 * clears the state.
 *
 * Per repo memory: Mail::fake() never records the Mail::raw the Emailer
 * pipeline emits, so we assert via in-app state (the persisted broadcast log
 * / RSVP status) rather than mail spooling.
 */
class EventCancellationTest extends TestCase
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

    private function makeEvent(User $user, array $settings = [], string $title = 'Launch Party'): Link
    {
        $link = Link::create([
            'user_id'    => $user->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => $title,
            'settings'   => $settings,
            'visibility' => 'public',
            'is_active'  => true,
        ]);
        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2035-06-01 09:00:00',
            'end_date'   => '2035-06-01 10:00:00',
            'timezone'   => 'UTC',
            'all_day'    => false,
        ]);
        return $link->fresh('icsData');
    }

    private function rsvp(Link $link, array $attrs): Rsvp
    {
        return Rsvp::create(array_merge([
            'link_id'  => $link->id,
            'name'     => 'Guest',
            'response' => 'yes',
            'status'   => 'confirmed',
        ], $attrs));
    }

    public function test_cancel_marks_settings_state_and_redirects(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host);

        $resp = $this->actingAs($host, 'web')
            ->post(route('user.links.ics.cancel.confirm', $link), ['notify_guests' => '0']);

        // No notify → hand off to the broadcast page with the cancel preset.
        $resp->assertRedirect(route('user.links.ics.broadcast', ['link' => $link, 'preset' => 'cancellation']));

        $link->refresh();
        $this->assertTrue($link->isEventCancelled());
        $this->assertNotNull($link->eventCancelledAt());
        $this->assertNotEmpty(($link->settings ?? [])['event_cancelled_at'] ?? null);
    }

    public function test_public_page_shows_cancelled_banner_and_hides_rsvp_cta(): void
    {
        $host = $this->makeUser('Grace Host');
        $link = $this->makeEvent($host, ['event_cancelled' => true, 'event_cancelled_at' => now()->toIso8601String()]);

        $resp = $this->get('/' . $link->alias);
        $resp->assertOk();
        $resp->assertSee('This event has been cancelled');
        // RSVP CTA must be gone.
        $resp->assertDontSee('RSVP now');
        $this->assertFalse(RedirectController::isRsvpAvailable($link->fresh()));
    }

    public function test_rsvp_form_and_submit_blocked_when_cancelled(): void
    {
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['event_cancelled' => true, 'event_cancelled_at' => now()->toIso8601String()]);

        $this->get('/' . $link->alias . '/rsvp')->assertNotFound();

        $this->post('/' . $link->alias . '/rsvp', [
            'name'     => 'Late Guest',
            'email'    => 'late@example.com',
            'response' => 'yes',
        ])->assertNotFound();

        $this->assertDatabaseMissing('rsvps', ['link_id' => $link->id, 'email' => 'late@example.com']);
    }

    public function test_ics_feed_reflects_cancellation_status(): void
    {
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['event_cancelled' => true, 'event_cancelled_at' => now()->toIso8601String()]);

        $resp = $this->get('/' . $link->alias . '?ics=1');
        $resp->assertOk();
        $this->assertStringContainsString('STATUS:CANCELLED', $resp->getContent());
    }

    public function test_reminders_skip_cancelled_events(): void
    {
        Mail::fake();
        Cache::flush();
        $host = $this->makeUser();

        // An event ~24h out with a confirmed RSVP would normally get a reminder.
        $link = $this->makeEvent($host, [
            'event_cancelled'    => true,
            'event_cancelled_at' => now()->toIso8601String(),
            'rsvp_settings'      => ['reminder_hours_before' => 24],
        ]);
        $link->icsData->update([
            'start_date' => now()->addHours(24)->format('Y-m-d H:i:s'),
            'end_date'   => now()->addHours(25)->format('Y-m-d H:i:s'),
        ]);
        $this->rsvp($link, ['email' => 'g@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $this->artisan('events:send-rsvp-reminders')
            ->expectsOutputToContain('Sent 0 RSVP reminder(s).')
            ->assertExitCode(0);

        // No reminder cache key was written for this rsvp.
        $rsvp = Rsvp::where('link_id', $link->id)->first();
        $this->assertNull(Cache::get('rsvp_reminder:' . $rsvp->id . ':' . md5(now()->addHours(24)->format('c'))));
    }

    public function test_waitlist_promotion_skipped_for_cancelled_event(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host, [
            'event_cancelled'    => true,
            'event_cancelled_at' => now()->toIso8601String(),
            'rsvp_settings'      => ['capacity' => 2, 'waitlist_enabled' => true],
        ]);

        // One confirmed (1 seat used), one waitlisted that WOULD fit if the
        // event were live — cancellation must keep it on the waitlist.
        $this->rsvp($link, ['email' => 'a@example.com', 'status' => 'confirmed']);
        $waiter = $this->rsvp($link, ['email' => 'b@example.com', 'status' => 'waitlist']);

        $promoted = app(WaitlistPromotionService::class)->promoteForLink($link->fresh());

        $this->assertSame(0, $promoted);
        $this->assertSame('waitlist', $waiter->fresh()->status);
    }

    public function test_notify_on_cancel_sends_broadcast_to_all_rsvps(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host);

        $this->rsvp($link, ['email' => 'going@example.com', 'response' => 'yes', 'status' => 'confirmed']);
        $this->rsvp($link, ['email' => 'wait@example.com', 'response' => 'yes', 'status' => 'waitlist']);
        $this->rsvp($link, ['email' => 'gone@example.com', 'response' => 'yes', 'status' => 'cancelled']);

        $resp = $this->actingAs($host, 'web')
            ->post(route('user.links.ics.cancel.confirm', $link), ['notify_guests' => '1']);

        $resp->assertRedirect(route('user.links.show', $link));

        $link->refresh();
        $this->assertTrue($link->isEventCancelled());

        // A cancellation broadcast to all_rsvps (2 non-cancelled guests) was logged.
        $broadcast = EventBroadcast::where('link_id', $link->id)->first();
        $this->assertNotNull($broadcast);
        $this->assertSame('all_rsvps', $broadcast->audience);
        $this->assertSame(2, $broadcast->recipients_count);
    }

    public function test_notify_on_cancel_still_cancels_when_broadcast_limited(): void
    {
        Mail::fake();
        $host = $this->makeUser();
        $link = $this->makeEvent($host);
        $this->rsvp($link, ['email' => 'g@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        // Seed the daily cap so the cancellation broadcast is refused.
        for ($i = 0; $i < EventBroadcastService::DAILY_CAP; $i++) {
            EventBroadcast::create([
                'link_id'          => $link->id,
                'user_id'          => $host->id,
                'audience'         => 'all_rsvps',
                'subject'          => "Prior {$i}",
                'message'          => 'x',
                'recipients_count' => 1,
                'created_at'       => now()->subHours(2),
                'updated_at'       => now()->subHours(2),
            ]);
        }

        $resp = $this->actingAs($host, 'web')
            ->post(route('user.links.ics.cancel.confirm', $link), ['notify_guests' => '1']);

        // Event is still cancelled; the organizer is pointed at the broadcast page.
        $resp->assertRedirect(route('user.links.ics.broadcast', $link));
        $resp->assertSessionHas('error');
        $this->assertTrue($link->fresh()->isEventCancelled());
    }

    public function test_reactivate_clears_cancelled_state(): void
    {
        $host = $this->makeUser();
        $link = $this->makeEvent($host, ['event_cancelled' => true, 'event_cancelled_at' => now()->toIso8601String()]);

        $resp = $this->actingAs($host, 'web')
            ->post(route('user.links.ics.reactivate', $link));

        $resp->assertRedirect(route('user.links.show', $link));

        $link->refresh();
        $this->assertFalse($link->isEventCancelled());
        $this->assertNull($link->eventCancelledAt());
        $this->assertTrue(RedirectController::isRsvpAvailable($link));
    }

    private function ticketedCancelledEvent(User $host): array
    {
        $link = $this->makeEvent($host, [
            'ticketing_enabled'  => true,
            'event_cancelled'    => true,
            'event_cancelled_at' => now()->toIso8601String(),
        ]);
        $tier = EventTicketTier::create([
            'link_id'     => $link->id,
            'name'        => 'General',
            'price_cents' => 0,
            'currency'    => 'USD',
            'is_active'   => true,
            'sort_order'  => 1,
        ]);
        return [$link, $tier];
    }

    public function test_api_buy_refused_for_cancelled_event(): void
    {
        $host = $this->makeUser();
        [$link, $tier] = $this->ticketedCancelledEvent($host);

        // Auth as a buyer (mobile buy is auth-required).
        $buyer = $this->makeUser('Buyer');
        // Reset the workspace instances that makeUser() rebinds so the
        // public alias resolution isn't scoped to the buyer's workspace.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $resp = $this->withToken($buyer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/events/' . $link->alias . '/buy', [
                'tier_id'  => $tier->id,
                'quantity' => 1,
                'name'     => 'Buyer',
                'email'    => 'buyer@example.com',
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.message', 'This event has been cancelled — ticket sales are closed.');

        // No checkout / ticket was started.
        $this->assertDatabaseMissing('event_tickets', ['link_id' => $link->id]);
    }

    public function test_web_buy_refused_for_cancelled_event(): void
    {
        $host = $this->makeUser();
        [$link, $tier] = $this->ticketedCancelledEvent($host);
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $resp = $this->post('/' . $link->alias . '/tickets/buy', [
            'tier_id'  => $tier->id,
            'quantity' => 1,
            'name'     => 'Buyer',
            'email'    => 'buyer@example.com',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('error');
        $this->assertDatabaseMissing('event_tickets', ['link_id' => $link->id]);
    }

    public function test_link_ticket_sales_closed_reason_is_shared_source_of_truth(): void
    {
        $host = $this->makeUser();
        $live = $this->makeEvent($host, ['ticketing_enabled' => true]);
        $this->assertNull($live->eventTicketSalesClosedReason());

        [$cancelled] = $this->ticketedCancelledEvent($host);
        $this->assertSame(
            'This event has been cancelled — ticket sales are closed.',
            $cancelled->eventTicketSalesClosedReason()
        );
    }

    public function test_cancel_forbidden_for_non_owner(): void
    {
        $host = $this->makeUser();
        $link = $this->makeEvent($host);
        $other = $this->makeUser('Mallory');

        // A different workspace owner may hit either the controller's 403
        // ownership guard or the workspace-scoped route-binding 404 first;
        // both correctly deny the action. Assert access is refused and the
        // event was NOT cancelled.
        $status = $this->actingAs($other, 'web')
            ->post(route('user.links.ics.cancel.confirm', $link), ['notify_guests' => '0'])
            ->baseResponse->getStatusCode();

        $this->assertContains($status, [403, 404]);
        $this->assertFalse($link->fresh()->isEventCancelled());
    }
}
