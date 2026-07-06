<?php

namespace Tests\Feature;

use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) parity for the in-app WhatsApp connect flow
 * ({@see \App\Modules\Api\Controllers\WhatsappController}), the mobile mirror
 * of OnboardingController::whatsappSend / whatsappVerify.
 *
 * These endpoints send + check OTP codes, so they are exactly the kind of
 * surface that gets probed. This suite confirms the abuse controls actually
 * hold:
 *   - the happy path links the number and flips has_whatsapp_number true,
 *   - a wrong code is rejected (no link, no false-positive),
 *   - the throttle:otp-send / throttle:otp-verify limiters trip,
 *   - a number already verified on another account can't be hijacked,
 *   - the OTP is interchangeable with the web onboarding tuple
 *     (type=mobile / purpose=link / guard=web),
 *   - the endpoints require authentication.
 *
 * Authenticated requests use a REAL personal access token, not
 * Sanctum::actingAs — the latter injects a mock that breaks the
 * TouchSessionToken middleware so every authed request would 500
 * (see AccountMergeApiTest for the same constraint).
 */
class WhatsappConnectApiTest extends TestCase
{
    use RefreshDatabase;

    /** Static dev OTP issued outside production by OtpService::generate(). */
    private const DEV_OTP = '123456';

    private const NUMBER = '+15551234567';

    // The OTP tuple shared by the web onboarding flow and this API.
    private const OTP_TYPE    = 'mobile';
    private const OTP_PURPOSE = 'link';
    private const OTP_GUARD   = 'web';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // Happy path
    // ---------------------------------------------------------------

    public function test_send_then_verify_links_number_and_flips_has_whatsapp(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->assertFalse($user->fresh()->hasWhatsappNumber());

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.mobile', self::NUMBER);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => self::DEV_OTP,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', true)
            ->assertJsonPath('data.mobile', self::NUMBER);

