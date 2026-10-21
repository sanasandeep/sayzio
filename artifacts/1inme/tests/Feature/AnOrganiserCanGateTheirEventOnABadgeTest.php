<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who may register is not the organiser's to decide.
 *
 * An earlier pass put a "Required to register" select in the event editor
 * because required_badge_id existed and nothing set it. That was the wrong
 * reading: badges are granted by admin or through the badge request flow,
 * so a gate is something Sayzio applies to an event, not a per-event
 * dropdown.
 *
 * Removing the field leaves a trap, and most of this file is about it.
 * icsPayload() built required_badge_id as `$v[...] ?? null` and the result
 * goes to IcsData::update(). With no field posting the value, every
 * ordinary save -- an organiser fixing a typo in the description --
 * would have cleared a gate they cannot see and did not set. The key is
 * now omitted rather than defaulted, so update() leaves that column alone.
 *
 * What the organiser does still choose is award_badge_id: a badge handed
 * to everyone who turns up and scans in.
 */
class AnOrganiserCanGateTheirEventOnABadgeTest extends TestCase
{
    use RefreshDatabase;

    private function organiser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function event(User $user): Link
    {
        $link = new Link([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Members Dinner',
            'is_active' => true,
        ]);
        $link->workspace_id = $user->ownedWorkspaces()->first()->id;
        $link->save();

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $link->title,
            'start_date' => '2030-01-01 18:00:00',
            'end_date'   => '2030-01-01 20:00:00',
            'timezone'   => 'UTC',
        ]);

        return $link->fresh();
    }

    private function badge(string $name): AccountBadge
    {
        return AccountBadge::create(['name' => $name, 'color' => '#3b82f6']);
    }

    private function payload(Link $link, array $extra = []): array
    {
        return array_merge([
            'title'      => $link->title,
            'event_name' => $link->title,
            'start_date' => '2030-01-01T18:00',
            'end_date'   => '2030-01-01T20:00',
            'timezone'   => 'UTC',
        ], $extra);
    }

    public function test_the_editor_does_not_offer_to_gate_registration(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $this->badge('Founding Member');

        $response = $this->actingAs($user)->get(route('user.links.ics.edit', $link));

        $response->assertOk();
        $response->assertDontSee('required_badge_id', false);
        $response->assertDontSee('Required to register');
    }

    public function test_an_ordinary_save_does_not_clear_a_gate_the_organiser_cannot_see(): void
    {
        // The whole point of this file. An admin gates the event; the
        // organiser then edits something unrelated. The gate must survive.
        $user = $this->organiser();
        $link = $this->event($user);
        $gate = $this->badge('Founding Member');

        $link->icsData->update(['required_badge_id' => $gate->id]);

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['description' => 'Fixed a typo.'])
        );

        $this->assertSame(
            $gate->id,
            (int) $link->fresh()->icsData->required_badge_id,
            'An unrelated save wiped the gate.'
        );
    }

    public function test_the_editor_still_offers_an_attendance_badge(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $this->badge('Attended 2030');

        $response = $this->actingAs($user)->get(route('user.links.ics.edit', $link));

        $response->assertOk();
        $response->assertSee('Attended 2030');
        $response->assertSee('award_badge_id', false);
        $response->assertSee('Attendance badge');
    }

    public function test_an_attendance_badge_reaches_the_column_check_in_reads(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $prize = $this->badge('Attended 2030');

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['award_badge_id' => $prize->id])
        );

        $this->assertSame($prize->id, (int) $link->fresh()->icsData->award_badge_id);
    }

    public function test_an_attendance_badge_can_be_taken_back_off(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $prize = $this->badge('Attended 2030');

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['award_badge_id' => $prize->id])
        );
        $this->assertNotNull($link->fresh()->icsData->award_badge_id);

        // "Nothing awarded" posts an empty string rather than omitting the
        // key, which is the difference between clearing it and this field
        // behaving like the gate above.
        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['award_badge_id' => ''])
        );

        $this->assertNull($link->fresh()->icsData->award_badge_id);
    }

    public function test_an_editor_with_no_badges_says_so_instead_of_an_empty_menu(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);

        $response = $this->actingAs($user)->get(route('user.links.ics.edit', $link));

        $response->assertOk();
        $response->assertSee('No badges exist yet');
    }
}
