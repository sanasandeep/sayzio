<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventPerson;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A billing resolves through the linked Contact, not the snapshot.
 *
 * This was a choice with a cost, and the cost is tested here rather
 * than only described: a past event now reports what somebody is
 * today. The alternative was a snapshot that goes stale on every
 * event an organiser is still editing, which is the commoner case.
 *
 * The snapshot has not stopped mattering. It is the whole story for
 * anybody typed in rather than picked, and it is the fallback when a
 * Contact leaves a field blank -- a Contact with no job title must
 * not erase a title the organiser typed.
 *
 * The two cross-workspace tests at the bottom are the ones most worth
 * keeping. Resolving through a relation means a filtered relation is
 * no longer an error -- it is a silent revert to the snapshot, which
 * looks like nothing is wrong.
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
     * A real Link, because event_people.link_id is a constrained foreign
     * key -- a made-up id fails the insert rather than the assertion.
     */
    private function event(User $host): Link
    {
        // "evt" prefix on purpose: the public alias regex rejects an alias
        // starting with `f`, so a bare Str::random() 404s about 1 in 26.
        return Link::create([
            'user_id' => $host->id, 'type' => 'ics',
            'alias'   => 'evt' . Str::random(8), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);
    }

    private function contact(User $owner, array $fields = []): Contact
    {
        return Contact::create(array_merge(
            ['user_id' => $owner->id, 'display_name' => 'Priya Sharma'],
            $fields
        ));
    }

    /** One billed person: a live profile on the left, a snapshot on the right. */
    private function billed(array $contactFields, array $snapshot): EventPerson
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, $contactFields);

        return EventPerson::create(array_merge([
            'link_id' => $this->event($owner)->id, 'contact_id' => $contact->id,
            'role'    => EventPerson::ROLE_SPEAKER, 'name' => 'Priya Sharma',
        ], $snapshot))->fresh();
    }

    // ── The live profile wins ────────────────────────────────────────

    public function test_a_changed_job_title_shows_on_the_event(): void
    {
        // The reason for the whole change: correct a Contact once, and
        // every event billing that person says the new thing.
        $p = $this->billed(
            ['job_title' => 'VP Engineering', 'organization' => 'Globex'],
            ['title' => 'CTO', 'company' => 'Acme']
        );

        $this->assertSame('VP Engineering at Globex', $p->billing());
    }

    public function test_the_name_follows_the_contact_too(): void
    {
        $p = $this->billed(['display_name' => 'Priya Sharma-Rao'], ['name' => 'Priya Sharma']);

        $this->assertSame('Priya Sharma-Rao', $p->displayName());
    }

    // ── The snapshot is still the fallback ───────────────────────────

    public function test_a_blank_contact_field_falls_back_to_the_snapshot(): void
    {
        // A Contact with no job title should not erase one the
        // organiser typed for this event.
        $p = $this->billed(
            ['job_title' => null, 'organization' => 'Globex'],
            ['title' => 'Keynote Speaker', 'company' => 'Acme']
        );

        $this->assertSame('Keynote Speaker at Globex', $p->billing());
    }

    public function test_somebody_typed_in_is_all_snapshot(): void
    {
        $owner = $this->owner();

        $p = EventPerson::create([
            'link_id' => $this->event($owner)->id, 'contact_id' => null,
            'role'    => EventPerson::ROLE_SPEAKER,
            'name'    => 'A One-Off Guest', 'title' => 'Founder', 'company' => 'Startup',
        ])->fresh();

        $this->assertSame('A One-Off Guest', $p->displayName());
        $this->assertSame('Founder at Startup', $p->billing());
    }

    public function test_a_name_is_never_empty(): void
    {
        // displayName is the one that cannot return null: a billing
        // without a name is not a billing.
        $owner = $this->owner();

        $p = EventPerson::create([
            'link_id' => $this->event($owner)->id, 'contact_id' => null,
            'role'    => EventPerson::ROLE_SPEAKER, 'name' => 'Only A Name',
        ])->fresh();

        $this->assertSame('Only A Name', $p->displayName());
        $this->assertNull($p->billing());
    }

    // ── The cost, recorded ───────────────────────────────────────────

    public function test_the_cost_of_this_choice_is_what_it_is(): void
    {
        // Not an endorsement -- a record. A past event reports what the
        // person is TODAY, not what they were when they spoke. If that
        // ever matters more than currency does, EventPerson::resolved()
        // documents the one condition that reverses it.
        $p = $this->billed(
            ['job_title' => 'VP Engineering', 'organization' => 'Globex'],
            ['title' => 'CTO', 'company' => 'Acme']
        );

        $this->assertSame('CTO', $p->title, 'the snapshot itself is untouched and still says what they were');
        $this->assertSame('VP Engineering at Globex', $p->billing(), 'but the page reports what they are now');
    }

    // ── A workspace filter must not quietly revert the billing ───────

    /**
     * Put the owner in a SECOND workspace of their own, which is the
     * ordinary case: contacts are owned by the person and cross
     * workspaces, events do not.
     *
     * @return array{0:EventPerson,1:Contact}
     */
    private function billedThenMovedToAnotherWorkspace(): array
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner, ['job_title' => 'VP Engineering', 'organization' => 'Globex']);

        $person = EventPerson::create([
            'link_id' => $this->event($owner)->id, 'contact_id' => $contact->id,
            'role'    => EventPerson::ROLE_SPEAKER,
            'name'    => 'Priya Sharma', 'title' => 'CTO', 'company' => 'Acme',
        ]);

        $second = Workspace::create([
            'owner_user_id' => $owner->id, 'name' => 'Second Space',
            'slug' => 'second-' . Str::random(8), 'is_personal' => false,
        ]);
        app()->instance('current_workspace', $second);

        // The control. Without this the test proves nothing: it would
        // pass just as well if the scope had never been active.
        $this->assertNull(
            Contact::find($contact->id),
            'the workspace scope is not active, so this test cannot detect it being bypassed'
        );

        return [$person, $contact];
    }

    public function test_a_contact_from_another_workspace_still_bills_live_when_lazy_loaded(): void
    {
        [$person] = $this->billedThenMovedToAnotherWorkspace();

        $this->assertSame(
            'VP Engineering at Globex',
            EventPerson::findOrFail($person->id)->billing(),
            'the workspace scope filtered the relation and the billing silently reverted to the snapshot'
        );
    }

    public function test_a_contact_from_another_workspace_still_bills_live_when_eager_loaded(): void
    {
        // The #516 shape: an eager load runs its own query with its own
        // scopes, so unscoping the parent does nothing for it.
        [$person] = $this->billedThenMovedToAnotherWorkspace();

        $loaded = EventPerson::with('contact')->findOrFail($person->id);

        $this->assertTrue($loaded->relationLoaded('contact'), 'the eager load did not run');
        $this->assertNotNull($loaded->getRelation('contact'), 'the eager load came back empty');
        $this->assertSame('VP Engineering at Globex', $loaded->billing());
    }
}
