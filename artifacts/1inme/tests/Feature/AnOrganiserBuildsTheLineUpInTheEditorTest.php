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
 * The editor half of #510: picking speakers and saving them.
 *
 * The case worth the most here is removing the LAST person. #510
 * guards syncEventPeople on the `people` key being present, so that a
 * settings-only save from another screen cannot wipe a line-up it
 * never rendered. But an empty line-up posts no people[] keys at all,
 * so that same guard would have silently refused the removal -- the
 * guard working against the screen it was written to protect. Hence
 * `people_present`, and hence a test for it.
 */
class AnOrganiserBuildsTheLineUpInTheEditorTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u->fresh();
    }

    private function event(User $host, array $agenda = []): Link
    {
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

    // ── The guard that nearly bit ─────────────────────────────────────

    public function test_removing_the_last_person_actually_removes_them(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner);

        EventPerson::create(['link_id' => $event->id, 'role' => 'host', 'name' => 'The Only One']);

        // Exactly what the editor posts with an empty line-up: the
        // marker, and no people[] keys whatsoever.
        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['people_present' => '1'])
        )->assertRedirect();

        $this->assertSame(0, EventPerson::where('link_id', $event->id)->count(),
            'the organiser removed the last person and the save ignored it');
    }

    public function test_a_save_from_another_screen_still_leaves_the_line_up_alone(): void
    {
        // The guard must keep doing its original job: neither key sent.
        $owner = $this->owner();
        $event = $this->event($owner);

        EventPerson::create(['link_id' => $event->id, 'role' => 'host', 'name' => 'The Host']);

        $this->actingAs($owner)
            ->put(route('user.links.ics.update', $event, false), $this->payload($event))
            ->assertRedirect();

        $this->assertSame(1, EventPerson::where('link_id', $event->id)->count());
    }

    // ── The editor surface ────────────────────────────────────────────

    public function test_the_editor_renders_the_existing_line_up(): void
    {
        $owner   = $this->owner();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Priya Sharma', 'job_title' => 'CTO', 'organization' => 'Acme']);
        $event   = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        EventPerson::create([
            'link_id' => $event->id, 'contact_id' => $contact->id, 'agenda_item_id' => 'keynote01',
            'role' => 'speaker', 'name' => 'Priya Sharma', 'title' => 'CTO', 'company' => 'Acme',
        ]);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))->assertOk()->getContent();

        $this->assertStringContainsString('Priya Sharma', $body);
        $this->assertStringContainsString('keynote01', $body);
        $this->assertStringContainsString('people_present', $body);
        // The picker has to be reachable from both levels.
        $this->assertStringContainsString("openPicker('')", $body);
        $this->assertStringContainsString('openPicker(item.id)', $body);
    }

    public function test_rows_are_keyed_on_identity_not_position(): void
    {
        // Keyed on the index, removing someone re-binds the row below
        // them to the removed row's <select>. Same bug as the agenda in
        // #511, same fix.
        $owner = $this->owner();
        $event = $this->event($owner);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))->assertOk()->getContent();

        $this->assertStringContainsString(':key="p.uid"', $body);
        $this->assertStringNotContainsString(':key="p"', str_replace(':key="p.uid"', '', $body));
    }

    public function test_every_cell_in_a_result_row_states_its_position(): void
    {
        // Found by opening the deployed picker, not by a test. The
        // action had grid-row set and no grid-column, so CSS grid auto
        // placement claimed column 1 for it and pushed the contact's
        // name into column 2: the Add button rendered to the LEFT of
        // the person it belonged to.
        //
        // This is a presence check and nothing more. It cannot see a
        // layout; it only stops the explicit placement being deleted
        // again. The layout was checked by looking at it.
        $owner = $this->owner();
        $event = $this->event($owner);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))->assertOk()->getContent();

        $this->assertStringContainsString('.lineup-result-action { grid-column: 2;', $body);
        $this->assertStringContainsString('.lineup-result .lineup-name    { grid-column: 1;', $body);
    }

    public function test_the_uid_is_never_posted(): void
    {
        // It exists to key a loop. It is not part of the payload, and
        // the server has no column for it.
        $owner = $this->owner();
        $event = $this->event($owner);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))->assertOk()->getContent();

        $this->assertStringNotContainsString("][uid]", $body);
    }

    // ── Saving what the picker produces ───────────────────────────────

    public function test_a_picked_contact_and_a_typed_guest_both_save(): void
    {
        $owner   = $this->owner();
        $contact = Contact::create(['user_id' => $owner->id, 'display_name' => 'Priya Sharma', 'job_title' => 'CTO', 'organization' => 'Acme']);
        $event   = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, [
                'agenda'         => [['id' => 'keynote01', 'title' => 'Opening keynote']],
                'people_present' => '1',
                'people' => [
                    ['contact_id' => $contact->id, 'agenda_item_id' => 'keynote01', 'role' => 'speaker', 'name' => 'Priya Sharma', 'title' => 'CTO', 'company' => 'Acme'],
                    ['contact_id' => '', 'agenda_item_id' => '', 'role' => 'host', 'name' => 'A One-Off Guest'],
                ],
            ])
        )->assertRedirect();

        $people = EventPerson::where('link_id', $event->id)->get()->keyBy('name');

        $this->assertSame($contact->id, $people['Priya Sharma']->contact_id);
        $this->assertSame('keynote01', $people['Priya Sharma']->agenda_item_id);

        $this->assertNull($people['A One-Off Guest']->contact_id);
        $this->assertNull($people['A One-Off Guest']->agenda_item_id);
        $this->assertSame('host', $people['A One-Off Guest']->role);
    }
}
