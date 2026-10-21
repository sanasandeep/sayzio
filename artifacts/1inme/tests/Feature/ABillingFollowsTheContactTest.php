<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventPerson;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * How a billed person reads, which turns on one thing: whether the
 * event has started.
 *
 * BEFORE it starts, the live Contact wins. Correct a job title once and
 * every event still being planned says the new thing.
 *
 * ONCE it starts, the stored columns win and nothing moves again. What
 * attendees read is what the event said on the day.
 *
 * Those two are easy to state and were NOT easy to make true together,
 * because a freeze is only honest if the thing it freezes is current.
 * Left alone, the columns hold whatever the profile said the day the
 * speaker was ADDED -- so the page would follow the profile right up to
 * the start and then jump back to a value no one ever saw, at the one
 * moment the whole point was that nothing changes. The mirror
 * (Contact::updated -> EventPerson::syncSnapshotsForContact) is what
 * closes that, and the tests under "the mirror" are the ones that would
 * catch its removal.
 */
class ABillingFollowsTheContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A signed-in owner WITH a resolved workspace. The resolve() call is
     * not decoration: a fresh factory user owns no workspace yet, so
     * the Contact `creating` hook has nothing to fill workspace_id from
     * and these rows would land NULL -- which is the bug #515 fixed,
     * not the state this test should be asserting against.
     */
    private function owner(): User
    {
        $u  = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);

        return $u->fresh();
    }

    /**
     * A real Link with real dates. Both halves matter: link_id is a
     * constrained foreign key, so a made-up id fails the insert rather
     * than the assertion; and the start date is the whole input to the
     * freeze, so leaving it unset would test neither branch.
     */
    private function event(User $host, string $when = '+1 day'): Link
    {
        // "evt" prefix on purpose: the public alias regex rejects an alias
        // starting with `f`, so a bare Str::random() 404s about 1 in 26.
        $link = Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias'   => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);

        $start = Carbon::parse($when);

        IcsData::create([
            'link_id'    => $link->id, 'event_name' => 'Founder Sessions',
            'start_date' => $start->toDateTimeString(),
            'end_date'   => $start->copy()->addHours(4)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
        ]);

        return $link->fresh();
    }

    private function contact(User $owner, array $fields = []): Contact
    {
        return Contact::create(array_merge(
            ['user_id' => $owner->id, 'display_name' => 'Priya Sharma'],
            $fields
        ));
    }

    private function person(Link $event, array $attributes): EventPerson
    {
        $p = EventPerson::create(array_merge(
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER],
            $attributes
        ));

        return $p->fresh();
    }

    /** Move an event's start without going through the editor. */
    private function startEvent(Link $event, string $when = '-2 hours'): void
    {
        $event->icsData->update(['start_date' => Carbon::parse($when)->toDateTimeString()]);
        $event->unsetRelation('icsData');
    }

    // ── Before the event: the live profile wins ──────────────────────

    /**
     * NOTE ON WHAT THESE TWO CAN AND CANNOT PROVE.
     *
     * The obvious way to write them -- create the person, edit the
     * Contact, assert the new value -- proves nothing now that the
     * mirror exists, because the edit updates the stored columns too.
     * The assertion would pass against a resolved() that never looked
     * at the Contact at all.
     *
     * So the divergence is created WITHOUT a Contact edit: the stored
     * columns are written directly to something the Contact does not
     * say. That is the only arrangement in which "the live profile
     * wins" is falsifiable.
     */
    public function test_the_live_profile_wins_over_what_is_stored(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'VP Engineering', 'organization' => 'Globex']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $this->assertSame('VP Engineering at Globex', $person->billing());
        $this->assertSame('CTO', $person->title, 'and the stored column is genuinely saying something else');
    }

    public function test_the_name_comes_from_the_profile_too(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['display_name' => 'Priya Sharma-Rao']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
        ]);

        $this->assertSame('Priya Sharma-Rao', $person->displayName());
    }

    public function test_correcting_a_profile_updates_an_upcoming_event(): void
    {
        // The user-facing story, end to end: one edit in the CRM, and
        // the event says the new thing. Both halves get there -- the
        // mirror moves the columns, resolved() reads the profile -- and
        // this test deliberately does not care which, because the
        // organiser does not either.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO', 'organization' => 'Acme']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $contact->update(['job_title' => 'VP Engineering', 'organization' => 'Globex']);

        $this->assertSame('VP Engineering at Globex', $person->fresh()->billing());
    }

    public function test_a_blank_contact_field_falls_back_to_the_stored_one(): void
    {
        // A Contact with no job title must not erase a title that is
        // already on the event.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => null, 'organization' => 'Globex']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'Keynote Speaker', 'company' => 'Acme',
        ]);

        $this->assertSame('Keynote Speaker at Globex', $person->billing());
    }

    public function test_an_event_with_no_ics_row_at_all_follows_the_profile(): void
    {
        // The only way eventHasStarted() sees no date. It is not a
        // half-filled date field -- ics_data.start_date is NOT NULL --
        // it is a link with no ics row behind it. A half-built event has
        // no attendees to mislead, so following the profile is the right
        // way to fail.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'VP Engineering']);

        $link = Link::create([
            'user_id' => $owner->id, 'type' => 'ics',
            'alias'   => 'evt' . Str::random(8), 'title' => 'Untitled', 'is_active' => false,
        ]);

        $person = $this->person($link->fresh(), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma', 'title' => 'CTO',
        ]);

        $this->assertFalse($person->eventHasStarted());
        $this->assertSame('VP Engineering', $person->billing());
    }

    // ── Once it starts: frozen ───────────────────────────────────────

    public function test_a_started_event_does_not_follow_the_profile(): void
    {
        // The property the snapshot column exists for, and the one a
        // later "simplification" would quietly remove.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO', 'organization' => 'Acme']);
        $event   = $this->event($owner);
        $person  = $this->person($event, [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $this->startEvent($event);
        $contact->update(['display_name' => 'Someone Else', 'job_title' => 'CEO', 'organization' => 'Beta']);

        $frozen = EventPerson::findOrFail($person->id);

        $this->assertTrue($frozen->eventHasStarted());
        $this->assertSame('CTO at Acme', $frozen->billing(), 'a profile edit rewrote an event that already happened');
        $this->assertSame('Priya Sharma', $frozen->displayName());
    }

    public function test_the_cut_is_the_start_not_the_end(): void
    {
        // An event underway is already being read by the people in the
        // room. Freeze at the start, not when it finishes.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO']);
        $event   = $this->event($owner, '-1 hour');   // started, four-hour event
        $person  = $this->person($event, [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma', 'title' => 'CTO',
        ]);

        $contact->update(['job_title' => 'CEO']);

        $this->assertSame('CTO', EventPerson::findOrFail($person->id)->billing());
    }

    public function test_an_organiser_can_still_correct_a_started_event(): void
    {
        // The freeze must not lock the organiser out. For a started
        // event the columns ARE what renders, so an editor save lands
        // immediately -- which is the escape hatch for a typo noticed
        // on the day.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO']);
        $event   = $this->event($owner, '-2 hours');
        $person  = $this->person($event, [
            'contact_id' => $contact->id, 'name' => 'Pria Sharma', 'title' => 'CTO',
        ]);

        $person->update(['name' => 'Priya Sharma']);

        $this->assertSame('Priya Sharma', EventPerson::findOrFail($person->id)->displayName());
    }

    // ── Anyone typed in rather than picked ───────────────────────────

    public function test_somebody_typed_in_reads_from_the_event_only(): void
    {
        $owner  = $this->owner();
        $person = $this->person($this->event($owner), [
            'contact_id' => null,
            'name'       => 'A One-Off Guest', 'title' => 'Founder', 'company' => 'Startup',
        ]);

        $this->assertSame('A One-Off Guest', $person->displayName());
        $this->assertSame('Founder at Startup', $person->billing());
        $this->assertFalse($person->followsContact());
    }

    public function test_a_name_is_never_empty(): void
    {
        // displayName is the one that cannot return null: a billing
        // without a name is not a billing.
        $owner  = $this->owner();
        $person = $this->person($this->event($owner), ['contact_id' => null, 'name' => 'Only A Name']);

        $this->assertSame('Only A Name', $person->displayName());
        $this->assertNull($person->billing());
    }

    // ── The mirror: what makes the freeze honest ─────────────────────

    public function test_the_stored_billing_is_kept_current_until_the_event_starts(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO', 'organization' => 'Acme']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $contact->update(['job_title' => 'VP Engineering', 'organization' => 'Globex']);

        $stored = $person->fresh();

        $this->assertSame('VP Engineering', $stored->title, 'the stored billing went stale behind the live one');
        $this->assertSame('Globex', $stored->company);
    }

    public function test_an_event_freezes_to_what_it_was_showing_not_to_what_it_once_showed(): void
    {
        // The test that justifies the mirror existing. Without it this
        // event would show "VP Engineering at Globex" for the three
        // weeks before it ran and then switch to "CTO at Acme" the
        // moment it began.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO', 'organization' => 'Acme']);
        $event   = $this->event($owner);
        $person  = $this->person($event, [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $contact->update(['job_title' => 'VP Engineering', 'organization' => 'Globex']);
        $showingBefore = EventPerson::findOrFail($person->id)->billing();

        $this->startEvent($event);

        $this->assertSame(
            $showingBefore,
            EventPerson::findOrFail($person->id)->billing(),
            'the billing changed at the moment the event started'
        );
    }

    public function test_the_mirror_does_not_touch_an_event_that_has_started(): void
    {
        // The exclusion IS the guarantee. If the mirror reached started
        // events, the freeze would be decorative.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO', 'organization' => 'Acme']);
        $event   = $this->event($owner, '-3 days');
        $person  = $this->person($event, [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        $contact->update(['job_title' => 'CEO', 'organization' => 'Beta']);

        $stored = $person->fresh();

        $this->assertSame('CTO', $stored->title, 'the mirror overwrote a billing of record');
        $this->assertSame('Acme', $stored->company);
    }

    public function test_the_mirror_reaches_an_event_in_another_workspace(): void
    {
        // Contacts are owned by the person and cross workspaces; events
        // do not. A scoped subquery would skip exactly the events most
        // likely to reuse a profile -- #516 again, in a whereHas.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma', 'title' => 'CTO',
        ]);

        app()->instance('current_workspace', $this->secondWorkspace($owner));

        $contact->update(['job_title' => 'VP Engineering']);

        $this->assertSame('VP Engineering', $person->fresh()->title);
    }

    public function test_an_override_on_a_linked_upcoming_person_does_not_survive(): void
    {
        // Recorded, not endorsed. Typing a one-off title against a
        // linked person on an upcoming event buys nothing: it does not
        // render, and the next profile edit overwrites it. The editor
        // says so via followsContact(); the way to bill someone
        // differently for one event is to unlink them.
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'CTO']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma', 'title' => 'Keynote Speaker',
        ]);

        $this->assertSame('CTO', $person->billing(), 'the override does not render while the profile is linked');
        $this->assertTrue($person->followsContact());

        $contact->update(['job_title' => 'VP Engineering']);

        $this->assertSame('VP Engineering', $person->fresh()->title, 'and it is gone on the next profile edit');
    }

    // ── A workspace filter must not quietly revert a billing ─────────

    private function secondWorkspace(User $owner): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id, 'name' => 'Second Space',
            'slug'          => 'second-' . Str::random(8), 'is_personal' => false,
        ]);
    }

    /**
     * Put the owner in a second workspace of their own, which is the
     * ordinary case.
     *
     * THIS CONTROL WAS INVERTED, AND THAT IS THE INTERESTING PART.
     *
     * It used to assert that a plain Contact::find() came back NULL
     * here -- proving the workspace scope was live, so that these two
     * tests could not pass against code that never had the bug they
     * describe. When the scope came off Contact entirely (contacts are
     * account-wide), that assertion failed, which is exactly what a
     * control is for: it caught a change to its own premise instead of
     * quietly going along with it.
     *
     * So it now asserts the new invariant rather than the old one. If
     * anybody re-scopes Contact to the workspace, this line fails
     * loudly -- which is better than these two tests silently reverting
     * to green because the billing fell back to the stored snapshot
     * and read the same either way.
     */
    private function billedThenMovedToAnotherWorkspace(): EventPerson
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'VP Engineering', 'organization' => 'Globex']);
        $person  = $this->person($this->event($owner), [
            'contact_id' => $contact->id, 'name' => 'Priya Sharma',
            'title'      => 'CTO', 'company' => 'Acme',
        ]);

        app()->instance('current_workspace', $this->secondWorkspace($owner));

        $this->assertNotNull(
            Contact::find($contact->id),
            'contacts are account-wide: a workspace filter is back on Contact and these tests no longer mean what they say'
        );

        return $person;
    }

    public function test_a_contact_from_another_workspace_still_resolves_when_lazy_loaded(): void
    {
        $person = $this->billedThenMovedToAnotherWorkspace();

        $this->assertSame(
            'VP Engineering at Globex',
            EventPerson::findOrFail($person->id)->billing(),
            'the workspace scope filtered the relation and the billing silently reverted'
        );
    }

    public function test_a_contact_from_another_workspace_still_resolves_when_eager_loaded(): void
    {
        // The #516 shape: an eager load runs its own query with its own
        // scopes, so unscoping the parent does nothing for it.
        $person = $this->billedThenMovedToAnotherWorkspace();

        $loaded = EventPerson::with('contact')->findOrFail($person->id);

        $this->assertTrue($loaded->relationLoaded('contact'), 'the eager load did not run');
        $this->assertNotNull($loaded->getRelation('contact'), 'the eager load came back empty');
        $this->assertSame('VP Engineering at Globex', $loaded->billing());
    }
}
