<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventPerson;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Billing a speaker means picking an existing Contact, so the organiser
 * types a profile once and reuses it. This covers the two halves of that
 * on the server: the picker that finds the profile, and the save that
 * turns a pick into a line-up.
 *
 * Two guards here are worth more than the happy path.
 *
 * The picker must reach contacts saved in ANOTHER workspace. Contacts
 * carry a workspace global scope but belong to the workspace owner and
 * deliberately cross workspaces -- scope it the obvious way and the
 * picker is empty for exactly the people most worth reusing. The event
 * attendee list had this same bug earlier and shipped with it.
 *
 * And a save that does not carry a `people` key must leave the line-up
 * alone. The quick "make discoverable" toggle posts a handful of fields
 * through this same endpoint; if absence read as "remove everyone", one
 * toggle would wipe a conference line-up.
 */
class AnOrganiserCanPickSpeakersFromContactsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A signed-in owner WITH a resolved workspace. The resolve() call is
     * not decoration: a fresh factory user owns no workspace yet, so
     * anything reading ownedWorkspaces() off one gets null. Binding the
     * container instances matches the repo's other event tests; the
     * `workspace.scope` middleware rebinds both per request, so the
     * last-bound owner here cannot leak into an HTTP call made as
     * somebody else.
     */
    private function owner(): User
    {
        $u = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u->fresh();
    }

    private function contact(User $owner, string $name, array $extra = []): Contact
    {
        return Contact::create(array_merge([
            'user_id' => $owner->id, 'display_name' => $name,
            'job_title' => 'CTO', 'organization' => 'Acme',
        ], $extra));
    }

    private function event(User $host, array $agenda = []): Link
    {
        // "evt" prefix on purpose: the public alias regex rejects an alias
        // starting with `f`, so a bare Str::random() 404s about 1 in 26.
        $link = Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias' => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);

        IcsData::create([
            'link_id' => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => now()->addDay()->toDateTimeString(),
            'end_date'   => now()->addDay()->addHours(4)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
            'agenda'     => $agenda,
        ]);

        return $link->fresh();
    }

    /** The minimum a valid update needs, plus whatever the test is testing. */
    private function payload(Link $link, array $extra = []): array
    {
        $ics = $link->icsData;

        return array_merge([
            'event_name' => 'Founder Sessions',
            'start_date' => $ics->start_date->toDateTimeString(),
            'end_date'   => $ics->end_date->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
        ], $extra);
    }

    // ── The picker ───────────────────────────────────────────────────

    public function test_the_picker_finds_a_contact_and_returns_what_billing_needs(): void
    {
        $owner = $this->owner();
        $this->contact($owner, 'Priya Sharma', [
            'notes'   => 'Builds payment rails.',
            'socials' => [['platform' => 'linkedin', 'label' => 'LI', 'url' => 'https://linkedin.com/in/priya']],
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('user.contacts.picker-search', ['q' => 'Priya'], false));

        $response->assertOk();
        $response->assertJsonPath('data.0.name', 'Priya Sharma');
        $response->assertJsonPath('data.0.title', 'CTO');
        $response->assertJsonPath('data.0.company', 'Acme');
        $response->assertJsonPath('data.0.linkedin_url', 'https://linkedin.com/in/priya');
        $response->assertJsonPath('data.0.bio', 'Builds payment rails.');
    }

    public function test_the_picker_searches_name_company_and_title(): void
    {
        $owner = $this->owner();
        $this->contact($owner, 'Ravi Kumar', ['organization' => 'Globex', 'job_title' => 'Head of Design']);

        foreach (['Ravi', 'Globex', 'Design'] as $needle) {
            $this->actingAs($owner)
                ->getJson(route('user.contacts.picker-search', ['q' => $needle], false))
                ->assertOk()
                ->assertJsonPath('data.0.name', 'Ravi Kumar');
        }
    }

    public function test_the_picker_never_returns_someone_elses_contacts(): void
    {
        $mine      = $this->owner();
        $stranger  = $this->owner();
        $this->contact($stranger, 'Not Mine');

        $this->actingAs($mine)
            ->getJson(route('user.contacts.picker-search', ['q' => 'Not Mine'], false))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ── Saving a line-up ─────────────────────────────────────────────

    public function test_picking_a_contact_bills_them_on_the_event(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, 'Priya Sharma');
        $event   = $this->event($owner);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people' => [[
                'contact_id' => $contact->id,
                'role'       => EventPerson::ROLE_MODERATOR,
                'name'       => 'Priya Sharma',
                'title'      => 'CTO',
                'company'    => 'Acme',
            ]]])
        )->assertRedirect();

        $person = EventPerson::where('link_id', $event->id)->first();

        $this->assertNotNull($person);
        $this->assertSame($contact->id, $person->contact_id);
        $this->assertSame(EventPerson::ROLE_MODERATOR, $person->role);
        $this->assertSame('CTO at Acme', $person->billing());
    }

    public function test_a_save_without_a_people_key_leaves_the_line_up_alone(): void
    {
        // The guard that matters. The quick discoverability toggle posts
        // through this same endpoint without ever rendering these fields.
        $owner = $this->owner();
        $event = $this->event($owner);

        EventPerson::create([
            'link_id' => $event->id, 'role' => EventPerson::ROLE_HOST, 'name' => 'The Host',
        ]);

        $this->actingAs($owner)
            ->put(route('user.links.ics.update', $event, false), $this->payload($event))
            ->assertRedirect();

        $this->assertSame(1, EventPerson::where('link_id', $event->id)->count(),
            'an ordinary save wiped a line-up it never rendered');
    }

    public function test_an_empty_people_array_does_clear_the_line_up(): void
    {
        // Sending the key with nothing in it is an explicit "remove all",
        // which is different from not sending it.
        $owner = $this->owner();
        $event = $this->event($owner);

        EventPerson::create([
            'link_id' => $event->id, 'role' => EventPerson::ROLE_HOST, 'name' => 'The Host',
        ]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people' => []])
        )->assertRedirect();

        $this->assertSame(0, EventPerson::where('link_id', $event->id)->count());
    }

    public function test_a_contact_you_do_not_own_is_dropped_but_the_name_is_kept(): void
    {
        $owner     = $this->owner();
        $stranger  = $this->owner();
        $theirs    = $this->contact($stranger, 'Someone Else');
        $event     = $this->event($owner);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people' => [[
                'contact_id' => $theirs->id,
                'role'       => EventPerson::ROLE_SPEAKER,
                'name'       => 'Typed Name',
            ]]])
        )->assertRedirect();

        $person = EventPerson::where('link_id', $event->id)->first();

        $this->assertNotNull($person);
        $this->assertNull($person->contact_id, 'a tampered id attached someone else\'s CRM record');
        $this->assertSame('Typed Name', $person->name);
    }

    public function test_a_session_id_that_is_not_on_this_event_is_dropped(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, [
                'agenda' => [['id' => 'keynote01', 'title' => 'Opening keynote']],
                'people' => [
                    ['agenda_item_id' => 'keynote01', 'role' => 'speaker', 'name' => 'Real Session'],
                    ['agenda_item_id' => 'notathing', 'role' => 'speaker', 'name' => 'Orphan'],
                ],
            ])
        )->assertRedirect();

        $people = EventPerson::where('link_id', $event->id)->get()->keyBy('name');

        $this->assertSame('keynote01', $people['Real Session']->agenda_item_id);
        $this->assertNull($people['Orphan']->agenda_item_id, 'an orphaned session id was stored as-is');
    }

    public function test_an_unknown_role_falls_back_rather_than_storing_junk(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people' => [
                ['role' => 'supreme-overlord', 'name' => 'Odd Role'],
                ['role' => '', 'name' => 'No Role'],
                ['name' => '   '],
            ]])
        )->assertRedirect();

        $people = EventPerson::where('link_id', $event->id)->get();

        $this->assertCount(2, $people, 'a nameless entry was stored');
        $this->assertSame([EventPerson::ROLE_SPEAKER, EventPerson::ROLE_SPEAKER], $people->pluck('role')->all());
    }

    public function test_the_order_the_organiser_chose_is_kept(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people' => [
                ['role' => 'speaker', 'name' => 'First'],
                ['role' => 'speaker', 'name' => 'Second'],
                ['role' => 'speaker', 'name' => 'Third'],
            ]])
        )->assertRedirect();

        $this->assertSame(
            ['First', 'Second', 'Third'],
            EventPerson::where('link_id', $event->id)->ordered()->pluck('name')->all()
        );
    }
}
