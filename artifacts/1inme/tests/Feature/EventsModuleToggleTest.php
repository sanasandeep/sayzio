<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EventsModule;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #6726 — platform-wide Events module switch.
 *
 * Toggle OFF: public /events directory, individual event pages, RSVP,
 * connect-QR, interest, ticket and creator @handle/events routes 404;
 * events API endpoints 404; user event creation routes 404; Events nav
 * entries disappear from the user sidebar and the marketing header.
 * Toggle ON (the default): everything works exactly as before.
 */
class EventsModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    private function off(): void
    {
        AppSetting::put(EventsModule::KEY, false);
    }

    /** Link has no factory — create directly + bind the workspace. */
    private function makeEvent(User $host): Link
    {
        $ws = $host->ownedWorkspaces()->first();
        $link = new Link([
            'user_id'   => $host->id,
            'type'      => 'ics',
            'alias'     => 'evm' . uniqid(),
            'title'     => 'Toggle Party',
            'is_active' => true,
            'settings'  => ['rsvp_enabled' => true],
        ]);
        $link->workspace_id = $ws->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ]);

        return $link->fresh(['icsData', 'user']);
    }

    // ---------------- default (on) ----------------

    public function test_default_is_enabled_and_events_surfaces_work(): void
    {
        $this->assertTrue(EventsModule::enabled());

        $host = User::factory()->create()->fresh();
        $link = $this->makeEvent($host);

        $this->get('/events')->assertOk();
        $this->get('/' . $link->alias)->assertOk();
        $this->getJson('/api/v1/events')->assertOk();
    }

    // ---------------- public web surfaces off ----------------

    public function test_public_event_routes_404_when_off(): void
    {
        $host = User::factory()->create()->fresh();
        $host->forceFill(['handle' => 'evmhost' . rand(1000, 9999), 'profile_published' => true])->save();
        $link = $this->makeEvent($host);

        $this->off();

        $this->get('/events')->assertNotFound();
        $this->get('/' . $link->alias)->assertNotFound();
        $this->get('/' . $link->alias . '/rsvp')->assertNotFound();
        $this->post('/' . $link->alias . '/interest')->assertNotFound();
        $this->get('/' . $link->alias . '/event-extras')->assertNotFound();
        $this->post('/' . $link->alias . '/connect-qr/send', ['identifier' => 'a@b.c', 'type' => 'email'])->assertNotFound();
        $this->post('/' . $link->alias . '/tickets/buy')->assertNotFound();
        $this->get('/@' . $host->handle . '/events')->assertNotFound();
    }

    // ---------------- branded unavailable page (Task #6728) ----------------

    public function test_html_visitors_get_branded_events_unavailable_page_when_off(): void
    {
        $host = User::factory()->create()->fresh();
        $link = $this->makeEvent($host);

        $this->off();

        // Routed events surface (middleware) — branded page, still 404 status.
        $dir = $this->get('/events');
        $dir->assertNotFound();
        $this->assertStringContainsString("Events aren't available right now", $dir->getContent());
        $this->assertStringContainsString(url('/'), $dir->getContent());

        // Catch-all event page (in-controller guard) — same branded page.
        $page = $this->get('/' . $link->alias);
        $page->assertNotFound();
        $this->assertStringContainsString("Events aren't available right now", $page->getContent());

        // JSON callers keep the plain 404 (no branded HTML).
        $json = $this->getJson('/api/v1/events');
        $json->assertNotFound();
        $this->assertStringNotContainsString("Events aren't available right now", $json->getContent());
    }

    // ---------------- API parity ----------------

    public function test_api_event_endpoints_404_when_off(): void
    {
        $host = User::factory()->create()->fresh();
        $link = $this->makeEvent($host);

        $this->off();

        $this->getJson('/api/v1/events')->assertNotFound();
        $this->getJson('/api/v1/events/' . $link->alias)->assertNotFound();
    }

    public function test_generic_api_link_create_rejects_event_types_when_off(): void
    {
        $user = User::factory()->create()->fresh();
        $this->withToken($user->createToken('test')->plainTextToken);

        $this->off();

        $this->postJson('/api/v1/links', [
            'type'     => 'event',
            'title'    => 'Sneaky Event',
            'settings' => ['event' => ['start' => '2030-01-01 18:00:00']],
        ])->assertNotFound();

        $this->postJson('/api/v1/links', ['type' => 'ics', 'title' => 'Dup Event'])
            ->assertNotFound();

        // Non-event link types stay unaffected by the toggle.
        $this->postJson('/api/v1/links', [
            'type'        => 'short',
            'destination' => 'https://example.com',
        ])->assertSuccessful();
    }

    public function test_rsvp_management_routes_404_when_off(): void
    {
        $host = User::factory()->create()->fresh();
        $link = $this->makeEvent($host);

        $this->off();

        // Owner RSVP guest lists (web + API) and the biolink RSVP-block submit.
        // Web first — withToken() leaks as a default header into later requests.
        $this->actingAs($host)->get(route('user.links.rsvps.index', $link))->assertNotFound();
        $this->actingAs($host)->get(route('user.links.connect-qr', $link))->assertNotFound();

        $this->withToken($host->createToken('test')->plainTextToken);
        $this->getJson('/api/v1/links/' . $link->id . '/rsvps')->assertNotFound();
        $this->postJson('/api/v1/biolinks/anyalias/blocks/1/rsvp', [])->assertNotFound();
    }

    // ---------------- user creation/management off ----------------

    public function test_event_creation_routes_404_when_off(): void
    {
        $user = User::factory()->create()->fresh();
        $this->off();

        $this->actingAs($user)->get(route('user.links.ics.create'))->assertNotFound();
        $this->actingAs($user)->post(route('user.links.ics.store'), [])->assertNotFound();
        $this->actingAs($user)->get(route('user.events.index'))->assertNotFound();
    }

    public function test_create_link_chooser_hides_and_rejects_event_type_when_off(): void
    {
        $user = User::factory()->create()->fresh();

        $on = $this->actingAs($user)->get(route('user.links.create'));
        $on->assertOk();
        $this->assertStringContainsString('value="ics"', $on->getContent());

        $this->off();

        $off = $this->actingAs($user)->get(route('user.links.create'));
        $off->assertOk();
        $this->assertStringNotContainsString('value="ics"', $off->getContent());

        // Hand-crafted type=ics submit must 404, not dead-end on a redirect.
        $this->actingAs($user)
            ->post(route('user.links.choose-type'), ['type' => 'ics'])
            ->assertNotFound();
    }

    public function test_event_creation_routes_work_when_on(): void
    {
        $user = User::factory()->create()->fresh();
        $this->actingAs($user)->get(route('user.links.ics.create'))->assertOk();
    }

    // ---------------- navigation ----------------

    public function test_sidebar_hides_events_entry_when_off(): void
    {
        $user = User::factory()->create()->fresh();

        $on = $this->actingAs($user)->followingRedirects()->get(route('user.dashboard'));
        $on->assertOk();
        $this->assertStringContainsString(route('user.events.index'), $on->getContent());

        $this->off();

        $off = $this->actingAs($user)->followingRedirects()->get(route('user.dashboard'));
        $off->assertOk();
        $this->assertStringNotContainsString(route('user.events.index'), $off->getContent());
    }

    public function test_marketing_header_hides_events_links_when_off(): void
    {
        $on = $this->get('/features');
        $on->assertOk();
        $this->assertStringContainsString(route('events.index'), $on->getContent());

        $this->off();

        $off = $this->get('/features');
        $off->assertOk();
        $this->assertStringNotContainsString(route('events.index'), $off->getContent());
    }

    // ---------------- admin toggle round-trip ----------------

    public function test_admin_marketing_settings_saves_the_toggle(): void
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        $admin = \App\Modules\Admin\Models\Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), [
                // events_module_enabled intentionally absent → off
                'home_design'         => 'classic',
                'events_band_enabled' => '1',
            ])->assertRedirect();

        $this->assertFalse(EventsModule::enabled());

        $this->actingAs($admin, 'admin')
            ->put(route('admin.marketing-settings.update'), [
                'home_design'            => 'classic',
                'events_module_enabled'  => '1',
            ])->assertRedirect();

        $this->assertTrue(EventsModule::enabled());
    }

    // ---------------- no data loss ----------------

    public function test_toggling_off_and_on_never_touches_event_data(): void
    {
        $host = User::factory()->create()->fresh();
        $link = $this->makeEvent($host);

        $this->off();
        AppSetting::put(EventsModule::KEY, true);

        $this->assertDatabaseHas('links', ['id' => $link->id, 'type' => 'ics']);
        $this->get('/' . $link->alias)->assertOk();
    }
}
