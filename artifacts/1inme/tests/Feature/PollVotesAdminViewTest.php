<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\PollVote;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the creator-facing poll-votes list, CSV export, and per-vote
 * delete endpoints. Mirrors the RSVP admin views.
 */
class PollVotesAdminViewTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner ' . Str::random(4),
            'email'    => 'own' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function setupPoll(User $user): array
    {
        $bio = Link::create([
            'user_id' => $user->id,
            'type'    => 'biolink',
            'alias'   => Link::generateAlias(),
            'title'   => 'My Bio',
        ]);
        $block = BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'poll',
            'sort_order' => 0,
            'settings'   => ['question' => 'Pick one', 'options' => ['A', 'B', 'C']],
        ]);
        PollVote::create([
            'link_id' => $bio->id, 'block_id' => $block->id,
            'option_index' => 0, 'option_label' => 'A',
            'voter_fingerprint' => 'fpA', 'source' => 'biolink',
        ]);
        PollVote::create([
            'link_id' => $bio->id, 'block_id' => $block->id,
            'option_index' => 1, 'option_label' => 'B',
            'voter_fingerprint' => 'fpB', 'source' => 'mobile_app',
        ]);
        return [$bio, $block];
    }

    public function test_index_lists_votes_for_owner(): void
    {
        $owner = $this->makeUser();
        [$bio, $block] = $this->setupPoll($owner);

        $resp = $this->actingAs($owner)
            ->get(route('user.links.poll-votes.index', [$bio, $block]));

        $resp->assertOk();
        $resp->assertSee('Pick one');
        $resp->assertSee('Poll votes');
    }

    public function test_index_blocks_other_users(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        [$bio, $block] = $this->setupPoll($owner);

        $this->actingAs($other)
            ->get(route('user.links.poll-votes.index', [$bio, $block]))
            ->assertForbidden();
    }

    public function test_index_404s_for_non_poll_block(): void
    {
        $owner = $this->makeUser();
        $bio = Link::create([
            'user_id' => $owner->id, 'type' => 'biolink',
            'alias' => Link::generateAlias(), 'title' => 'B',
        ]);
        $block = BiolinkBlock::create([
            'link_id' => $bio->id, 'type' => 'heading',
            'sort_order' => 0, 'settings' => [],
        ]);

        $this->actingAs($owner)
            ->get(route('user.links.poll-votes.index', [$bio, $block]))
            ->assertNotFound();
    }

    public function test_export_streams_csv(): void
    {
        $owner = $this->makeUser();
        [$bio, $block] = $this->setupPoll($owner);

        $resp = $this->actingAs($owner)
            ->get(route('user.links.poll-votes.export', [$bio, $block]));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $resp->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($body))));
        $this->assertSame(
            ['Option index', 'Option label', 'Voter name', 'Voter email',
             'Voter fingerprint', 'Source', 'IP address', 'Submitted at'],
            $rows[0]
        );
        $labels = array_column(array_slice($rows, 1), 1);
        $this->assertContains('A', $labels);
        $this->assertContains('B', $labels);
    }

    public function test_destroy_removes_a_vote(): void
    {
        $owner = $this->makeUser();
        [$bio, $block] = $this->setupPoll($owner);
        $vote = PollVote::where('block_id', $block->id)->first();

        $this->actingAs($owner)
            ->delete(route('user.links.poll-votes.destroy', [$bio, $block, $vote]))
            ->assertRedirect();

        $this->assertNull(PollVote::find($vote->id));
        $this->assertEquals(1, PollVote::where('block_id', $block->id)->count());
    }
}
