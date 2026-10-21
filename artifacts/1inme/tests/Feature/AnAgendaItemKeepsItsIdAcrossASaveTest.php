<?php

namespace Tests\Feature;

use App\Modules\User\Models\EventPerson;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A speaker is attached to an agenda session by that session's id, so the
 * id has to mean the same session tomorrow as it does today.
 *
 * The server mints an id and keeps whatever it is sent. That is only half
 * the contract: the editor has to post the id back. It did not -- it
 * posted each item's title, times, day and description and nothing else,
 * so every save re-minted every id. Nothing noticed while nothing
 * referred to a session; the moment people are billed to one, an
 * ordinary save would orphan the entire line-up.
 *
 * So this covers both halves: that the server keeps an id it is given,
 * and that the form actually sends one.
 */
class AnAgendaItemKeepsItsIdAcrossASaveTest extends TestCase
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

    // ── The server's half ────────────────────────────────────────────

    public function test_an_id_that_is_posted_back_is_kept(): void
    {
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, [
                'agenda' => [['id' => 'keynote01', 'title' => 'Opening keynote, renamed']],
            ])
        )->assertRedirect();

        $agenda = $event->fresh('icsData')->icsData->agenda;

        $this->assertSame('keynote01', $agenda[0]['id'], 'a posted id was re-minted');
        $this->assertSame('Opening keynote, renamed', $agenda[0]['title']);
    }

    public function test_an_id_that_is_not_posted_back_is_minted_fresh(): void
    {
        // The old behaviour, pinned deliberately: no id in means a new id
        // out. This is what makes the hidden field in the form load-bearing
        // rather than decorative, and it is also what a pre-ids event needs
        // on its first save.
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, ['agenda' => [['title' => 'Opening keynote']]])
        )->assertRedirect();

        $agenda = $event->fresh('icsData')->icsData->agenda;

        $this->assertNotSame('keynote01', $agenda[0]['id']);
        $this->assertMatchesRegularExpression('~^[a-z0-9]{1,16}$~', $agenda[0]['id']);
    }

    public function test_a_billed_speaker_survives_an_ordinary_save(): void
    {
        // The whole point. Attach somebody to a session, save the event
        // again the way the editor does, and they are still on that
        // session.
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, [
                'agenda' => [['id' => 'keynote01', 'title' => 'Opening keynote']],
                'people' => [['agenda_item_id' => 'keynote01', 'role' => 'speaker', 'name' => 'Priya Sharma']],
            ])
        )->assertRedirect();

        // A second save, carrying the agenda back exactly as the form now
        // does, and carrying the line-up back with it.
        $this->actingAs($owner)->put(
            route('user.links.ics.update', $event, false),
            $this->payload($event, [
                'agenda' => [['id' => 'keynote01', 'title' => 'Opening keynote']],
                'people' => [['agenda_item_id' => 'keynote01', 'role' => 'speaker', 'name' => 'Priya Sharma']],
            ])
        )->assertRedirect();

        $person = EventPerson::where('link_id', $event->id)->first();

        $this->assertNotNull($person);
        $this->assertSame('keynote01', $person->agenda_item_id,
            'a second save orphaned a speaker from their session');
    }

    // ── The form's half ──────────────────────────────────────────────

    public function test_the_editor_posts_each_agenda_items_id(): void
    {
        // Rendered through the real route, so this also proves the Blade
        // still compiles -- the failure mode that shipped twice this week
        // was a template that passed every PHP test and rendered wrong.
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("'agenda[' + i + '][id]'", $body,
            'the agenda form has no hidden id field, so a save re-mints every session id');
    }

    public function test_the_editor_keys_agenda_rows_on_identity_not_position(): void
    {
        // Keyed on the index, Alpine reuses a row's DOM for whatever item
        // lands at that index. Remove the first of three sessions and the
        // remaining rows keep the removed row's inputs.
        $owner = $this->owner();
        $event = $this->event($owner, [['id' => 'keynote01', 'title' => 'Opening keynote']]);

        $body = $this->actingAs($owner)
            ->get(route('user.links.ics.edit', $event, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('x-for="(item, i) in agendaItems" :key="item.id"', $body);
    }
}
