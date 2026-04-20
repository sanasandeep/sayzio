<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\AccountMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountMergeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    public function test_backfill_creates_linked_identifier_for_existing_user(): void
    {
        $user = $this->makeUser(['email' => 'me@example.com']);
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $user->id,
            'kind'    => 'email',
            'value'   => 'me@example.com',
            'is_primary' => true,
        ]);
    }

    public function test_link_and_login_via_secondary_email(): void
    {
        $user = $this->makeUser(['email' => 'primary@example.com']);
        LinkedIdentifier::create([
            'user_id' => $user->id,
            'kind'    => 'email',
            'value'   => 'alt@example.com',
            'verified_at' => now(),
        ]);

        // Use OTP send + verify with the dev-mode static "123456" code.
        $this->post('/user/send-otp', ['identifier' => 'alt@example.com', 'type' => 'email'])
             ->assertRedirect();

        $resp = $this->post('/user/verify-otp', ['code' => '123456']);
        $resp->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_cannot_link_identifier_already_attached_to_another_account(): void
    {
        $a = $this->makeUser(['email' => 'a@example.com']);
        $b = $this->makeUser(['email' => 'b@example.com']);

        $this->actingAs($b)
            ->post('/user/identifiers/start', ['kind' => 'email', 'value' => 'a@example.com'])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('linked_identifiers', [
            'user_id' => $b->id, 'value' => 'a@example.com',
        ]);
    }

    public function test_unlink_refuses_to_leave_user_without_identifiers(): void
    {
        $u = $this->makeUser();
        $primary = $u->primaryIdentifier();
        $svc = new AccountMergeService();
        $this->expectException(\RuntimeException::class);
        $svc->unlink($u->fresh(), $primary);
    }

    public function test_promote_swaps_primary_and_syncs_user_email(): void
    {
        $u = $this->makeUser(['email' => 'old@example.com']);
        $alt = LinkedIdentifier::create([
            'user_id' => $u->id, 'kind' => 'email', 'value' => 'new@example.com',
            'verified_at' => now(), 'is_primary' => false,
        ]);
        (new AccountMergeService())->promoteToPrimary($u->fresh(), $alt->fresh());

        $this->assertEquals('new@example.com', $u->fresh()->email);
        $this->assertTrue($alt->fresh()->is_primary);
    }

    public function test_merge_reassigns_data_and_deletes_secondary(): void
    {
        $primary   = $this->makeUser(['email' => 'p@example.com']);
        $secondary = $this->makeUser(['email' => 's@example.com']);

        // Create a couple of owned rows on the secondary.
        DB::table('projects')->insert([
            'user_id' => $secondary->id, 'name' => 'X',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $summary = (new AccountMergeService())->merge($primary->fresh(), $secondary->fresh(), 'primary');

        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
        $this->assertDatabaseHas('projects', ['user_id' => $primary->id, 'name' => 'X']);
        // Secondary's identifier has moved.
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $primary->id, 'value' => 's@example.com',
        ]);
        $this->assertNotEmpty($summary['reassigned']);
    }

    public function test_merge_with_keep_secondary_plan_cancels_primary_plan(): void
    {
        $paid = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'monthly_price' => 10, 'annual_price' => 100,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);
        $primary   = $this->makeUser(['email' => 'p@example.com', 'plan_id' => null]);
        $secondary = $this->makeUser([
            'email' => 's@example.com', 'plan_id' => $paid->id,
            'plan_expires_at' => now()->addMonth(),
        ]);

        (new AccountMergeService())->merge($primary->fresh(), $secondary->fresh(), 'secondary');
        $this->assertEquals($paid->id, $primary->fresh()->plan_id);
        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
    }

    public function test_cannot_merge_an_account_into_itself(): void
    {
        $u = $this->makeUser();
        $this->expectException(\InvalidArgumentException::class);
        (new AccountMergeService())->merge($u->fresh(), $u->fresh(), 'primary');
    }

    public function test_merge_preview_lists_owned_data_counts(): void
    {
        $primary   = $this->makeUser();
        $secondary = $this->makeUser();
        DB::table('projects')->insert([
            'user_id' => $secondary->id, 'name' => 'A',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('projects')->insert([
            'user_id' => $secondary->id, 'name' => 'B',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $preview = (new AccountMergeService())->preview($primary, $secondary);
        $this->assertSame(2, $preview['counts']['projects.user_id'] ?? null);
    }

    public function test_merge_dedupes_overlapping_follows_safely(): void
    {
        // Both accounts follow the same creator, AND a third user follows
        // both accounts. Both halves stress the (follower_id, creator_id)
        // unique key in opposite directions.
        $primary   = $this->makeUser();
        $secondary = $this->makeUser();
        $creator   = $this->makeUser();
        $fan       = $this->makeUser();

        \DB::table('follows')->insert([
            ['follower_id' => $primary->id,   'creator_id' => $creator->id, 'created_at' => now()],
            ['follower_id' => $secondary->id, 'creator_id' => $creator->id, 'created_at' => now()],
            ['follower_id' => $fan->id, 'creator_id' => $primary->id,   'created_at' => now()],
            ['follower_id' => $fan->id, 'creator_id' => $secondary->id, 'created_at' => now()],
        ]);

        (new AccountMergeService())->merge($primary->fresh(), $secondary->fresh(), 'primary');

        // Exactly one row in each direction should remain.
        $this->assertSame(1, \DB::table('follows')
            ->where('follower_id', $primary->id)->where('creator_id', $creator->id)->count());
        $this->assertSame(1, \DB::table('follows')
            ->where('follower_id', $fan->id)->where('creator_id', $primary->id)->count());
        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
    }

    public function test_social_login_resolves_provider_external_id(): void
    {
        // Mirrors the canonicalization fix: Twitter/X returns its id under
        // `data.id`, so a row keyed `twitter:<external_id>` must resolve to
        // its owning user via LinkedIdentifier::resolveUser.
        $u = $this->makeUser();
        LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'social',
            'value'       => LinkedIdentifier::normalize('social', '', 'twitter', '1234567890'),
            'provider'    => 'twitter',
            'external_id' => '1234567890',
            'verified_at' => now(),
        ]);

        $resolved = LinkedIdentifier::resolveUser('social', '', 'twitter', '1234567890');
        $this->assertNotNull($resolved);
        $this->assertSame($u->id, $resolved->id);
    }

    public function test_merge_rolls_back_on_mid_transaction_db_failure(): void
    {
        // Induce a real reassignment failure halfway through the merge by
        // pointing the service at a table whose user_id column doesn't
        // accept the value we're going to write into it. We do that by
        // dynamically creating a CHECK-constrained scratch table and
        // registering it as an "owned table" via subclassing.
        $primary   = $this->makeUser(['email' => 'p@example.com']);
        $secondary = $this->makeUser(['email' => 's@example.com']);

        // Real owned data on the secondary that should NOT be moved when
        // the merge rolls back.
        \DB::table('projects')->insert([
            'user_id' => $secondary->id, 'name' => 'untouched',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Scratch table with a CHECK that rejects the primary's id —
        // any attempt to reassign user_id to the primary will throw a
        // QueryException mid-transaction.
        $primaryId = $primary->id;
        \DB::statement("CREATE TABLE merge_canary (id INTEGER PRIMARY KEY, user_id INTEGER CHECK (user_id <> {$primaryId}))");
        \DB::table('merge_canary')->insert(['user_id' => $secondary->id]);

        $service = new class extends AccountMergeService {
            public function ownedTables(): array {
                $base = parent::ownedTables();
                $base[] = ['merge_canary', 'user_id'];
                return $base;
            }
        };

        $threw = false;
        try { $service->merge($primary->fresh(), $secondary->fresh(), 'primary'); }
        catch (\Throwable $e) { $threw = true; }

        $this->assertTrue($threw, 'Merge should throw when reassignment hits a DB constraint');
        // Both accounts intact, secondary's data still owned by secondary.
        $this->assertDatabaseHas('users',    ['id' => $secondary->id]);
        $this->assertDatabaseHas('users',    ['id' => $primary->id]);
        $this->assertDatabaseHas('projects', ['user_id' => $secondary->id, 'name' => 'untouched']);
        $this->assertDatabaseMissing('projects', ['user_id' => $primary->id, 'name' => 'untouched']);
        $this->assertDatabaseHas('merge_canary', ['user_id' => $secondary->id]);
    }

    public function test_phone_normalization_matches_runtime_lookup(): void
    {
        // Whitespace, parens, dashes and dots must all collapse so that
        // backfilled legacy numbers ("+1 (555) 123-4567") resolve via
        // canonical input ("+15551234567").
        $u = $this->makeUser();
        LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', '+1 (555) 123-4567'),
            'verified_at' => now(),
        ]);
        $resolved = LinkedIdentifier::resolveUser('phone', '+15551234567');
        $this->assertNotNull($resolved);
        $this->assertSame($u->id, $resolved->id);
    }

    public function test_merge_dedupes_subscribers_and_social_connections(): void
    {
        // Both `subscribers` and `social_account_connections` carry
        // unique constraints involving user_id — these must merge
        // cleanly rather than blow up the transaction.
        $primary   = $this->makeUser(['email' => 'p@example.com']);
        $secondary = $this->makeUser(['email' => 's@example.com']);

        \DB::table('subscribers')->insert([
            ['user_id' => $primary->id,   'type' => 'email', 'email' => 'fan@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $secondary->id, 'type' => 'email', 'email' => 'fan@example.com', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $secondary->id, 'type' => 'email', 'email' => 'unique@example.com', 'created_at' => now(), 'updated_at' => now()],
        ]);
        \DB::table('social_account_connections')->insert([
            ['user_id' => $primary->id,   'platform' => 'instagram', 'handle' => 'shared', 'access_token' => 'a', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $secondary->id, 'platform' => 'instagram', 'handle' => 'shared', 'access_token' => 'b', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $secondary->id, 'platform' => 'tiktok',    'handle' => 'only',   'access_token' => 'c', 'created_at' => now(), 'updated_at' => now()],
        ]);

        (new AccountMergeService())->merge($primary->fresh(), $secondary->fresh(), 'primary');

        $this->assertSame(1, \DB::table('subscribers')
            ->where('user_id', $primary->id)->where('email', 'fan@example.com')->count());
        $this->assertSame(1, \DB::table('subscribers')
            ->where('user_id', $primary->id)->where('email', 'unique@example.com')->count());
        $this->assertSame(1, \DB::table('social_account_connections')
            ->where('user_id', $primary->id)->where('platform', 'instagram')->where('handle', 'shared')->count());
        $this->assertSame(1, \DB::table('social_account_connections')
            ->where('user_id', $primary->id)->where('platform', 'tiktok')->where('handle', 'only')->count());
        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
    }

    public function test_merge_session_cannot_be_hijacked_by_different_user(): void
    {
        // User A starts a merge challenge, session cookie keeps the
        // secondary id, then User B logs in on the same browser.
        // User B must not be able to ride that session into preview/confirm.
        $userA   = $this->makeUser(['email' => 'a@example.com']);
        $userB   = $this->makeUser(['email' => 'b@example.com']);
        $victim  = $this->makeUser(['email' => 'v@example.com']);

        $this->withSession([
            'merge_secondary_id' => $victim->id,
            'merge_primary_id'   => $userA->id,
        ])->actingAs($userB)
          ->get('/user/merge/preview')
          ->assertRedirect(route('user.merge.start'));

        $this->withSession([
            'merge_secondary_id' => $victim->id,
            'merge_primary_id'   => $userA->id,
        ])->actingAs($userB)
          ->post('/user/merge/confirm', ['keep_plan_from' => 'primary'])
          ->assertRedirect(route('user.merge.start'));

        // Victim must still exist and own its identifiers.
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_oauth_merge_callback_refuses_when_session_unauthenticated(): void
    {
        // User A starts a social merge challenge, then their auth
        // session expires (or they sign out) before the provider
        // redirects back. The callback must not set merge_secondary_id
        // or allow a freshly-logged-in User B to inherit the in-flight
        // merge.
        config(['app.url' => 'http://localhost']);
        putenv('TWITTER_CLIENT_ID=cid');
        putenv('TWITTER_CLIENT_SECRET=csecret');

        $victim = $this->makeUser();
        LinkedIdentifier::create([
            'user_id'     => $victim->id,
            'kind'        => 'social',
            'value'       => LinkedIdentifier::normalize('social', '', 'twitter', '999'),
            'provider'    => 'twitter',
            'external_id' => '999',
            'verified_at' => now(),
        ]);

        // No actingAs() — guest session.
        $resp = $this->withSession([
            'social_oauth_state_twitter' => 'abc',
            'social_oauth_mode_twitter'  => 'merge',
        ])->get('/user/social-oauth/twitter/callback?state=abc&code=xyz');

        $resp->assertRedirect(route('user.login'));
        $this->assertNull(session('merge_secondary_id'));
        $this->assertNull(session('merge_primary_id'));
        $this->assertDatabaseHas('users', ['id' => $victim->id]);
    }

    public function test_oauth_callback_login_mode_signs_in_linked_user(): void
    {
        // Set the env vars + faked HTTP responses Twitter/X would return
        // and then walk through a callback as if the visitor had just
        // approved the OAuth consent screen. The visitor must be signed
        // in as the user the social identity is linked to.
        config(['app.url' => 'http://localhost']);
        $_ENV['TWITTER_CLIENT_ID']     = 'cid';
        $_ENV['TWITTER_CLIENT_SECRET'] = 'csecret';
        putenv('TWITTER_CLIENT_ID=cid');
        putenv('TWITTER_CLIENT_SECRET=csecret');

        Http::fake([
            'api.twitter.com/2/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
            'api.twitter.com/2/users/me*'    => Http::response(['data' => ['id' => '999', 'username' => 'jane']], 200),
        ]);

        $user = $this->makeUser();
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'social',
            'value'       => LinkedIdentifier::normalize('social', '', 'twitter', '999'),
            'provider'    => 'twitter',
            'external_id' => '999',
            'verified_at' => now(),
        ]);

        $resp = $this->withSession([
            'social_oauth_state_twitter' => 'abc',
            'social_oauth_mode_twitter'  => 'login',
        ])->get('/user/social-oauth/twitter/callback?state=abc&code=xyz');

        $resp->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_oauth_callback_login_mode_unknown_identity_redirects_to_login(): void
    {
        config(['app.url' => 'http://localhost']);
        putenv('TWITTER_CLIENT_ID=cid');
        putenv('TWITTER_CLIENT_SECRET=csecret');
        Http::fake([
            'api.twitter.com/2/oauth2/token' => Http::response(['access_token' => 'tok'], 200),
            'api.twitter.com/2/users/me*'    => Http::response(['data' => ['id' => 'nobody', 'username' => 'ghost']], 200),
        ]);

        $resp = $this->withSession([
            'social_oauth_state_twitter' => 'abc',
            'social_oauth_mode_twitter'  => 'login',
        ])->get('/user/social-oauth/twitter/callback?state=abc&code=xyz');

        $resp->assertRedirect(route('user.login'));
        $resp->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_oauth_callback_rejects_state_mismatch(): void
    {
        // No session state set — the callback must refuse rather than
        // attempt any token exchange or login.
        $resp = $this->get('/user/social-oauth/twitter/callback?state=bogus&code=xyz');
        $resp->assertRedirect();
        $this->assertGuest();
    }

    public function test_admin_role_cannot_be_merged_even_if_not_super_admin(): void
    {
        $primary = $this->makeUser(['email' => 'p@example.com']);
        // A non-super "admin" role should still be refused.
        $admin   = $this->makeUser(['email' => 'a@example.com', 'role' => 'admin']);
        $this->expectException(\RuntimeException::class);
        (new AccountMergeService())->merge($primary->fresh(), $admin->fresh(), 'primary');
    }

    public function test_unique_constraint_blocks_duplicate_link(): void
    {
        $a = $this->makeUser(['email' => 'one@example.com']);
        $b = $this->makeUser(['email' => 'two@example.com']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        LinkedIdentifier::create([
            'user_id' => $b->id, 'kind' => 'email', 'value' => 'one@example.com',
            'verified_at' => now(),
        ]);
    }
}
