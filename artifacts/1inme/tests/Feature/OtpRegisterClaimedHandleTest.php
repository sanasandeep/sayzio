<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the claimed-handle handoff on the OTP/WhatsApp *sign-up* path
 * (Api\OtpController::register). The marketing hero's "claim your link" pill
 * carries a desired_handle through sign-up so it's reserved on the new
 * account. The email/password register flow was already covered; this pins the
 * OTP register path (the genuine WhatsApp sign-up channel), where the handle
 * is applied via the shared ClaimedHandle helper right after account creation.
 *
 * Validation mirrors the web register form: a valid handle is reserved, while
 * a taken/banned/malformed one is silently skipped so sign-up never dead-ends.
 */
class OtpRegisterClaimedHandleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    public function test_valid_claimed_handle_is_reserved_on_otp_signup(): void
    {
        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Hero Claimer',
            'identifier'     => 'claimer@example.com',
            'type'           => 'email',
            'desired_handle' => 'heroclaimer',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email'  => 'claimer@example.com',
            'handle' => 'heroclaimer',
        ]);
    }

    public function test_claimed_handle_is_lowercased_and_trimmed(): void
    {
        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Mixed Case',
            'identifier'     => 'mixed@example.com',
            'type'           => 'email',
            'desired_handle' => '  MixedCase ',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email'  => 'mixed@example.com',
            'handle' => 'mixedcase',
        ]);
    }

    public function test_signup_succeeds_without_a_claimed_handle(): void
    {
        $this->postJson('/api/v1/auth/otp/register', [
            'name'       => 'No Handle',
            'identifier' => 'nohandle@example.com',
            'type'       => 'email',
        ])->assertStatus(201);

        $user = User::where('email', 'nohandle@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->handle);
    }

    public function test_taken_handle_is_silently_skipped_and_signup_still_succeeds(): void
    {
        User::create([
            'name'     => 'Owner',
            'email'    => 'owner@example.com',
            'password' => Hash::make('secret-pass'),
            'status'   => 'active',
            'handle'   => 'taken',
        ]);

        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Late Comer',
            'identifier'     => 'late@example.com',
            'type'           => 'email',
            'desired_handle' => 'taken',
        ])->assertStatus(201);

        // Account is created but never grabs the already-taken handle.
        $user = User::where('email', 'late@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->handle);
        $this->assertSame(1, User::where('handle', 'taken')->count());
    }

    public function test_banned_handle_is_silently_skipped(): void
    {
        BannedName::firstOrCreate(['name' => 'admin']);

        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Sneaky',
            'identifier'     => 'sneaky@example.com',
            'type'           => 'email',
            'desired_handle' => 'admin',
        ])->assertStatus(201);

        $user = User::where('email', 'sneaky@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->handle);
    }

    public function test_malformed_handle_is_silently_skipped(): void
    {
        $this->postJson('/api/v1/auth/otp/register', [
            'name'           => 'Bad Chars',
            'identifier'     => 'badchars@example.com',
            'type'           => 'email',
            // Spaces/punctuation fail the /^[a-z0-9_]+$/i format rule.
            'desired_handle' => 'not a handle!',
        ])->assertStatus(201);

        $user = User::where('email', 'badchars@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->handle);
    }
}
