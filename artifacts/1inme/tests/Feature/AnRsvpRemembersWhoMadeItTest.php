<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An RSVP has never known which account made it.
 *
 * That is fine for the guest it was designed around -- anyone can RSVP to
 * a public event without an account, and should be able to. It is not fine
 * for the Sayzio user who RSVPs while signed in, because nothing
 * afterwards can follow the thread: their own dashboard cannot show them
 * the event they are going to, and nothing can introduce two people
 * attending the same thing.
 *
 * So: record the account when there is one, leave it null when there
 * isn't, and never claim an existing RSVP for an account after the fact.
 * An email is typed into a public form by whoever is sitting there.
 */
class AnRsvpRemembersWhoMadeItTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Link
    {
        $owner = User::factory()->create()->fresh();

        $link = new Link([
            'user_id'   => $owner->id,
            'type'      => 'ics',
            'alias'     => 'evt' . uniqid(),
            'title'     => 'Portfolio Review Night',
            'is_active' => true,
        ]);
        $link->workspace_id = $owner->ownedWorkspaces()->first()->id;
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

    private function rsvpPayload(array $extra = []): array
    {
        return array_merge([
            'name'     => 'Priya Sharma',
            'email'    => 'priya@example.com',
            'response' => 'yes',
        ], $extra);
    }

    public function test_a_signed_in_visitor_leaves_their_account_on_the_rsvp(): void
    {
        $link = $this->event();
        $guest = User::factory()->create()->fresh();

        $this->actingAs($guest)->post(
            route('redirect.rsvp.submit', $link->alias),
            $this->rsvpPayload()
        );

        $rsvp = Rsvp::where('link_id', $link->id)->firstOrFail();
        $this->assertSame($guest->id, (int) $rsvp->user_id);
    }

    public function test_a_guest_rsvp_still_works_and_belongs_to_nobody(): void
    {
        // The case the form was built for. It must not start requiring an
        // account, and it must not guess at one.
        $link = $this->event();

        $this->post(
            route('redirect.rsvp.submit', $link->alias),
            $this->rsvpPayload(['name' => 'Walk-in Guest'])
        );

        $rsvp = Rsvp::where('link_id', $link->id)->firstOrFail();
        $this->assertNull($rsvp->user_id);
        $this->assertSame('Walk-in Guest', $rsvp->name);
    }

    public function test_the_rsvp_points_back_at_the_account(): void
    {
        $link = $this->event();
        $guest = User::factory()->create()->fresh();

        $this->actingAs($guest)->post(
            route('redirect.rsvp.submit', $link->alias),
            $this->rsvpPayload()
        );

        $rsvp = Rsvp::where('link_id', $link->id)->firstOrFail();
        $this->assertSame($guest->id, $rsvp->user->id);
    }

    public function test_an_account_can_find_what_it_is_going_to(): void
    {
        $link = $this->event();
        $guest = User::factory()->create()->fresh();

        $mine = Rsvp::create([
            'link_id'  => $link->id,
            'user_id'  => $guest->id,
            'name'     => 'Mine, signed in',
            'email'    => 'something-else@example.com',
            'response' => 'yes',
        ]);

        // An RSVP made before this column existed, or made signed out,
        // still reaches its owner through the account's own email.
        $older = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Mine, by email',
            'email'    => $guest->email,
            'response' => 'yes',
        ]);

        $someoneElse = Rsvp::create([
            'link_id'  => $link->id,
            'name'     => 'Not mine',
            'email'    => 'stranger@example.com',
            'response' => 'yes',
        ]);

        $found = Rsvp::forAccount($guest)->pluck('id')->all();

        $this->assertContains($mine->id, $found);
        $this->assertContains($older->id, $found);
        $this->assertNotContains($someoneElse->id, $found);
    }

    public function test_the_email_arm_does_not_widen_a_narrower_query(): void
    {
        // forAccount() groups its two conditions. Ungrouped, the orWhere
        // would escape the surrounding filter and return other people's
        // RSVPs to other events -- a filter quietly becoming "everyone".
        $mine = $this->event();
        $other = $this->event();
        $guest = User::factory()->create()->fresh();

        Rsvp::create([
            'link_id' => $other->id, 'name' => 'Mine, elsewhere',
            'email' => $guest->email, 'response' => 'yes',
        ]);
        $here = Rsvp::create([
            'link_id' => $mine->id, 'user_id' => $guest->id,
            'name' => 'Mine, here', 'email' => $guest->email, 'response' => 'yes',
        ]);

        $found = Rsvp::where('link_id', $mine->id)->forAccount($guest)->pluck('id')->all();

        $this->assertSame([$here->id], $found);
    }
}
