<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) parity for the in-app account-merge flow
 * (see {@see \Tests\Feature\AccountMergeTest} for the web/service side).
 *
 * Drives the real stateless HTTP path —
 *   challenge → verify → preview → confirm —
 * against a seeded primary + secondary user, using the dev-mode static
 * OTP code "123456". The proven secondary id rides between steps in an
 * APP_KEY-encrypted "merge token" instead of a session.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the
 * TouchSessionToken middleware — every authed request would 500).
 */
class AccountMergeApiTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_OTP = '123456';

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Mint a merge token exactly as the controller does (same APP_KEY
     * encryption + payload shape) so error paths can be exercised without
     * walking the full challenge/verify dance.
     */
    private function mintToken(int $primaryId, int $secondaryId, ?int $expTs = null): string
    {
        return Crypt::encryptString((string) json_encode([
            'p'   => $primaryId,
            's'   => $secondaryId,
            'exp' => $expTs ?? now()->addMinutes(15)->getTimestamp(),
        ]));
    }

    // ---------------------------------------------------------------
    // Happy path: challenge → verify → preview → confirm
    // ---------------------------------------------------------------

    public function test_full_merge_flow_moves_data_and_deletes_secondary(): void
    {
        $primary   = $this->makeUser(['email' => 'primary@example.com']);
        $secondary = $this->makeUser(['email' => 'secondary@example.com']);

        // Owned data on the secondary that must move to the primary.
        DB::table('projects')->insert([
            'user_id' => $secondary->id, 'name' => 'Moved Project',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = $this->token($primary);

        // Step 1 — challenge: sends the OTP to the OTHER account.
        $this->withToken($token)
            ->postJson('/api/v1/account/merge/challenge', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.kind', 'email');

        // Step 2 — verify: returns a merge token + preview.
        $verify = $this->withToken($token)
            ->postJson('/api/v1/account/merge/verify', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
                'code'  => self::DEV_OTP,
            ])
            ->assertOk();

        $mergeToken = $verify->json('data.merge_token');
        $this->assertNotEmpty($mergeToken);
        // Preview reflects the secondary's owned rows.
        $this->assertGreaterThan(0, $verify->json('data.preview.total_records'));
        $this->assertSame('secondary@example.com', $verify->json('data.preview.secondary.email'));

        // Step 3 — preview re-fetch from a still-valid token.
        $this->withToken($token)
            ->postJson('/api/v1/account/merge/preview', ['merge_token' => $mergeToken])
            ->assertOk()
            ->assertJsonPath('data.preview.primary.email', 'primary@example.com');

        // Step 4 — confirm: executes the merge.
        $confirm = $this->withToken($token)
            ->postJson('/api/v1/account/merge/confirm', [
                'merge_token'    => $mergeToken,
                'keep_plan_from' => 'primary',
            ])
            ->assertOk()
            ->assertJsonPath('data.merged', true);

        $this->assertGreaterThan(0, $confirm->json('data.records_moved'));

        // Secondary gone, its data + identifier now owned by the primary.
        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
        $this->assertDatabaseHas('projects', ['user_id' => $primary->id, 'name' => 'Moved Project']);
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $primary->id, 'value' => 'secondary@example.com',
        ]);

        // Step 5 — replayed confirm: secondary is gone, so the token no
        // longer resolves a second account → 404 (no double-merge).
        $this->withToken($token)
            ->postJson('/api/v1/account/merge/confirm', [
                'merge_token'    => $mergeToken,
                'keep_plan_from' => 'primary',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'user_not_found');
    }

    // ---------------------------------------------------------------
    // Token security: a leaked token can't be driven by another account
    // ---------------------------------------------------------------

    public function test_confirm_with_token_started_by_a_different_user_is_rejected(): void
    {
        $primary   = $this->makeUser();
        $secondary = $this->makeUser();
        $attacker  = $this->makeUser();

        // Token says the merge was started by $primary, but $attacker
        // presents it — the primary/auth mismatch must 403.
        $stolen = $this->mintToken($primary->id, $secondary->id);

        $this->withToken($this->token($attacker))
            ->postJson('/api/v1/account/merge/confirm', [
                'merge_token'    => $stolen,
                'keep_plan_from' => 'primary',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'merge_mismatch');

        // Nobody was merged.
        $this->assertDatabaseHas('users', ['id' => $secondary->id]);
    }

    public function test_expired_token_is_rejected(): void
    {
        $primary   = $this->makeUser();
        $secondary = $this->makeUser();

        $expired = $this->mintToken($primary->id, $secondary->id, now()->subMinute()->getTimestamp());

        $this->withToken($this->token($primary))
            ->postJson('/api/v1/account/merge/preview', ['merge_token' => $expired])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'merge_expired');

        $this->assertDatabaseHas('users', ['id' => $secondary->id]);
    }

    // ---------------------------------------------------------------
    // Self-merge / admin-merge are refused
    // ---------------------------------------------------------------

    public function test_challenge_refuses_self_merge(): void
    {
        $primary = $this->makeUser(['email' => 'me@example.com']);

        // The identifier already belongs to the signed-in user — there's
        // no "other account" to merge.
        $this->withToken($this->token($primary))
            ->postJson('/api/v1/account/merge/challenge', [
                'kind'  => 'email',
                'value' => 'me@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'merge_self');
    }

    public function test_verify_refuses_admin_account_merge(): void
    {
        $primary = $this->makeUser(['email' => 'primary@example.com']);
        $admin   = $this->makeUser(['email' => 'admin@example.com']);

        // Attach any user-pool role to the secondary — merging it would
        // risk privilege escalation via the data move.
        $roleId = DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        $this->assertNotNull($roleId, 'user-admin role must be seeded for this test');
        $admin->roles()->syncWithoutDetaching([$roleId]);
        $admin->flushPermissionCache();

        $token = $this->token($primary);

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/challenge', [
                'kind'  => 'email',
                'value' => 'admin@example.com',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/verify', [
                'kind'  => 'email',
                'value' => 'admin@example.com',
                'code'  => self::DEV_OTP,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'merge_admin');

        // Admin account untouched.
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ---------------------------------------------------------------
    // OTP gate + plan handling
    // ---------------------------------------------------------------

    public function test_verify_with_wrong_code_does_not_issue_token(): void
    {
        $primary   = $this->makeUser(['email' => 'primary@example.com']);
        $secondary = $this->makeUser(['email' => 'secondary@example.com']);

        $token = $this->token($primary);

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/challenge', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
            ])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/verify', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
                'code'  => '000000',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_otp');

        $this->assertDatabaseHas('users', ['id' => $secondary->id]);
    }

    public function test_confirm_keeps_secondary_paid_plan_when_chosen(): void
    {
        $paid = Plan::create([
            'name' => 'Pro', 'slug' => 'pro', 'monthly_price' => 10, 'annual_price' => 100,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);
        $primary   = $this->makeUser(['email' => 'primary@example.com', 'plan_id' => null]);
        $secondary = $this->makeUser([
            'email' => 'secondary@example.com', 'plan_id' => $paid->id,
            'plan_expires_at' => now()->addMonth(),
        ]);

        $token = $this->token($primary);

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/challenge', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
            ])
            ->assertOk();

        $mergeToken = $this->withToken($token)
            ->postJson('/api/v1/account/merge/verify', [
                'kind'  => 'email',
                'value' => 'secondary@example.com',
                'code'  => self::DEV_OTP,
            ])
            ->assertOk()
            ->json('data.merge_token');

        $this->withToken($token)
            ->postJson('/api/v1/account/merge/confirm', [
                'merge_token'    => $mergeToken,
                'keep_plan_from' => 'secondary',
            ])
            ->assertOk()
            ->assertJsonPath('data.kept_plan_from', 'secondary');

        $this->assertEquals($paid->id, $primary->fresh()->plan_id);
        $this->assertDatabaseMissing('users', ['id' => $secondary->id]);
    }

    // ---------------------------------------------------------------
    // Auth gate
    // ---------------------------------------------------------------

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/account/merge/challenge', [
            'kind' => 'email', 'value' => 'x@example.com',
        ])->assertStatus(401);
    }
}
