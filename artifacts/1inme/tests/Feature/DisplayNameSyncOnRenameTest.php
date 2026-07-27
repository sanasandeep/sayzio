<?php

namespace Tests\Feature;

use App\Modules\User\Models\BlockComment;
use App\Modules\User\Models\CommunityMember;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\FanPoint;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * When a user renames their profile (web or API path), every denormalized
 * copy of their display name must follow: block comments they authored,
 * community-member rosters, fan-points entries, subscriber opt-ins matched
 * by their email, and contacts internally linked to them (Google-synced
 * contacts stay untouched). Cached creator surfaces are busted. Anonymous
 * (NULL) snapshots must never be de-anonymized.
 */
class DisplayNameSyncOnRenameTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private Link $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = User::factory()->create(['name' => 'Creator'])->fresh();
        $this->link = Link::create([
            'user_id' => $this->creator->id,
            'alias'   => 'sync-test-' . uniqid(),
            'type'    => 'biolink',
            'url'     => null,
        ]);
    }

    private function seedDenormalizedRows(User $fan): array
    {
        $comment = BlockComment::create([
            'link_id'        => $this->link->id,
            'block_id'       => $this->makeBlock()->id,
            'viewer_user_id' => $fan->id,
            'author_name'    => $fan->name,
            'body'           => 'hello',
            'status'         => 'visible',
        ]);
        $member = CommunityMember::create([
            'user_id'        => $this->creator->id,
            'link_id'        => $this->link->id,
            'viewer_user_id' => $fan->id,
            'email'          => $fan->email,
            'display_name'   => $fan->name,
            'tier'           => 'free',
            'status'         => 'active',
            'joined_at'      => now(),
        ]);
        $point = FanPoint::create([
            'user_id'        => $this->creator->id,
            'link_id'        => $this->link->id,
            'viewer_user_id' => $fan->id,
            'display_name'   => $fan->name,
            'action'         => 'comment',
            'points'         => 5,
            'subject_id'     => $this->link->id,
            'subject_type'   => Link::class,
        ]);
        $sub = Subscriber::create([
            'user_id'       => $this->creator->id,
            'link_id'       => $this->link->id,
            'type'          => 'email',
            'email'         => strtolower($fan->email),
            'name'          => $fan->name,
            'status'        => 'active',
            'source'        => 'test',
            'subscribed_at' => now(),
        ]);
        $linkedContact = Contact::create([
            'user_id'         => $this->creator->id,
            'display_name'    => $fan->name,
            'biolink_user_id' => $fan->id,
        ]);
        $roadmapComment = \App\Modules\User\Models\RoadmapComment::create([
            'item_id'        => $this->makeRoadmapItem()->id,
            'viewer_user_id' => $fan->id,
            'author_name'    => $fan->name,
            'body'           => 'roadmap thoughts',
        ]);
        $review = \App\Modules\User\Models\Review::create([
            'user_id'      => $this->creator->id,
            'link_id'      => $this->link->id,
            'author_name'  => $fan->name,
            'author_email' => strtoupper($fan->email), // case-insensitive match
            'rating'       => 5,
            'body'         => 'great creator',
            'status'       => \App\Modules\User\Models\Review::STATUS_APPROVED,
        ]);

        return compact('comment', 'member', 'point', 'sub', 'linkedContact', 'roadmapComment', 'review');
    }

    private function makeRoadmapItem(): \App\Modules\User\Models\RoadmapItem
    {
        return \App\Modules\User\Models\RoadmapItem::create([
            'workspace_id' => $this->creator->ownedWorkspaces()->first()?->id
                ?? \Illuminate\Support\Facades\DB::table('workspaces')->insertGetId([
                    'owner_user_id' => $this->creator->id,
                    'name'          => 'WS',
                    'is_personal'   => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]),
            'link_id'      => $this->link->id,
            'block_id'     => $this->makeBlock()->id,
            'title'        => 'Roadmap item',
        ]);
    }

    private function makeBlock(): \App\Modules\User\Models\BiolinkBlock
    {
        return \App\Modules\User\Models\BiolinkBlock::create([
            'link_id'  => $this->link->id,
            'type'     => 'text',
            'settings' => [],
            'order'    => 1,
        ]);
    }

    public function test_web_rename_propagates_to_all_denormalized_copies(): void
    {
        $fan = User::factory()->create(['name' => 'Old Fan'])->fresh();
        $rows = $this->seedDenormalizedRows($fan);

        $resp = $this->actingAs($fan)->put(route('user.profile.update'), [
            'name' => 'New Fan', 'email' => $fan->email, 'timezone' => 'UTC', 'language' => 'en',
        ]);
        $resp->assertSessionHasNoErrors();

        $this->assertSame('New Fan', $rows['comment']->fresh()->author_name);
        $this->assertSame('New Fan', $rows['member']->fresh()->display_name);
        $this->assertSame('New Fan', $rows['point']->fresh()->display_name);
        $this->assertSame('New Fan', $rows['sub']->fresh()->name);
        $this->assertSame('New Fan', $rows['linkedContact']->fresh()->display_name);
        $this->assertSame('New Fan', $rows['roadmapComment']->fresh()->author_name);
        $this->assertSame('New Fan', $rows['review']->fresh()->author_name);
    }

    public function test_api_rename_propagates_and_skips_google_contacts_and_anonymous_rows(): void
    {
        $fan = User::factory()->create(['name' => 'Old Fan'])->fresh();
        $rows = $this->seedDenormalizedRows($fan);

        // Google-synced contact linked to the same user must stay frozen.
        $account = \App\Modules\User\Models\GoogleContactsAccount::create([
            'user_id'       => $this->creator->id,
            'google_email'  => 'creator@gmail.example',
            'access_token'  => 'x',
            'refresh_token' => 'y',
        ]);
        $googleContact = Contact::create([
            'user_id'                    => $this->creator->id,
            'display_name'               => 'Old Fan',
            'biolink_user_id'            => $fan->id,
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => 'people/c1',
        ]);

        // Anonymous fan-point entry (no display name) must stay anonymous.
        $anonPoint = FanPoint::create([
            'user_id'           => $this->creator->id,
            'link_id'           => $this->link->id,
            'viewer_user_id'    => $fan->id,
            'display_name'      => null,
            'voter_fingerprint' => 'fp1',
            'action'            => 'click',
            'points'            => 1,
            'subject_id'        => $this->link->id,
            'subject_type'      => Link::class,
        ]);

        // Anonymous native review (no author name) must stay anonymous.
        $anonReview = \App\Modules\User\Models\Review::create([
            'user_id'      => $this->creator->id,
            'link_id'      => $this->link->id,
            'author_name'  => null,
            'author_email' => $fan->email,
            'rating'       => 4,
            'body'         => 'anon',
            'status'       => \App\Modules\User\Models\Review::STATUS_APPROVED,
        ]);

        $token = $fan->createToken('t')->plainTextToken;
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', ['name' => 'API Fan']);
        $resp->assertOk();

        $this->assertSame('API Fan', $rows['comment']->fresh()->author_name);
        $this->assertSame('API Fan', $rows['member']->fresh()->display_name);
        $this->assertSame('API Fan', $rows['point']->fresh()->display_name);
        $this->assertSame('API Fan', $rows['sub']->fresh()->name);
        $this->assertSame('API Fan', $rows['linkedContact']->fresh()->display_name);
        $this->assertSame('API Fan', $rows['roadmapComment']->fresh()->author_name);
        $this->assertSame('API Fan', $rows['review']->fresh()->author_name);
        $this->assertSame('Old Fan', $googleContact->fresh()->display_name);
        $this->assertNull($anonPoint->fresh()->display_name);
        $this->assertNull($anonReview->fresh()->author_name);
    }

    public function test_rename_busts_creator_index_cache(): void
    {
        $fan = User::factory()->create(['name' => 'Old Fan'])->fresh();
        Cache::put(\App\Modules\Common\Controllers\CreatorsController::DEFAULT_CACHE_KEY, ['stale'], 300);

        $this->actingAs($fan)->put(route('user.profile.update'), [
            'name' => 'New Fan', 'email' => $fan->email, 'timezone' => 'UTC', 'language' => 'en',
        ])->assertSessionHasNoErrors();

        $this->assertNull(Cache::get(\App\Modules\Common\Controllers\CreatorsController::DEFAULT_CACHE_KEY));
    }

    public function test_backfill_command_fixes_stale_snapshots(): void
    {
        $fan = User::factory()->create(['name' => 'Fresh Name'])->fresh();
        // Simulate a pre-sync rename: rows still carry the old name.
        $comment = BlockComment::create([
            'link_id'        => $this->link->id,
            'block_id'       => $this->makeBlock()->id,
            'viewer_user_id' => $fan->id,
            'author_name'    => 'Stale Name',
            'body'           => 'hi',
            'status'         => 'visible',
        ]);
        $sub = Subscriber::create([
            'user_id'       => $this->creator->id,
            'link_id'       => $this->link->id,
            'type'          => 'email',
            'email'         => strtolower($fan->email),
            'name'          => 'Stale Name',
            'status'        => 'active',
            'source'        => 'test',
            'subscribed_at' => now(),
        ]);

        $roadmapComment = \App\Modules\User\Models\RoadmapComment::create([
            'item_id'        => $this->makeRoadmapItem()->id,
            'viewer_user_id' => $fan->id,
            'author_name'    => 'Stale Name',
            'body'           => 'old roadmap comment',
        ]);
        $review = \App\Modules\User\Models\Review::create([
            'user_id'      => $this->creator->id,
            'link_id'      => $this->link->id,
            'author_name'  => 'Stale Name',
            'author_email' => strtoupper($fan->email),
            'rating'       => 5,
            'body'         => 'old review',
            'status'       => \App\Modules\User\Models\Review::STATUS_APPROVED,
        ]);

        $this->artisan('users:sync-display-names')->assertExitCode(0);

        $this->assertSame('Fresh Name', $comment->fresh()->author_name);
        $this->assertSame('Fresh Name', $sub->fresh()->name);
        $this->assertSame('Fresh Name', $roadmapComment->fresh()->author_name);
        $this->assertSame('Fresh Name', $review->fresh()->author_name);
    }
}
