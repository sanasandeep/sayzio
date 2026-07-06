<?php

namespace Tests\Feature;

use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the /api/v1/dialer/suggestions endpoint and the web
 * /user/dialer/suggestions route.
 *
 * Assertions:
 *   (1) Unauthenticated requests are rejected (401).
 *   (2) A user with no data gets an empty suggestions list (total=0, groups=[]).
 *   (3) Speed-dial favorites appear in the "favorites" group.
 *   (4) Accounts that recently followed the user appear in "new_followers".
 *   (5) Accounts the user recently followed appear in "following".
 *   (6) Active subscribers appear in "new_leads".
 *   (7) A suspended follower is excluded (reachability gate).
 *   (8) A follower that has blocked the user is excluded (block gate).
 *   (9) Form submissions from all user workspaces appear in new_leads.
 *   (10) Leads (submissions/subscribers) with no name, email, or phone are
 *        excluded, and blank leads don't starve out older actionable leads.
 *   (11) Web route (/user/dialer/suggestions) returns the same {data} envelope.
 */
class DialerSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    /**
     * Authenticate the given user and send a GET to the API suggestions
     * endpoint using a real Sanctum Bearer token.
     * (Sanctum::actingAs breaks the TouchSessionToken middleware —
     * see .agents/memory/sanctum-api-tests.md.)
     */
    private function apiSuggestions(User $user): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($user->createToken('suggestions-test')->plainTextToken)
            ->getJson('/api/v1/dialer/suggestions');
    }

    /**
     * Authenticate via session (web guard) for the web route.
     */
    private function actingAsWeb(User $user): self
    {
        $ws = $user->personalWorkspace ?? $user->workspaces()->first();
        return $this->actingAs($user)->withSession(
            $ws ? [WorkspaceContext::SESSION_KEY => $ws->id] : []
        );
    }

    // ── (1) Auth gate ─────────────────────────────────────────────────

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/dialer/suggestions')->assertUnauthorized();
    }

    // ── (2) Empty state ───────────────────────────────────────────────

    public function test_new_user_with_no_data_returns_empty(): void
    {
        $user = $this->makeUser('empty');
        $resp = $this->apiSuggestions($user);

        $resp->assertOk();
        $data = $resp->json('data');
        $this->assertIsArray($data);
        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['groups']);
    }

    // ── (3) Favorites group ───────────────────────────────────────────

    public function test_favorites_appear_in_suggestions(): void
    {
        $user = $this->makeUser('fav');

        DialerFavorite::create([
            'user_id'     => $user->id,
            'number_e164' => '+14155550101',
            'label'       => 'My Fav',
            'sort_order'  => 0,
        ]);

        $resp = $this->apiSuggestions($user);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $favGroup = collect($groups)->firstWhere('key', 'favorites');
        $this->assertNotNull($favGroup, 'Expected a favorites group');
        $this->assertCount(1, $favGroup['items']);
        $this->assertSame('My Fav', $favGroup['items'][0]['title']);
    }

    // ── (4) New followers group ───────────────────────────────────────

    public function test_recent_followers_appear_in_new_followers_group(): void
    {
        $creator  = $this->makeUser('creator');
        $follower = $this->makeUser('follower');

        Follow::create([
            'follower_id' => $follower->id,
            'creator_id'  => $creator->id,
            'created_at'  => now(),
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $newFollowersGroup = collect($groups)->firstWhere('key', 'new_followers');
        $this->assertNotNull($newFollowersGroup, 'Expected a new_followers group');

        $userIds = array_column(array_column($newFollowersGroup['items'], 'action'), 'user_id');
        $this->assertContains($follower->id, $userIds);
    }

    // ── (5) Following group ───────────────────────────────────────────

    public function test_recently_followed_accounts_appear_in_following_group(): void
    {
        $viewer  = $this->makeUser('viewer');
        $creator = $this->makeUser('creator2');

        Follow::create([
            'follower_id' => $viewer->id,
            'creator_id'  => $creator->id,
            'created_at'  => now(),
        ]);

        $resp = $this->apiSuggestions($viewer);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $followingGroup = collect($groups)->firstWhere('key', 'following');
        $this->assertNotNull($followingGroup, 'Expected a following group');

        $userIds = array_column(array_column($followingGroup['items'], 'action'), 'user_id');
        $this->assertContains($creator->id, $userIds);
    }

    // ── (6) New leads (subscribers) ───────────────────────────────────

    public function test_recent_subscribers_appear_in_new_leads_group(): void
    {
        $creator = $this->makeUser('creator3');

        Subscriber::create([
            'user_id' => $creator->id,
            'type'    => 'email',
            'email'   => 'lead@example.com',
            'name'    => 'Lead Person',
            'status'  => 'active',
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $leadsGroup = collect($groups)->firstWhere('key', 'new_leads');
        $this->assertNotNull($leadsGroup, 'Expected a new_leads group');
        $this->assertCount(1, $leadsGroup['items']);
        $this->assertSame('Lead Person', $leadsGroup['items'][0]['title']);
        $this->assertSame('Subscriber', $leadsGroup['items'][0]['type_label']);
    }

    // ── (7) Suspended follower excluded (reachability gate) ───────────

    public function test_suspended_follower_is_excluded_from_new_followers(): void
    {
        $creator   = $this->makeUser('cr4');
        $suspended = $this->makeUser('susp');
        $suspended->status = 'suspended';
        $suspended->save();

        Follow::create([
            'follower_id' => $suspended->id,
            'creator_id'  => $creator->id,
            'created_at'  => now(),
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $newFollowersGroup = collect($groups)->firstWhere('key', 'new_followers');

        // The suspended account must not appear.
        $userIds = $newFollowersGroup
            ? array_column(array_column($newFollowersGroup['items'], 'action'), 'user_id')
            : [];
        $this->assertNotContains($suspended->id, $userIds);
    }

    // ── (8) Blocked follower excluded ────────────────────────────────

    public function test_follower_who_blocked_the_user_is_excluded(): void
    {
        $creator  = $this->makeUser('cr5');
        $blocker  = $this->makeUser('blkr');

        Follow::create([
            'follower_id' => $blocker->id,
            'creator_id'  => $creator->id,
            'created_at'  => now(),
        ]);

        // $blocker has blocked $creator (the authenticated user).
        UserBlock::create([
            'blocker_user_id' => $blocker->id,
            'blocked_user_id' => $creator->id,
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $newFollowersGroup = collect($groups)->firstWhere('key', 'new_followers');

        $userIds = $newFollowersGroup
            ? array_column(array_column($newFollowersGroup['items'], 'action'), 'user_id')
            : [];
        $this->assertNotContains($blocker->id, $userIds);
    }

    // ── (9) Form submissions from all user workspaces appear in new_leads ─

    public function test_form_submissions_appear_regardless_of_active_workspace(): void
    {
        $creator = $this->makeUser('cr6');

        $form = \App\Modules\User\Models\Form::create([
            'user_id' => $creator->id,
            'name'    => 'Contact form',
        ]);

        FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => ['name' => 'Cross Workspace Lead', 'phone' => '+15550001234'],
            'is_spam'    => false,
            'is_read'    => false,
            'is_starred' => false,
            'status'     => 'completed',
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $leadsGroup = collect($groups)->firstWhere('key', 'new_leads');
        $this->assertNotNull($leadsGroup, 'Expected a new_leads group');
        $titles = array_column($leadsGroup['items'], 'title');
        $this->assertContains('Cross Workspace Lead', $titles);
    }

    // ── (10) Leads with no contact info are excluded ──────────────────

    public function test_leads_without_contact_info_are_excluded_from_new_leads(): void
    {
        $creator = $this->makeUser('cr7');

        $form = \App\Modules\User\Models\Form::create([
            'user_id' => $creator->id,
            'name'    => 'Feedback form',
        ]);

        // Submission whose payload has no extractable name/email/phone.
        FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => ['message' => 'Great site!', 'rating' => '5'],
            'is_spam'    => false,
            'is_read'    => false,
            'is_starred' => false,
            'status'     => 'completed',
        ]);

        // Subscriber row with no name, email, or phone.
        Subscriber::create([
            'user_id' => $creator->id,
            'type'    => 'email',
            'status'  => 'active',
        ]);

        // An actionable submission so the group still renders.
        FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => ['name' => 'Actionable Lead', 'email' => 'act@example.com'],
            'is_spam'    => false,
            'is_read'    => false,
            'is_starred' => false,
            'status'     => 'completed',
        ]);

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $leadsGroup = collect($groups)->firstWhere('key', 'new_leads');
        $this->assertNotNull($leadsGroup, 'Expected a new_leads group');
        $this->assertCount(1, $leadsGroup['items'], 'Blank-data leads must be excluded');
        $this->assertSame('Actionable Lead', $leadsGroup['items'][0]['title']);
        $titles = array_column($leadsGroup['items'], 'title');
        $this->assertNotContains('Form lead', $titles);
        $this->assertNotContains('Subscriber', $titles);
    }

    public function test_blank_leads_do_not_starve_out_older_actionable_leads(): void
    {
        $creator = $this->makeUser('cr8');

        $form = \App\Modules\User\Models\Form::create([
            'user_id' => $creator->id,
            'name'    => 'Survey form',
        ]);

        // One OLDER actionable submission…
        $old = FormSubmission::create([
            'form_id'    => $form->id,
            'data'       => ['name' => 'Older Actionable Lead', 'phone' => '+15550002222'],
            'is_spam'    => false,
            'is_read'    => false,
            'is_starred' => false,
            'status'     => 'completed',
        ]);
        $old->created_at = now()->subDays(3);
        $old->save();

        // …buried under more-than-LIMIT newer blank submissions.
        for ($i = 0; $i < 10; $i++) {
            FormSubmission::create([
                'form_id'    => $form->id,
                'data'       => ['message' => 'no contact info ' . $i],
                'is_spam'    => false,
                'is_read'    => false,
                'is_starred' => false,
                'status'     => 'completed',
            ]);
        }

        $resp = $this->apiSuggestions($creator);
        $resp->assertOk();

        $groups = $resp->json('data.groups');
        $leadsGroup = collect($groups)->firstWhere('key', 'new_leads');
        $this->assertNotNull($leadsGroup, 'Expected a new_leads group');
        $titles = array_column($leadsGroup['items'], 'title');
        $this->assertContains(
            'Older Actionable Lead',
            $titles,
            'Blank leads must not consume list slots and hide actionable leads'
        );
        $this->assertNotContains('Form lead', $titles);
    }

    // ── (11) Web route envelope ───────────────────────────────────────

    public function test_web_route_returns_data_envelope(): void
    {
        $user = $this->makeUser('web');

        $resp = $this->actingAsWeb($user)
            ->getJson(route('user.dialer.suggestions'));

        $resp->assertOk();
        $this->assertArrayHasKey('data', $resp->json());
        $data = $resp->json('data');
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('groups', $data);
    }
}
