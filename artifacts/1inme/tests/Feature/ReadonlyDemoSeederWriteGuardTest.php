<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Database\Seeders\ReadonlyDemoAccountSeeder;
use Database\Seeders\ShowcaseAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #3503 — end-to-end guard for the public read-only demo account
 * (demo@sayzio.app).
 *
 * Two independent test files already exist:
 *
 *   - {@see ReadonlyDemoSeederNoPrivilegeTest} proves the account, *as
 *     provisioned by the seeder*, is privilege-free (no Admin bridge, no
 *     user-admin role) and carries `is_readonly_demo = true`.
 *   - {@see ReadonlyDemoWriteGuardTest} proves the
 *     {@see \App\Modules\Common\Middleware\BlockReadonlyDemoWrites} middleware
 *     blocks web + API writes for *any* account flagged `is_readonly_demo`
 *     (it provisions the flag directly, bypassing the seeder).
 *
 * Neither verifies the two work together: that the account the seeder
 * ACTUALLY provisions is the one the middleware then blocks. A future change
 * to the seeder's flag wiring or to middleware ordering could open a gap that
 * only shows up when both are exercised as one flow. This test closes that
 * gap: it provisions the account via the real seeder's account-provisioning
 * path, logs in through the real OTP flow, and confirms a representative web
 * POST and API POST are both blocked and persist nothing.
 *
 * Like {@see ReadonlyDemoSeederNoPrivilegeTest}, it drives only the seeder's
 * account-provisioning path via reflection — the full showcase content graph
 * takes minutes to build, far past the test budget, and the write-guard
 * behaviour depends only on the user row the provisioning path stamps.
 *
 * See memory: "OTP fixed dev code + curl login flow" (fixed 123456 in
 * non-prod) and "Sanctum API feature tests" (real token, not actingAs).
 */
class ReadonlyDemoSeederWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_MESSAGE = "This is a demo — changes aren't saved.";

    protected function setUp(): void
    {
        parent::setUp();
        // Auth/login views use @vite; swap it for a no-op so any rendered
        // page works without a built manifest in the test environment.
        $this->withoutVite();
    }

    /**
     * Run only the account-provisioning path of the read-only demo seeder —
     * the first two steps of its `run()`: `ensureUser($plan)` then
     * `ensureAdminBridge($user)` (the subclass no-op). This is the entire
     * surface where the demo account's user row (incl. the
     * `is_readonly_demo` flag) is decided, and it avoids the minutes-long
     * content graph the rest of `run()` builds.
     */
    private function provisionDemoAccount(): User
    {
        $seeder = new ReadonlyDemoAccountSeeder();

        // `ensureUser` is a private base method reused unchanged by both
        // seeders — resolve it from its declaring class explicitly.
        $ensureUser = new \ReflectionMethod(ShowcaseAccountSeeder::class, 'ensureUser');
        $ensureUser->setAccessible(true);
        /** @var User $user */
        $user = $ensureUser->invoke($seeder, $this->unlimitedPlan());

        // `ensureAdminBridge` is protected so reflection dispatches to the
        // subclass override (a no-op that also strips any stale Admin row).
        $ensureAdminBridge = new \ReflectionMethod($seeder, 'ensureAdminBridge');
        $ensureAdminBridge->setAccessible(true);
        $ensureAdminBridge->invoke($seeder, $user);

        // The seeder's full run() gives the account a personal workspace; do
        // the same so the write path reaches the guard exactly as it would in
        // production (owner status grants every workspace permission).
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /**
     * The `unlimited` comp plan the showcase seeders bind their account to.
     * `ensureUser()` sets `plan_id` to it, so provisioning requires it.
     */
    private function unlimitedPlan(): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => 'unlimited'],
            ['name' => 'Unlimited', 'status' => 'active', 'is_internal' => true]
        );
    }

    /**
     * Drive the real web OTP login flow end to end (send-otp → verify-otp).
     * In non-production the code is the fixed `123456`. Mirrors the production
     * reality that password login is disabled for this account, leaving OTP
     * as the only way in.
     */
    private function loginViaOtp(User $user): void
    {
        $this->post('/user/send-otp', [
            'identifier' => $user->email,
            'type'       => 'email',
        ])->assertRedirect(route('user.otp.verify.form'));

        $this->post('/user/verify-otp', ['code' => '123456']);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_seeded_demo_account_is_blocked_on_web_and_api_writes_end_to_end(): void
    {
        $demo = $this->provisionDemoAccount();

        // Sanity: this is genuinely the seeder-provisioned demo account with
        // the flag the guard keys on — not a hand-stamped stand-in.
        $this->assertSame(ReadonlyDemoAccountSeeder::EMAIL, $demo->email);
        $this->assertTrue(
            (bool) $demo->is_readonly_demo,
            'The seeder must provision the account with is_readonly_demo = true.'
        );

        // 1. Web write path: log in via OTP, then a create that would
        //    otherwise persist a link is short-circuited with the flash error.
        $this->loginViaOtp($demo);

        $this->post('/user/links', [
            'type'     => 'url',
            'long_url' => 'https://example.com/demo-should-not-save',
        ])
            ->assertRedirect()
            ->assertSessionHas('error', self::BLOCK_MESSAGE);

        $this->assertSame(
            0,
            Link::where('user_id', $demo->id)->count(),
            'the seeded read-only demo account must not persist any link on the web'
        );

        // 2. API write path: a Sanctum-authenticated write returns the 403
        //    {error} envelope and persists nothing.
        $token = $demo->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/links', [
                'type'     => 'short',
                'long_url' => 'https://example.com/api-demo-should-not-save',
            ])
            ->assertStatus(403)
            ->assertJson([
                'error' => [
                    'message' => self::BLOCK_MESSAGE,
                    'code'    => 'demo_readonly',
                ],
            ]);

        $this->assertSame(
            0,
            Link::where('user_id', $demo->id)->count(),
            'the seeded read-only demo API caller must not persist any link'
        );
    }
}
