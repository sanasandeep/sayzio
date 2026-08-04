<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\EventQrConnect;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Event Connect QR (Task #6685): scan-source click tagging, the one-flow
 * OTP → account → RSVP → follow "connect" endpoints (new user, existing
 * user, repeat idempotency, badge-gate refusal), and the QR Connect stats
 * attribution on the Visitor Insights page. Non-production OTP code is
 * the fixed dev value '123456'.
 */
class EventConnectQrTest extends TestCase
{
    use RefreshDatabase;

    private const OTP = '123456';

    private function makeHost(): User
    {
        return User::factory()->create()->fresh();
    }

    /** Link has no factory — create directly + bind the workspace. */
    private function makeEvent(User $host, array $icsOverrides = [], array $settings = []): Link
    {
        $ws = $host->ownedWorkspaces()->first();
        $link = new Link([
            'user_id'   => $host->id,
            'type'      => 'ics',
            'alias'     => 'cqr' . uniqid(),
            'title'     => 'Connect Party',
            'is_active' => true,
            'settings'  => array_merge(['rsvp_enabled' => true], $settings),
        ]);
        $link->workspace_id = $ws->id;
        $link->save();

        IcsData::create(array_merge([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ], $icsOverrides));

        return $link->fresh(['icsData', 'user']);
    }

    private function connectAsNewOrExisting(Link $link, string $email): \Illuminate\Testing\TestResponse
    {
        $this->postJson(route('events.connect-qr.send', $link->alias), [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertOk()->assertJson(['success' => true]);

        return $this->postJson(route('events.connect-qr.verify', $link->alias), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::OTP,
        ]);
    }

    // ---------------- scan tagging ----------------

    public function test_qr_tagged_visit_records_connect_qr_click_source(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);

        $this->get('/' . $link->alias . '?src=connect_qr')->assertOk();

        $click = LinkClick::where('link_id', $link->id)->orderByDesc('id')->first();
        $this->assertNotNull($click);
        $this->assertSame('connect_qr', $click->source);

        // A plain visit stays a normal 'web' click.
        $this->get('/' . $link->alias)->assertOk();
        $this->assertSame('web', LinkClick::where('link_id', $link->id)->orderByDesc('id')->first()->source);
    }

    public function test_event_page_shows_connect_prompt_only_with_qr_source(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);

        $this->get('/' . $link->alias . '?src=connect_qr')->assertOk()->assertSee('RSVP &amp; Connect', false);
        $this->get('/' . $link->alias)->assertOk()->assertDontSee('You scanned');
    }

    // ---------------- new-user flow ----------------

    public function test_new_user_scan_creates_account_rsvp_and_follow(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);
        $email = 'brand-new-' . uniqid() . '@example.com';

        $this->assertNull(User::where('email', $email)->first());

        $res = $this->connectAsNewOrExisting($link, $email);
        $res->assertOk()->assertJson(['success' => true, 'status' => 'confirmed', 'followed' => true]);

        $guest = User::where('email', $email)->first();
        $this->assertNotNull($guest, 'account auto-created');

        $rsvp = Rsvp::where('link_id', $link->id)->where('email', $email)->first();
        $this->assertNotNull($rsvp);
        $this->assertSame('yes', $rsvp->response);
        $this->assertSame('confirmed', $rsvp->status);
        $this->assertSame('connect_qr', $rsvp->source);

        $this->assertTrue(Follow::where('follower_id', $guest->id)->where('creator_id', $host->id)->exists());
        $this->assertSame(1, (int) $host->fresh()->followers_count);

        $connect = EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->first();
        $this->assertNotNull($connect);
        $this->assertTrue($connect->was_new_user);
        $this->assertSame($rsvp->id, (int) $connect->rsvp_id);
        $this->assertTrue($connect->followed);

        // Visitor is signed into the viewer session, not the dashboard guard.
        $this->assertSame($guest->id, session(ViewerSession::KEY));
    }

    // ---------------- existing-user flow ----------------

    public function test_existing_user_scan_marks_not_new_and_connects(): void
    {
        $host  = $this->makeHost();
        $link  = $this->makeEvent($host);
        $guest = User::factory()->create()->fresh();

        $this->connectAsNewOrExisting($link, $guest->email)
            ->assertOk()->assertJson(['success' => true]);

        $connect = EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->first();
        $this->assertNotNull($connect);
        $this->assertFalse($connect->was_new_user);
        $this->assertNotNull($connect->rsvp_id);
        $this->assertTrue($connect->followed);
    }

    public function test_signed_in_visitor_one_tap_confirm(): void
    {
        $host  = $this->makeHost();
        $link  = $this->makeEvent($host);
        $guest = User::factory()->create()->fresh();

        $this->withSession([ViewerSession::KEY => $guest->id])
            ->postJson(route('events.connect-qr.confirm', $link->alias))
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(1, Rsvp::where('link_id', $link->id)->where('email', $guest->email)->count());
        $this->assertTrue(Follow::where('follower_id', $guest->id)->where('creator_id', $host->id)->exists());
    }

    // ---------------- idempotency ----------------

    public function test_repeat_scans_stay_idempotent(): void
    {
        $host  = $this->makeHost();
        $link  = $this->makeEvent($host);
        $guest = User::factory()->create()->fresh();

        $this->connectAsNewOrExisting($link, $guest->email)->assertOk();
        // Second full round-trip (fresh OTP) + a one-tap confirm on top.
        $this->connectAsNewOrExisting($link, $guest->email)->assertOk();
        $this->withSession([ViewerSession::KEY => $guest->id])
            ->postJson(route('events.connect-qr.confirm', $link->alias))->assertOk();

        $this->assertSame(1, Rsvp::where('link_id', $link->id)->where('email', $guest->email)->count());
        $this->assertSame(1, Follow::where('follower_id', $guest->id)->where('creator_id', $host->id)->count());
        $this->assertSame(1, EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->count());
        $this->assertSame(1, (int) $host->fresh()->followers_count);
    }

    // ---------------- badge gating ----------------

    public function test_badge_gated_event_refuses_connect_without_badge(): void
    {
        $host  = $this->makeHost();
        $badge = AccountBadge::create(['name' => 'VIP Invite', 'created_by' => $host->id]);
        $link  = $this->makeEvent($host, ['required_badge_id' => $badge->id]);
        $guest = User::factory()->create()->fresh();

        $res = $this->connectAsNewOrExisting($link, $guest->email);
        $res->assertStatus(403)->assertJson(['success' => false, 'code' => 'badge_required']);

        $this->assertSame(0, Rsvp::where('link_id', $link->id)->count());
        $this->assertFalse(Follow::where('follower_id', $guest->id)->where('creator_id', $host->id)->exists());
        $this->assertSame(0, EventQrConnect::where('link_id', $link->id)->count());

        // With the badge, the same guest connects fine.
        $guest->accountBadges()->attach($badge->id);
        $this->connectAsNewOrExisting($link, $guest->email)->assertOk()->assertJson(['success' => true]);
    }

    // ---------------- capacity / waitlist ----------------

    public function test_full_event_with_waitlist_puts_connect_on_waitlist(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host, [], [
            'rsvp_settings' => ['capacity' => 1, 'waitlist_enabled' => true],
        ]);
        Rsvp::create(['link_id' => $link->id, 'name' => 'First', 'email' => 'first@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $guest = User::factory()->create()->fresh();
        $this->connectAsNewOrExisting($link, $guest->email)
            ->assertOk()->assertJson(['success' => true, 'status' => 'waitlist']);
        $this->assertSame('waitlist', Rsvp::where('email', $guest->email)->first()->status);
    }

    public function test_prior_maybe_or_no_rsvp_is_converted_to_yes(): void
    {
        $host  = $this->makeHost();
        $link  = $this->makeEvent($host);
        $guest = User::factory()->create()->fresh();

        $prior = Rsvp::create(['link_id' => $link->id, 'name' => 'G', 'email' => $guest->email, 'response' => 'maybe', 'status' => 'confirmed', 'source' => 'event_page']);

        $this->connectAsNewOrExisting($link, $guest->email)
            ->assertOk()->assertJson(['success' => true, 'status' => 'confirmed']);

        $prior->refresh();
        $this->assertSame('yes', $prior->response);
        $this->assertSame('confirmed', $prior->status);
        $this->assertSame('connect_qr', $prior->source);
        // Still exactly one RSVP — converted, not duplicated.
        $this->assertSame(1, Rsvp::where('link_id', $link->id)->where('email', $guest->email)->count());
    }

    public function test_converting_no_rsvp_to_yes_respects_capacity_waitlist(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host, [], [
            'rsvp_settings' => ['capacity' => 1, 'waitlist_enabled' => true],
        ]);
        Rsvp::create(['link_id' => $link->id, 'name' => 'First', 'email' => 'first@example.com', 'response' => 'yes', 'status' => 'confirmed']);

        $guest = User::factory()->create()->fresh();
        $prior = Rsvp::create(['link_id' => $link->id, 'name' => 'G', 'email' => $guest->email, 'response' => 'no', 'status' => 'confirmed']);

        $this->connectAsNewOrExisting($link, $guest->email)
            ->assertOk()->assertJson(['success' => true, 'status' => 'waitlist']);

        $prior->refresh();
        $this->assertSame('yes', $prior->response);
        $this->assertSame('waitlist', $prior->status);
    }

    public function test_email_less_mobile_user_never_touches_another_guests_rsvp(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);

        // Another attendee's email-less RSVP already on the event.
        $other = Rsvp::create(['link_id' => $link->id, 'name' => 'Anon', 'email' => null, 'phone' => '+1999', 'response' => 'maybe', 'status' => 'confirmed', 'source' => 'event_page']);

        // Mobile-only guest (email = null).
        $guest = User::factory()->create()->fresh();
        $guest->forceFill(['email' => null, 'mobile' => '+15551234567'])->save();

        $this->withSession([ViewerSession::KEY => $guest->id])
            ->postJson(route('events.connect-qr.confirm', $link->alias))
            ->assertOk()->assertJson(['success' => true]);

        // The stranger's RSVP is untouched; the guest got their own new row.
        $other->refresh();
        $this->assertSame('maybe', $other->response);
        $this->assertSame('event_page', $other->source);
        $own = Rsvp::where('link_id', $link->id)->where('phone', $guest->mobile)->first();
        $this->assertNotNull($own);
        $this->assertSame('yes', $own->response);
        $this->assertSame('connect_qr', $own->source);

        // Repeat confirm stays idempotent via the attribution row's rsvp_id.
        $this->withSession([ViewerSession::KEY => $guest->id])
            ->postJson(route('events.connect-qr.confirm', $link->alias))->assertOk();
        $this->assertSame(1, Rsvp::where('link_id', $link->id)->where('phone', $guest->mobile)->count());
        $this->assertSame(1, EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->count());
    }

    // ---------------- stats attribution ----------------

    public function test_visitor_insights_qr_connect_panel_counts_respect_range(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);

        // Two in-range scans, one never signing in.
        foreach (range(1, 2) as $i) {
            LinkClick::create([
                'link_id'    => $link->id,
                'source'     => 'connect_qr',
                'is_bot'     => false,
                'clicked_at' => now()->subDay(),
            ]);
        }
        // One out-of-range scan.
        LinkClick::create([
            'link_id'    => $link->id,
            'source'     => 'connect_qr',
            'is_bot'     => false,
            'clicked_at' => now()->subDays(400),
        ]);

        $new      = User::factory()->create();
        $existing = User::factory()->create();
        $old      = User::factory()->create();

        $r1 = Rsvp::create(['link_id' => $link->id, 'name' => 'A', 'email' => $new->email, 'response' => 'yes', 'status' => 'confirmed', 'source' => 'connect_qr']);
        EventQrConnect::create(['link_id' => $link->id, 'user_id' => $new->id, 'was_new_user' => true, 'rsvp_id' => $r1->id, 'followed' => true]);
        EventQrConnect::create(['link_id' => $link->id, 'user_id' => $existing->id, 'was_new_user' => false, 'rsvp_id' => null, 'followed' => true]);
        $stale = EventQrConnect::create(['link_id' => $link->id, 'user_id' => $old->id, 'was_new_user' => true, 'rsvp_id' => null, 'followed' => false]);
        EventQrConnect::where('id', $stale->id)->update(['created_at' => now()->subDays(400)]);

        $res = $this->actingAs($host)->get(route('user.links.visitors', $link) . '?period=30d');
        $res->assertOk();

        $qr = $res->viewData('qrConnect');
        $this->assertNotNull($qr);
        $this->assertSame(2, $qr['scans']);
        $this->assertSame(2, $qr['connected']);
        $this->assertSame(1, $qr['new_users']);
        $this->assertSame(1, $qr['existing']);
        $this->assertSame(1, $qr['rsvps']);
        $this->assertSame(2, $qr['follows']);

        // Daily funnel + conversion (Task #6689): 2 scans yesterday, 2
        // connects today → union of both days, 100% conversion for the range.
        $this->assertSame(100.0, $qr['conversion_pct']);
        $daily = $qr['daily'];
        $this->assertCount(2, $daily);
        $byDay = $daily->keyBy('d');
        $yesterday = now()->subDay()->format('Y-m-d');
        $today = now()->format('Y-m-d');
        $this->assertSame(2, $byDay[$yesterday]->scans);
        $this->assertSame(0, $byDay[$yesterday]->connects);
        $this->assertSame(0, $byDay[$today]->scans);
        $this->assertSame(2, $byDay[$today]->connects);

        // Mobile parity (Task #6687): the per-link visitors API surfaces the
        // same qr_connect block with identical numbers.
        $token = $host->createToken('test')->plainTextToken;
        $api = $this->withToken($token)->getJson('/api/v1/links/' . $link->id . '/visitors?period=30d');
        $api->assertOk();
        $apiQr = $api->json('data.qr_connect');
        $this->assertNotNull($apiQr);
        $this->assertSame(2, $apiQr['scans']);
        $this->assertSame(2, $apiQr['connected']);
        $this->assertSame(1, $apiQr['new_users']);
        $this->assertSame(1, $apiQr['existing']);
        $this->assertSame(1, $apiQr['rsvps']);
        $this->assertSame(2, $apiQr['follows']);

        // Daily funnel + conversion parity (Task #6694): the API mirrors the
        // web panel's per-day scans-vs-connects series and range conversion.
        // JSON round-trips 100.0 as int 100, so compare loosely.
        $this->assertEquals(100.0, $apiQr['conversion_pct']);
        $this->assertCount(2, $apiQr['daily']);
        $apiByDay = collect($apiQr['daily'])->keyBy('d');
        $this->assertSame(2, $apiByDay[$yesterday]['scans']);
        $this->assertSame(0, $apiByDay[$yesterday]['connects']);
        $this->assertSame(0, $apiByDay[$today]['scans']);
        $this->assertSame(2, $apiByDay[$today]['connects']);
    }

    // ---------------- printable poster (Task #6693) ----------------

    public function test_web_poster_renders_event_details_and_qr(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host, ['location' => 'The Warehouse, Berlin']);

        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($host);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $host);

        $res = $this->actingAs($host)->get(
            route('user.links.connect-qr', [$link, 'download' => 'poster'])
        );
        $res->assertOk()
            ->assertSee('Connect Party')
            ->assertSee('The Warehouse, Berlin')
            ->assertSee('Scan to RSVP')
            ->assertSee('<svg', false)
            ->assertSee('?src=connect_qr');
    }

    // ---------------- mobile API (Task #6687) ----------------

    public function test_api_connect_qr_payload_for_host(): void
    {
        $host = $this->makeHost();
        $link = $this->makeEvent($host);

        $token = $host->createToken('test')->plainTextToken;
        $res = $this->withToken($token)->getJson('/api/v1/links/' . $link->id . '/connect-qr');
        $res->assertOk();

        $data = $res->json('data');
        $this->assertSame($link->id, $data['link']['id']);
        $this->assertStringContainsString('?src=connect_qr', $data['connect_url']);
        $this->assertStringContainsString('<svg', $data['qr_svg']);
        // PNG is best-effort (needs imagick); when present it must be valid
        // base64. The SVG above is the guaranteed payload.
        if ($data['qr_png_base64'] !== null) {
            $this->assertNotFalse(base64_decode($data['qr_png_base64'], true));
        }

        // Poster fields (Task #6693): name/date/venue from ics_data.
        $this->assertSame('Connect Party', $data['event']['name']);
        $this->assertNotNull($data['event']['start_date']);
        $this->assertSame('UTC', $data['event']['timezone']);

        // Someone else's link → 404, never a leak.
        $stranger = User::factory()->create();
        $otherToken = $stranger->createToken('test')->plainTextToken;
        $this->withToken($otherToken)->getJson('/api/v1/links/' . $link->id . '/connect-qr')->assertNotFound();
    }

    public function test_api_guest_connect_rsvps_and_follows(): void
    {
        $host  = $this->makeHost();
        $link  = $this->makeEvent($host);
        $guest = User::factory()->create()->fresh();

        $token = $guest->createToken('test')->plainTextToken;
        $res = $this->withToken($token)->postJson('/api/v1/events/' . $link->alias . '/connect');
        $res->assertOk()->assertJsonPath('success', true);

        $rsvp = Rsvp::where('link_id', $link->id)->where('email', $guest->email)->first();
        $this->assertNotNull($rsvp);
        $this->assertSame('yes', $rsvp->response);
        $this->assertSame('connect_qr', $rsvp->source);

        $this->assertTrue(
            Follow::where('follower_id', $guest->id)->where('creator_id', $host->id)->exists()
        );

        $connect = EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->first();
        $this->assertNotNull($connect);
        $this->assertFalse((bool) $connect->was_new_user);
        $this->assertSame($rsvp->id, $connect->rsvp_id);

        // Repeat call stays idempotent.
        $this->withToken($token)->postJson('/api/v1/events/' . $link->alias . '/connect')->assertOk();
        $this->assertSame(1, Rsvp::where('link_id', $link->id)->where('email', $guest->email)->count());
        $this->assertSame(1, EventQrConnect::where('link_id', $link->id)->where('user_id', $guest->id)->count());
    }
}
