<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * required_badge_id and award_badge_id have been on ics_data, validated by
 * IcsLinkController and enforced at registration and at the door for a long
 * time -- with no input anywhere that could set them. Invite-only was a mode
 * the code supported and the product could not switch on.
 *
 * These tests pin the wiring rather than the gate itself: the gate already
 * has its own enforcement in RedirectController and EventCheckinController.
 * What was missing, and what can silently regress, is whether a value an
 * organiser picks actually survives the round trip to the column the gate
 * reads.
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

    /** The minimum the update route insists on, so a save is about badges. */
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

    public function test_the_editor_offers_the_badges_that_exist(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $this->badge('Founding Member');

        $response = $this->actingAs($user)->get(route('user.links.ics.edit', $link));

        $response->assertOk();
        $response->assertSee('Founding Member');
        $response->assertSee('required_badge_id', false);
        $response->assertSee('award_badge_id', false);
    }

    public function test_a_chosen_badge_reaches_the_column_the_gate_reads(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $gate = $this->badge('Founding Member');
        $prize = $this->badge('Attended 2030');

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, [
                'required_badge_id' => $gate->id,
                'award_badge_id'    => $prize->id,
            ])
        );

        $ics = $link->fresh()->icsData;
        $this->assertSame($gate->id, (int) $ics->required_badge_id);
        $this->assertSame($prize->id, (int) $ics->award_badge_id);
    }

    public function test_an_open_event_stays_open(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['required_badge_id' => '', 'award_badge_id' => ''])
        );

        $ics = $link->fresh()->icsData;
        $this->assertNull($ics->required_badge_id);
        $this->assertNull($ics->award_badge_id);
    }

    public function test_a_gate_can_be_lifted_again(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);
        $gate = $this->badge('Founding Member');

        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['required_badge_id' => $gate->id])
        );
        $this->assertNotNull($link->fresh()->icsData->required_badge_id);

        // Choosing "Anyone can register" posts an empty string, not a
        // missing key. If that were ever treated as "leave it alone", an
        // organiser could not reopen their own event.
        $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['required_badge_id' => ''])
        );

        $this->assertNull($link->fresh()->icsData->required_badge_id);
    }

    public function test_a_badge_that_does_not_exist_is_refused(): void
    {
        $user = $this->organiser();
        $link = $this->event($user);

        $response = $this->actingAs($user)->put(
            route('user.links.ics.update', $link),
            $this->payload($link, ['required_badge_id' => 999999])
        );

        $response->assertSessionHasErrors('required_badge_id');
        $this->assertNull($link->fresh()->icsData->required_badge_id);
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