        $this->assertTrue($user->fresh()->hasWhatsappNumber());
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $user->id,
            'kind'    => 'phone',
            'value'   => self::NUMBER,
        ]);
    }

    /** Formatting noise normalises to the same stored value. */
    public function test_verify_normalises_formatted_number(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => '+1 (555) 123-4567'])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => '+1 (555) 123-4567',
                'code'   => self::DEV_OTP,
            ])
            ->assertOk();

        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $user->id,
            'kind'    => 'phone',
            'value'   => self::NUMBER,
        ]);
    }

    // ---------------------------------------------------------------
    // Wrong / missing code is rejected
    // ---------------------------------------------------------------

    public function test_verify_with_wrong_code_is_rejected_and_does_not_link(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => '000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_code');

        $this->assertFalse($user->fresh()->hasWhatsappNumber());
        $this->assertDatabaseMissing('linked_identifiers', [
            'kind'  => 'phone',
            'value' => self::NUMBER,
        ]);
    }

    public function test_verify_without_a_prior_send_is_rejected(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        // No code was ever issued for this number → nothing to match.
        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => self::DEV_OTP,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_code');

        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    // ---------------------------------------------------------------
    // Brute-force cap: the OTP row burns after MAX_ATTEMPTS bad guesses,
    // so even the correct code is then refused for that issued code.
    // ---------------------------------------------------------------

    public function test_repeated_wrong_codes_burn_the_issued_code(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertOk();

        // Exhaust the per-code attempt cap with wrong guesses (under the
        // otp-verify per-minute throttle ceiling of 8).
        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->withToken($token)
                ->postJson('/api/v1/me/whatsapp/verify', [
                    'mobile' => self::NUMBER,
                    'code'   => '000000',
                ])
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'invalid_code');
        }

        // The right code now no longer works — the row was burned.
        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => self::DEV_OTP,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_code');

        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    // ---------------------------------------------------------------
    // Throttling (abuse / spam controls)
    // ---------------------------------------------------------------

    public function test_send_is_throttled(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        // otp-send caps issuance at 3/min on the (empty) identifier key —
        // the whatsapp endpoint passes `mobile`, not `identifier`, so every
        // send shares that key. The 4th send in the window must 429.
        $status = null;
        for ($i = 0; $i < 6; $i++) {
            $status = $this->withToken($token)
                ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
                ->getStatusCode();
            if ($status === 429) {
                break;
            }
        }

        $this->assertSame(429, $status, 'otp-send throttle never triggered');
    }

    public function test_verify_is_throttled(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertOk();

        // otp-verify caps at 8/min on the (empty) identifier key; the 9th
        // attempt in the window must 429 (independent of the per-code cap).
        $status = null;
        for ($i = 0; $i < 12; $i++) {
            $status = $this->withToken($token)
                ->postJson('/api/v1/me/whatsapp/verify', [
                    'mobile' => self::NUMBER,
                    'code'   => '000000',
                ])
                ->getStatusCode();
            if ($status === 429) {
                break;
            }
        }

        $this->assertSame(429, $status, 'otp-verify throttle never triggered');
    }

    // ---------------------------------------------------------------
    // Hijack protection: a number verified on another account is refused
    // ---------------------------------------------------------------

    public function test_send_refuses_number_linked_to_another_account(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();

        LinkedIdentifier::create([
            'user_id'     => $owner->id,
            'kind'        => 'phone',
            'value'       => self::NUMBER,
            'verified_at' => now(),
            'is_primary'  => false,
        ]);

        $this->withToken($this->token($attacker))
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'number_in_use');

        // Ownership is unchanged.
        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $owner->id,
            'kind'    => 'phone',
            'value'   => self::NUMBER,
        ]);
        $this->assertFalse($attacker->fresh()->hasWhatsappNumber());
    }

    public function test_verify_refuses_number_linked_to_another_account_after_send(): void
    {
        $attacker = $this->makeUser();
        $token    = $this->token($attacker);

        // Attacker manages to get a code issued for a number...
        $code = (new OtpService())->generate(
            self::NUMBER, self::OTP_TYPE, self::OTP_PURPOSE, self::OTP_GUARD
        );

        // ...but it gets verified on a legitimate account before they finish.
        $owner = $this->makeUser();
        LinkedIdentifier::create([
            'user_id'     => $owner->id,
            'kind'        => 'phone',
            'value'       => self::NUMBER,
            'verified_at' => now(),
            'is_primary'  => false,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => $code,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'number_in_use');

        $this->assertFalse($attacker->fresh()->hasWhatsappNumber());
        $this->assertDatabaseMissing('linked_identifiers', [
            'user_id' => $attacker->id,
            'kind'    => 'phone',
            'value'   => self::NUMBER,
        ]);
    }

    // ---------------------------------------------------------------
    // OTP is interchangeable with the web onboarding flow
    // ---------------------------------------------------------------

    public function test_otp_issued_by_web_tuple_is_accepted_by_api_verify(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        // Simulate the web onboarding send step issuing a code under the
        // shared tuple (type=mobile / purpose=link / guard=web).
        $code = (new OtpService())->generate(
            self::NUMBER, self::OTP_TYPE, self::OTP_PURPOSE, self::OTP_GUARD
        );

        // The API verify endpoint accepts that same code → tuples match.
        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => $code,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', true);

        $this->assertTrue($user->fresh()->hasWhatsappNumber());
    }

    public function test_otp_issued_by_api_send_is_accepted_by_web_tuple_verify(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertOk();

        // The web onboarding verify path reads the very same tuple, so the
        // API-issued code validates there too.
        $accepted = (new OtpService())->verify(
            self::NUMBER, self::DEV_OTP, self::OTP_TYPE, self::OTP_PURPOSE, self::OTP_GUARD
        );

        $this->assertTrue($accepted, 'API-issued OTP was not accepted by the web tuple');
    }

    public function test_otp_issued_under_a_different_tuple_is_not_accepted(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        // A code issued for a DIFFERENT purpose must not unlock the WhatsApp
        // link — the API verify is bound to the (mobile/link/web) tuple.
        $code = (new OtpService())->generate(
            self::NUMBER, self::OTP_TYPE, 'login', self::OTP_GUARD
        );

        $this->withToken($token)
            ->postJson('/api/v1/me/whatsapp/verify', [
                'mobile' => self::NUMBER,
                'code'   => $code,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_code');

        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    // ---------------------------------------------------------------
    // Auth gate
    // ---------------------------------------------------------------

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/me/whatsapp/send', ['mobile' => self::NUMBER])
            ->assertStatus(401);

        $this->postJson('/api/v1/me/whatsapp/verify', [
            'mobile' => self::NUMBER,
            'code'   => self::DEV_OTP,
        ])->assertStatus(401);
    }
}
