<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The "claim your link" flow spans five surfaces (hero form → open-auth
 * event → header Alpine x-data → auth-modal hidden field →
 * AuthController::applyClaimedHandle, plus the standalone register ?handle=
 * path). A regression in any one silently drops the handle, and because
 * applyClaimedHandle fails gracefully (skips on any validation error) a
 * break would be invisible — the user just wouldn't get their handle.
 *
 * This pins the controller end of the wiring: a desired_handle submitted to
 * POST user.register.submit must land (lowercased) on the new account, while
 * an empty / already-taken / banned value is silently skipped without
 * breaking the sign-up. It also pins that the standalone register page
 * renders the hidden desired_handle field from ?handle=.
 *
 * See memory "Marketing hero claim-handle handoff".
 */
class ClaimedHandleSurvivesRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** A banned handle within the regex/length limits so NotBannedName is reached. */
    private const BANNED_HANDLE = 'reservedhandle';

    protected function setUp(): void
    {
        parent::setUp();
        // The register page uses @vite; swap it for a no-op so the GET page
        // renders without a built manifest in the test environment.
        $this->withoutVite();

        // Registration assigns the default (free) plan to the new user.
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    /**
     * Default auth mode is OTP (password disabled), so registration only
     * requires name + email. The handle is applied before the OTP/password
     * branch, so the post-create handle assertion holds regardless of mode.
     */
    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name'  => 'Claimer ' . Str::random(4),
            // register() lowercases the stored email, so keep the generated
            // local part lowercase (Str::random can emit uppercase) or the
            // case-sensitive User::where('email', ...) lookups below miss it.
            'email' => 'claimer' . Str::lower(Str::random(8)) . '@example.com',
        ], $overrides);
    }

    private function seedBannedHandle(string $name = self::BANNED_HANDLE): void
    {
        BannedName::firstOrCreate(['name' => $name]);
        // Drop the 5-minute cached lookup so the rule sees the row now.
        BannedNameChecker::flush($name);
    }

    public function test_claimed_handle_survives_to_the_new_account_lowercased(): void
    {
        $payload = $this->registerPayload(['desired_handle' => 'MyHandle']);

        $this->post('/user/register', $payload)
            ->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user, 'the new account must exist');
        $this->assertSame('myhandle', $user->handle);
    }

    public function test_empty_handle_still_registers_without_setting_a_handle(): void
    {
        $payload = $this->registerPayload(['desired_handle' => '']);

        $this->post('/user/register', $payload)
            ->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user, 'the new account must exist');
        $this->assertNull($user->handle);
    }

    public function test_already_taken_handle_is_skipped_but_signup_still_succeeds(): void
    {
        // An existing account already owns the desired handle.
        $owner = User::create([
            'name'     => 'Owner',
            'email'    => 'owner' . Str::random(6) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'takenhandle',
        ]);

        $payload = $this->registerPayload(['desired_handle' => 'takenhandle']);

        $this->post('/user/register', $payload)
            ->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user, 'the new account must still be created');
        $this->assertNull($user->handle, 'a taken handle must be skipped, not assigned');
        // The original owner keeps the handle untouched.
        $this->assertSame('takenhandle', $owner->fresh()->handle);
    }

    public function test_banned_handle_is_skipped_and_is_not_fatal(): void
    {
        $this->seedBannedHandle();

        $payload = $this->registerPayload(['desired_handle' => self::BANNED_HANDLE]);

        $this->post('/user/register', $payload)
            ->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $payload['email'])->first();
        $this->assertNotNull($user, 'the new account must still be created');
        $this->assertNull($user->handle, 'a banned handle must be skipped, not assigned');
        $this->assertDatabaseMissing('users', ['handle' => self::BANNED_HANDLE]);
    }

    public function test_register_page_renders_hidden_desired_handle_from_query(): void
    {
        $this->get('/user/register?handle=fromquery')
            ->assertOk()
            ->assertSee('name="desired_handle"', false)
            ->assertSee('value="fromquery"', false);
    }
}
