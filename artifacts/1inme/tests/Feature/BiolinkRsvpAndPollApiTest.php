<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the JSON poll-vote and RSVP submission endpoints introduced for
 * the mobile biolink viewer. Both endpoints are reached via the biolink
 * alias + block id; the RSVP endpoint resolves the actual ICS event link
 * server-side from the block's `event_link_id` setting.
 */
class BiolinkRsvpAndPollApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeBiolink(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => Link::generateAlias(),
            'title'   => 'My Bio',
        ]);
    }

    public function test_poll_vote_persists_and_dedupes_per_voter(): void
    {
        $user = $this->makeUser();
        $bio  = $this->makeBiolink($user);
        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'poll',
            'sort_order' => 0,
            'settings'   => ['question' => 'Pick one', 'options' => ['A', 'B', 'C']],
        ]);

        $url = "/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/poll-vote";

        $r1 = $this->postJson($url, ['option_index' => 1, 'option_label' => 'B']);
        $r1->assertOk();
        $this->assertEquals(1, PollVote::where('block_id', $block->id)->count());
        $this->assertEquals('B', PollVote::where('block_id', $block->id)->first()->option_label);

        // Same anonymous viewer (same ip+ua) re-voting updates the existing
        // row instead of creating a duplicate.
        $r2 = $this->postJson($url, ['option_index' => 0, 'option_label' => 'A']);
        $r2->assertOk();
        $this->assertEquals(1, PollVote::where('block_id', $block->id)->count());
        $this->assertEquals('A', PollVote::where('block_id', $block->id)->first()->option_label);

        // Out-of-range option is rejected.
        $this->postJson($url, ['option_index' => 99])->assertStatus(422);
    }

    public function test_poll_results_aggregates_per_option_and_respects_visibility(): void
    {
        $user = $this->makeUser();
        $bio  = $this->makeBiolink($user);
        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'poll',
            'sort_order' => 0,
            'settings'   => ['question' => 'Pick one', 'options' => ['A', 'B', 'C']],
        ]);

        // Seed three votes from distinct fingerprints so the unique
        // (block_id, voter_fingerprint) constraint isn't tripped.
        PollVote::create(['block_id' => $block->id, 'link_id' => $bio->id, 'option_index' => 0, 'option_label' => 'A', 'voter_fingerprint' => 'f1', 'source' => 'web']);
        PollVote::create(['block_id' => $block->id, 'link_id' => $bio->id, 'option_index' => 1, 'option_label' => 'B', 'voter_fingerprint' => 'f2', 'source' => 'web']);
        PollVote::create(['block_id' => $block->id, 'link_id' => $bio->id, 'option_index' => 1, 'option_label' => 'B', 'voter_fingerprint' => 'f3', 'source' => 'web']);

        $resp = $this->getJson("/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/poll-results");
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertSame($block->id, $data['block_id']);
        $this->assertSame(3, $data['total_votes']);
        $this->assertCount(3, $data['options']);
        $this->assertSame(1, $data['options'][0]['count']);
        $this->assertSame(2, $data['options'][1]['count']);
        $this->assertSame(0, $data['options'][2]['count']);
        $this->assertSame(33, $data['options'][0]['percent']);
        $this->assertSame(67, $data['options'][1]['percent']);

        // Now lock the biolink to followers — anonymous viewer must be
        // refused, just like the page itself.
        $bio->forceFill(['visibility' => 'followers'])->save();
        $this->getJson("/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/poll-results")
            ->assertStatus(401);
    }

    public function test_rsvp_submission_resolves_event_link_via_block(): void
    {
        $user = $this->makeUser();
        $bio  = $this->makeBiolink($user);

        $event = Link::create([
            'user_id'  => $user->id,
            'type'     => 'ics',
            'alias'    => Link::generateAlias(),
            'title'    => 'Launch Party',
            'settings' => ['rsvp_enabled' => true, 'rsvp_allow_plus_ones' => true],
        ]);

        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'rsvp',
            'sort_order' => 0,
            'settings'   => ['event_link_id' => $event->id, 'heading' => 'RSVP to'],
        ]);

        $resp = $this->postJson(
            "/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/rsvp",
            [
                'name'      => 'Sam Sample',
                'email'     => 'sam@example.com',
                'response'  => 'yes',
                'plus_ones' => 2,
                'message'   => 'See you there',
            ]
        );

        $resp->assertCreated();
        $rsvp = Rsvp::where('link_id', $event->id)->first();
        $this->assertNotNull($rsvp);
        $this->assertEquals('Sam Sample', $rsvp->name);
        $this->assertEquals('yes', $rsvp->response);
        $this->assertEquals(2, $rsvp->plus_ones);
        $this->assertEquals($block->id, $rsvp->source_block_id);
    }

    public function test_rsvp_rejects_block_pointing_at_other_users_event(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $bio   = $this->makeBiolink($owner);

        $foreignEvent = Link::create([
            'user_id'  => $other->id,
            'type'     => 'ics',
            'alias'    => Link::generateAlias(),
            'title'    => 'Not yours',
            'settings' => ['rsvp_enabled' => true],
        ]);

        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'rsvp',
            'sort_order' => 0,
            'settings'   => ['event_link_id' => $foreignEvent->id],
        ]);

        $this->postJson(
            "/api/v1/biolinks/{$bio->alias}/blocks/{$block->id}/rsvp",
            ['name' => 'Eve', 'response' => 'yes']
        )->assertStatus(404);

        $this->assertEquals(0, Rsvp::where('link_id', $foreignEvent->id)->count());
    }
}
