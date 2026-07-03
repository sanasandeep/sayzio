<?php

namespace Tests\Feature;

use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3500 — regression coverage for the read-only demo account
 * ({@see \App\Modules\Common\Middleware\BlockReadonlyDemoWrites}).
 *
 * The demo account's login + write-blocking behaviour (OTP login, web write
 * block with a flash message, API write block with the {error} envelope,
 * logout, and non-interference with normal accounts) had only ever been
 * verified manually via curl. Nothing guarded it, so a future change to auth,
 * middleware ordering, or the {@see \Database\Seeders\ReadonlyDemoAccountSeeder}
 * that stamps `is_readonly_demo = true` could silently break it.
 *
 * The guard keys on the `is_readonly_demo` flag — not a hardcoded email — so
 * these tests provision the flag directly (a fast stand-in for the full
 * showcase seeder, which builds a minutes-long content graph) and assert:
 *
 *   1. A read-only demo account can log in via OTP (password login is off for
 *      it in production) and then has its web writes short-circuited BEFORE
 *      any controller/model runs: the POST bounces back with the flash error
 *      and no row is persisted, while GETs still render.
 *   2. The same account's Sanctum-authenticated API writes return 403 with the
 *      `{error:{message,code:'demo_readonly'}}` envelope and persist nothing.
 *   3. Logout still works for the demo account, and a normal (non-demo)
 *      account is completely unaffected on both the web and API write paths.
 *
 * See memory: "OTP fixed dev code + curl login flow" (fixed 123456 in
 * non-prod) and "Sanctum API feature tests" (real token, not actingAs).
 */
class ReadonlyDemoWriteGuardTest extends TestCase
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

    private function makeUser(bool $readonlyDemo): User
    {
        $user = User::create([
            'name'              => 'U ' . Str::random(4),
            'email'             => 'u' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('secret-pass'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
            'is_readonly_demo'  => $readonlyDemo,
        ]);
        // Owns a personal workspace so `workspace.can:links.create` passes for
        // the non-demo control path (owner status grants every permission).
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /**
     * Drive the real web OTP login flow end to end (send-otp → verify-otp).
     * In non-production the code is the fixed `123456`, so no DB/log peek is
     * needed. Mirrors the production reality that password login is disabled
     * for this account, leaving OTP as the only way in.
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

    public function test_readonly_demo_can_log_in_via_otp(): void
    {
        // Login is an allowlisted auth action, never treated as a "save",
        // so the demo account can still authenticate normally.
        $this->loginViaOtp($this->makeUser(true));
    }

    public function test_readonly_demo_web_write_is_blocked_with_flash_and_persists_nothing(): void
    {
        $demo = $this->makeUser(true);
        $this->loginViaOtp($demo);

        // A create that would otherwise persist a url link.
        $this->post('/user/links', [
            'type'     => 'url',
            'long_url' => 'https://example.com/demo-should-not-save',
        ])
            ->assertRedirect()
            ->assertSessionHas('error', self::BLOCK_MESSAGE);

        $this->assertSame(
            0,
            Link::where('user_id', $demo->id)->count(),
            'the read-only demo account must not persist any link'
        );
    }

    public function test_readonly_demo_get_requests_still_render(): void
    {
        $demo = $this->makeUser(true);
        $this->loginViaOtp($demo);

        // GET/HEAD always pass through so every editor/settings screen still
        // renders fully (looking editable) for this account.
        $this->get('/user/links')->assertOk();
    }

    public function test_readonly_demo_can_use_allowlisted_non_persisting_interactive_post(): void
    {
        $demo = $this->makeUser(true);
        $this->loginViaOtp($demo);

        // The standalone QR generator (user.qrcode.download) is an interactive
        // POST that only renders an image — it writes nothing and charges no
        // coins — so it is allowlisted for the demo. It must run the controller
        // and return the SVG, not be short-circuited with the block flash.
        // (SVG avoids the imagick-only PNG backend so this holds anywhere.)
        $this->post('/user/qrcode', [
            'url'    => 'https://example.com/demo-qr',
            'format' => 'svg',
        ])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSessionHasNoErrors();
    }

    public function test_readonly_demo_ai_generation_write_is_still_blocked(): void
    {
        $demo = $this->makeUser(true);
        $this->loginViaOtp($demo);

        // The interactive allowlist is deliberately narrow: AI-*generation*
        // surfaces (here the artistic QR) persist rows and/or charge real coins
        // from the shared demo wallet, so they must stay blocked even though
        // other interactive POSTs are now allowed.
        $this->post('/user/qr-codes/generate-art', ['prompt' => 'a neon fox'])
            ->assertRedirect()
            ->assertSessionHas('error', self::BLOCK_MESSAGE);
    }

    public function test_readonly_demo_logout_still_works(): void
    {
        $demo = $this->makeUser(true);
        $this->loginViaOtp($demo);

        // Logout is allowlisted (not a "save"), so it must sign the account
        // out normally rather than being blocked as a write.
        $this->post(route('user.logout'))->assertRedirect();
        $this->assertGuest();
    }

    public function test_readonly_demo_api_write_returns_403_envelope_and_persists_nothing(): void
    {
        $demo  = $this->makeUser(true);
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
            'the read-only demo API caller must not persist any link'
        );
    }

    public function test_normal_account_web_write_is_unaffected(): void
    {
        $user = $this->makeUser(false);

        $this->actingAs($user)->post('/user/links', [
            'type'     => 'url',
            'long_url' => 'https://example.com/normal-should-save',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            Link::where('user_id', $user->id)->count(),
            'a normal account must still be able to create links on the web'
        );
    }

    public function test_normal_account_api_write_is_unaffected(): void
    {
        $user  = $this->makeUser(false);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/links', [
                'type'     => 'short',
                'long_url' => 'https://example.com/normal-api-should-save',
            ])
            ->assertStatus(201);

        $this->assertSame(
            1,
            Link::where('user_id', $user->id)->count(),
            'a normal account must still be able to create links via the API'
        );
    }
}
