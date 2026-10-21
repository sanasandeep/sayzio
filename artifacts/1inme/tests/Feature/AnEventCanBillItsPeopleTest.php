<?php

namespace Tests\Feature;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\EventPerson;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Speakers, moderators, panellists and hosts on an event.
 *
 * The design question underneath all of this was whether a person on an
 * event is a reference or a copy. A reference gives reuse: edit the
 * profile once and every event follows. A copy gives accuracy: last
 * year's conference keeps billing someone the way they were billed on
 * the day.
 *
 * It is both. `contact_id` is the reference -- a speaker is a Contact,
 * which already carries name, job title, organization, socials, notes
 * and a photo, and is owned by the workspace owner across workspaces,
 * so one profile serves every event. The name/title/company columns
 * beside it are the stored billing.
 *
 * WHICH ONE RENDERS turns on whether the event has started: the live
 * profile before, the stored columns from the start onwards, and
 * nothing after. The full policy and the reasoning live in
 * ABillingFollowsTheContactTest; what is kept here is the pair that
 * says a Contact edit reaches an upcoming event and cannot reach one
 * that already happened.
 *
 * This file originally asserted the stored columns always rendered, and
 * called that "the property the whole shape exists for". The accuracy
 * half of it was right and is still enforced below -- but it was being
 * bought at the price of the reuse half, on every event an organiser
 * was still editing, which is most of them.
 */
class AnEventCanBillItsPeopleTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    /**
     * `$when` is not decoration: the start date is the only input to
     * the freeze, so a test that means "a past event" has to say so.
     * The default stays upcoming, which is what every other test here
     * wants.
     */
    private function event(User $host, array $agenda = [], string $when = '+1 day'): Link
    {
        $link = new Link([
            'user_id' => $host->id, 'type' => 'ics',
            'alias' => 'evt' . uniqid(), 'title' => 'Founder Sessions', 'is_active' => true,
        ]);
        $link->workspace_id = $host->ownedWorkspaces()->first()->id;
        $link->save();

        $start = \Illuminate\Support\Carbon::parse($when);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Founder Sessions',
            'start_date' => $start->toDateTimeString(),
            'end_date'   => $start->copy()->addHours(4)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
            'agenda'     => $agenda,
        ]);

        return $link->fresh();
    }

    private function contact(User $owner, array $attributes = []): Contact
    {
        return Contact::create(array_merge([
            'user_id'      => $owner->id,
            'display_name' => 'Priya Sharma',
            'job_title'    => 'CTO',
            'organization' => 'Acme',
            'notes'        => 'Builds payment rails.',
            'socials'      => [
                ['platform' => 'twitter',  'label' => 'X',        'url' => 'https://x.com/priya'],
                ['platform' => 'linkedin', 'label' => 'LinkedIn', 'url' => 'https://linkedin.com/in/priya'],
            ],
        ], $attributes));
    }

    private function render(Link $link): string
    {
        // The partial, not the public page: the routing stack around
        // /{alias} is not what changed here, and the partial is what both
        // public surfaces include.
        return view('common.partials.event-rich-content', [
            'link'           => $link->fresh(),
            'similarEvents'  => collect(),
            'sameHostEvents' => collect(),
            'interestCounts' => [],
            'hideHostCard'   => true,
        ])->render();
    }

    // ── The reference half: reuse ────────────────────────────────────

    public function test_a_speaker_is_built_from_a_reusable_profile(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner);

        $snapshot = EventPerson::snapshotFrom($contact);

        $this->assertSame($contact->id, $snapshot['contact_id']);
        $this->assertSame('Priya Sharma', $snapshot['name']);
        $this->assertSame('CTO', $snapshot['title']);
        $this->assertSame('Acme', $snapshot['company']);
        $this->assertSame('https://linkedin.com/in/priya', $snapshot['linkedin_url']);
        $this->assertSame('Builds payment rails.', $snapshot['bio']);
    }

    public function test_one_profile_serves_several_events(): void
    {
        // The whole point of the reference: no re-typing.
        $owner   = $this->owner();
        $contact = $this->contact($owner);

        foreach ([$this->event($owner), $this->event($owner)] as $event) {
            EventPerson::create(array_merge(
                EventPerson::snapshotFrom($contact),
                ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
            ));
        }

        $this->assertSame(2, EventPerson::where('contact_id', $contact->id)->count());
    }

    public function test_the_organiser_can_bill_someone_differently_for_one_event(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner);

        $snapshot = EventPerson::snapshotFrom($contact, ['title' => 'Keynote speaker', 'company' => null]);

        $this->assertSame('Keynote speaker', $snapshot['title']);
        $this->assertNull($snapshot['company']);
        $this->assertSame($contact->id, $snapshot['contact_id'], 'the reference must survive an override');
    }

    // ── The snapshot half: a past event does not change ──────────────

    public function test_changing_a_profile_does_not_rewrite_an_event_that_already_happened(): void
    {
        // The accuracy half, and the one a later "simplification" would
        // quietly remove. Note the date: this test is about a PAST
        // event, and until the freeze landed it was asserting the same
        // thing against an event starting TOMORROW -- so it was quietly
        // forbidding the reuse case too, under a name that said
        // otherwise.
        $owner   = $this->owner();
        $contact = $this->contact($owner);
        $event   = $this->event($owner, [], '-1 month');

        $person = EventPerson::create(array_merge(
            EventPerson::snapshotFrom($contact),
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
        ));

        $contact->update(['job_title' => 'CEO', 'organization' => 'Beta']);

        $this->assertSame('CTO at Acme', $person->fresh()->billing());
        $this->assertStringContainsString('CTO at Acme', $this->render($event));
    }

    public function test_changing_a_profile_does_reach_an_event_still_to_come(): void
    {
        // The reuse half, in the same shape, so the two sit side by side
        // and neither can be removed without the other being obviously
        // wrong.
        $owner   = $this->owner();
        $contact = $this->contact($owner);
        $event   = $this->event($owner);

        EventPerson::create(array_merge(
            EventPerson::snapshotFrom($contact),
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
        ));

        $contact->update(['job_title' => 'CEO', 'organization' => 'Beta']);

        $this->assertStringContainsString('CEO at Beta', $this->render($event));
        $this->assertStringNotContainsString('CTO at Acme', $this->render($event));
    }

    public function test_an_upcoming_event_does_not_drift_because_it_is_kept_in_step(): void
    {
        // hasDrifted() used to go true here, and refreshFromContact()
        // was the organiser's manual catch-up. Neither is needed while
        // an event is upcoming: Contact::updated mirrors the stored
        // billing, which is what lets the freeze capture what the page
        // was actually showing. Drift staying false IS the mirror
        // working.
        $owner   = $this->owner();
        $contact = $this->contact($owner);
        $event   = $this->event($owner);

        $person = EventPerson::create(array_merge(
            EventPerson::snapshotFrom($contact),
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
        ));

        $this->assertFalse($person->hasDrifted());

        $contact->update(['job_title' => 'CEO', 'organization' => 'Beta']);

        $this->assertFalse(
            $person->fresh()->load('contact')->hasDrifted(),
            'the stored billing fell behind the profile on an upcoming event'
        );
        $this->assertSame('CEO at Beta', $person->fresh()->billing());
    }

    public function test_a_past_event_can_drift_and_refreshing_it_is_deliberate(): void
    {
        // Once an event has started the mirror leaves it alone, so drift
        // is real again -- and refreshFromContact() is the only way it
        // moves, which is the point: rewriting what a finished event
        // said has to be somebody's decision.
        $owner   = $this->owner();
        $contact = $this->contact($owner);
        $event   = $this->event($owner, [], '-1 month');

        $person = EventPerson::create(array_merge(
            EventPerson::snapshotFrom($contact),
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
        ));

        $contact->update(['job_title' => 'CEO', 'organization' => 'Beta']);
        $person->refresh()->load('contact');

        $this->assertTrue($person->hasDrifted(), 'the profile moved and nothing noticed');
        $this->assertSame('CTO at Acme', $person->billing(), 'and the page is still showing the day it happened');

        $this->assertTrue($person->refreshFromContact());
        $this->assertSame('CEO at Beta', $person->fresh()->billing());
    }

    public function test_losing_the_profile_does_not_blank_the_line_up(): void
    {
        $owner   = $this->owner();
        $contact = $this->contact($owner);
        $event   = $this->event($owner);

        $person = EventPerson::create(array_merge(
            EventPerson::snapshotFrom($contact),
            ['link_id' => $event->id, 'role' => EventPerson::ROLE_SPEAKER]
        ));

        $contact->delete();
        $person = $person->fresh();

        $this->assertNotNull($person, 'deleting a CRM record deleted an event line-up entry');
        $this->assertNull($person->contact_id);
        $this->assertSame('Priya Sharma', $person->name);
        $this->assertSame('CTO at Acme', $person->billing());
    }

    // ── LinkedIn, which is the one social that is asked for by name ──

    public function test_linkedin_is_found_by_platform_or_by_url(): void
    {
        $owner = $this->owner();

        $byPlatform = $this->contact($owner, ['socials' => [
            ['platform' => 'linkedin', 'label' => 'LI', 'url' => 'https://lnkd.in/x'],
        ]]);
        $byUrl = $this->contact($owner, ['socials' => [
            ['platform' => '', 'label' => '', 'url' => 'https://www.linkedin.com/in/someone'],
        ]]);
        $neither = $this->contact($owner, ['socials' => [
            ['platform' => 'twitter', 'label' => 'X', 'url' => 'https://x.com/a'],
        ]]);

        $this->assertSame('https://lnkd.in/x', EventPerson::linkedinFrom($byPlatform));
        $this->assertSame('https://www.linkedin.com/in/someone', EventPerson::linkedinFrom($byUrl));
        $this->assertNull(EventPerson::linkedinFrom($neither));
    }

    public function test_a_person_needs_no_profile_at_all(): void
    {
        // A one-off guest must not force a CRM entry.
        $event = $this->event($owner = $this->owner());

        $person = EventPerson::create([
            'link_id' => $event->id, 'role' => EventPerson::ROLE_MODERATOR, 'name' => 'A Guest',
        ]);

        $this->assertNull($person->contact_id);
        $this->assertStringContainsString('A Guest', $this->render($event));
    }

    // ── Agenda items, panels and ordering ────────────────────────────

    public function test_a_panel_reads_moderator_first(): void
    {
        $event = $this->event($owner = $this->owner());

        foreach ([
            [EventPerson::ROLE_PANELLIST, 'Panellist One'],
            [EventPerson::ROLE_HOST,      'The Host'],
            [EventPerson::ROLE_SPEAKER,   'A Speaker'],
            [EventPerson::ROLE_MODERATOR, 'The Moderator'],
        ] as [$role, $name]) {
            EventPerson::create(['link_id' => $event->id, 'role' => $role, 'name' => $name]);
        }

        $order = EventPerson::billingOrder($event->eventPeople()->ordered()->get())
            ->pluck('name')->all();

        $this->assertSame(['The Host', 'The Moderator', 'A Speaker', 'Panellist One'], $order);
    }

    public function test_people_show_under_the_session_they_belong_to(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner, [
            ['id' => 'keynote01', 'title' => 'Opening keynote', 'time' => '10:00'],
            ['id' => 'panel00001', 'title' => 'The payments panel', 'time' => '11:00'],
        ]);

        EventPerson::create(['link_id' => $event->id, 'agenda_item_id' => 'keynote01',
            'role' => EventPerson::ROLE_SPEAKER, 'name' => 'Keynote Person']);
        EventPerson::create(['link_id' => $event->id, 'agenda_item_id' => 'panel00001',
            'role' => EventPerson::ROLE_MODERATOR, 'name' => 'Panel Moderator']);
        EventPerson::create(['link_id' => $event->id,
            'role' => EventPerson::ROLE_HOST, 'name' => 'Event Host']);

        $html = $this->render($event);

        $this->assertStringContainsString('Keynote Person', $html);
        $this->assertStringContainsString('Panel Moderator', $html);
        $this->assertStringContainsString('Event Host', $html);

        // The session person must sit inside their own session, not in the
        // other one -- which is the thing a position-based link gets wrong.
        $keynoteAt = strpos($html, 'Opening keynote');
        $panelAt   = strpos($html, 'The payments panel');
        $speakerAt = strpos($html, 'Keynote Person');

        $this->assertNotFalse($keynoteAt);
        $this->assertNotFalse($panelAt);
        $this->assertGreaterThan($keynoteAt, $speakerAt, 'the keynote speaker rendered above their own session');
        $this->assertLessThan($panelAt, $speakerAt, 'the keynote speaker rendered under the wrong session');
    }

    // ── Agenda ids, which everything above depends on ───────────────

    /**
     * normalizeAgenda is private and is a pure transform, so it is
     * exercised directly. Going through the update endpoint would mean
     * assembling a whole valid event payload to test a string function.
     */
    private function normalizeAgenda(array $items): array
    {
        $controller = new \App\Modules\User\Controllers\IcsLinkController();
        $method = new \ReflectionMethod($controller, 'normalizeAgenda');
        $method->setAccessible(true);

        return $method->invoke($controller, $items);
    }

    public function test_every_agenda_item_gets_an_id(): void
    {
        $items = $this->normalizeAgenda([
            ['title' => 'Opening keynote'],
            ['title' => 'The payments panel'],
        ]);

        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertMatchesRegularExpression('~^[a-z0-9]{1,16}$~', $item['id']);
        }

        $this->assertNotSame($items[0]['id'], $items[1]['id']);
    }

    public function test_an_id_survives_editing_the_agenda(): void
    {
        // If ids were re-minted on every save, every speaker attachment
        // would come loose the first time the organiser fixed a typo.
        $first = $this->normalizeAgenda([['title' => 'Opening keynote']]);
        $id    = $first[0]['id'];

        $second = $this->normalizeAgenda([
            ['id' => $id, 'title' => 'Opening keynote (revised)', 'time' => '10:30'],
        ]);

        $this->assertSame($id, $second[0]['id']);
        $this->assertSame('Opening keynote (revised)', $second[0]['title']);
    }

    public function test_a_duplicated_item_does_not_share_an_id(): void
    {
        // Copying a row in the editor brings its id along. Left alone,
        // the copy and the original would share one line-up.
        $items = $this->normalizeAgenda([
            ['id' => 'samesame', 'title' => 'Workshop A'],
            ['id' => 'samesame', 'title' => 'Workshop B'],
        ]);

        $this->assertSame('samesame', $items[0]['id']);
        $this->assertNotSame('samesame', $items[1]['id']);
    }

    public function test_a_junk_id_is_replaced_rather_than_trusted(): void
    {
        $items = $this->normalizeAgenda([
            ['id' => '../../etc/passwd', 'title' => 'Opening keynote'],
        ]);

        $this->assertMatchesRegularExpression('~^[a-z0-9]{1,16}$~', $items[0]['id']);
    }

    public function test_an_event_with_no_people_renders_no_line_up(): void
    {
        $html = $this->render($this->event($this->owner()));

        $this->assertStringNotContainsString('event-people-section', $html);
        $this->assertStringNotContainsString('event-people-row', $html);
    }
}
