<?php

namespace Tests\Feature;

use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CompanyEmailTemplate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) security boundary for the per-billing-company SMTP +
 * client-facing email-template editor exposed at
 * {@see \App\Modules\Api\Controllers\CompanyMailController}.
 *
 * The whole surface is owner-scoped: a creator may only read/edit the SMTP
 * transport and email-template overrides of a {@see BillingCompany} they own.
 * A regression here would let one creator reconfigure another creator's
 * outbound mail or rewrite the emails their clients receive, so every endpoint
 * must 404 on a company owned by someone else.
 *
 * Also locks down:
 *   - the password UX (blank keeps the stored secret, explicit clear resets it,
 *     the raw password is never returned — only a masked tail), and
 *   - the editable-key allow-list (template update/reset only accept the
 *     client-facing keys and 404 on anything else).
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the TouchSessionToken
 * middleware — every authed request would 500).
 */
class CompanyMailApiOwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private const EDITABLE_KEY = 'billing.client_invoice';
    private const EDITABLE_KEY_2 = 'billing.receipt';
    // A real registry key that is NOT in the editable allow-list.
    private const NON_EDITABLE_KEY = 'system.health_alert';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeCompany(User $owner, array $attrs = []): BillingCompany
    {
        return BillingCompany::create(array_merge([
            'user_id' => $owner->id,
            'name'    => 'Acme ' . Str::random(4),
            'email'   => 'biz' . Str::random(4) . '@ex.com',
        ], $attrs));
    }

    // ---------------------------------------------------------------
    // Owner scope: a creator can't touch another creator's company
    // ---------------------------------------------------------------

    public function test_smtp_endpoints_404_on_a_company_owned_by_someone_else(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();
        $company  = $this->makeCompany($owner);

        $token = $this->token($attacker);

        $this->withToken($token)
            ->getJson("/api/v1/billing/companies/{$company->id}/smtp")
            ->assertStatus(404);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled'  => true,
                'smtp_host'     => 'smtp.attacker.test',
                'smtp_password' => 'pwned',
            ])
            ->assertStatus(404);

        $this->withToken($token)
            ->postJson("/api/v1/billing/companies/{$company->id}/smtp/verify")
            ->assertStatus(404);

        $this->withToken($token)
            ->postJson("/api/v1/billing/companies/{$company->id}/smtp/test", [
                'test_email' => 'x@ex.com',
            ])
            ->assertStatus(404);

        // The victim's company was never mutated.
        $this->assertNull($company->fresh()->smtp_host);
        $this->assertFalse((bool) $company->fresh()->smtp_enabled);
    }

    public function test_email_template_endpoints_404_on_a_company_owned_by_someone_else(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();
        $company  = $this->makeCompany($owner);

        $token = $this->token($attacker);

        $this->withToken($token)
            ->getJson("/api/v1/billing/companies/{$company->id}/emails")
            ->assertStatus(404);

        $this->withToken($token)
            ->getJson("/api/v1/billing/companies/{$company->id}/emails/" . self::EDITABLE_KEY)
            ->assertStatus(404);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/emails/" . self::EDITABLE_KEY, [
                'subject' => 'Hijacked',
                'body'    => 'Pay me instead',
                'format'  => 'html',
            ])
            ->assertStatus(404);

        $this->withToken($token)
            ->deleteJson("/api/v1/billing/companies/{$company->id}/emails/" . self::EDITABLE_KEY)
            ->assertStatus(404);

        $this->withToken($token)
            ->postJson("/api/v1/billing/companies/{$company->id}/emails/" . self::EDITABLE_KEY . '/preview', [
                'subject' => 'Hijacked',
                'body'    => 'Pay me instead',
                'format'  => 'html',
            ])
            ->assertStatus(404);

        // No override leaked onto the victim's company.
        $this->assertDatabaseMissing('company_email_templates', [
            'billing_company_id' => $company->id,
        ]);
    }

    // ---------------------------------------------------------------
    // Password UX: blank keeps, clear resets, never returned raw
    // ---------------------------------------------------------------

    public function test_smtp_password_is_set_masked_and_never_returned_raw(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        // smtp_enabled stays false so the controller skips the live SMTP
        // handshake — we only care about how the password is persisted.
        $res = $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled'  => false,
                'smtp_host'     => 'smtp.acme.test',
                'smtp_username' => 'mailer@acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.masked_password', '••••••••t123');

        // The raw password (and its encrypted form) are never echoed back.
        $json = $res->json('data');
        $this->assertArrayNotHasKey('smtp_password', $json);
        $this->assertArrayNotHasKey('smtp_password_enc', $json);
        $this->assertArrayNotHasKey('password', $json);
        $res->assertDontSee('secret123');

        // The stored secret decrypts to what we sent.
        $stored = $company->fresh()->smtp_password_enc;
        $this->assertNotEmpty($stored);
        $this->assertSame('secret123', Crypt::decryptString($stored));
    }

    public function test_blank_smtp_password_leaves_the_stored_secret_untouched(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled'  => false,
                'smtp_host'     => 'smtp.acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertOk();

        $encBefore = $company->fresh()->smtp_password_enc;
        $this->assertNotEmpty($encBefore);

        // A subsequent save WITHOUT a password (e.g. only editing the host)
        // must keep the previously stored secret.
        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled' => false,
                'smtp_host'    => 'smtp.acme-2.test',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.smtp_host', 'smtp.acme-2.test');

        $this->assertSame(
            'secret123',
            Crypt::decryptString($company->fresh()->smtp_password_enc),
        );
    }

    public function test_clear_password_flag_resets_the_stored_secret(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled'  => false,
                'smtp_host'     => 'smtp.acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('data.has_password', true);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/smtp", [
                'smtp_enabled'        => false,
                'smtp_host'           => 'smtp.acme.test',
                'smtp_clear_password' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.has_password', false)
            ->assertJsonPath('data.masked_password', null);

        $this->assertNull($company->fresh()->smtp_password_enc);
    }

    // ---------------------------------------------------------------
    // Editable-key allow-list: only client-facing keys, 404 otherwise
    // ---------------------------------------------------------------

    public function test_template_update_accepts_editable_keys_and_persists_override(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        foreach ([self::EDITABLE_KEY, self::EDITABLE_KEY_2] as $key) {
            $this->withToken($token)
                ->putJson("/api/v1/billing/companies/{$company->id}/emails/{$key}", [
                    'subject' => 'Custom ' . $key,
                    'body'    => 'Hello from ' . $key,
                    'format'  => 'html',
                ])
                ->assertOk()
                ->assertJsonPath('data.override.subject', 'Custom ' . $key);

            $this->assertDatabaseHas('company_email_templates', [
                'billing_company_id' => $company->id,
                'template_key'       => $key,
            ]);
        }
    }

    public function test_template_update_404s_on_a_non_editable_key(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        $this->withToken($token)
            ->putJson("/api/v1/billing/companies/{$company->id}/emails/" . self::NON_EDITABLE_KEY, [
                'subject' => 'Nope',
                'body'    => 'Should not persist',
                'format'  => 'html',
            ])
            ->assertStatus(404);

        $this->assertDatabaseMissing('company_email_templates', [
            'billing_company_id' => $company->id,
            'template_key'       => self::NON_EDITABLE_KEY,
        ]);
    }

    public function test_template_reset_only_acts_on_editable_keys(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);
        $token   = $this->token($owner);

        // Seed an override on an editable key, then reset it.
        CompanyEmailTemplate::create([
            'billing_company_id' => $company->id,
            'template_key'       => self::EDITABLE_KEY,
            'subject'            => 'X',
            'body'               => 'Y',
            'format'             => 'html',
        ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/billing/companies/{$company->id}/emails/" . self::EDITABLE_KEY)
            ->assertOk()
            ->assertJsonPath('data.override', null);

        $this->assertDatabaseMissing('company_email_templates', [
            'billing_company_id' => $company->id,
            'template_key'       => self::EDITABLE_KEY,
        ]);

        // A reset against a non-editable key 404s and doesn't touch anything.
        $this->withToken($token)
            ->deleteJson("/api/v1/billing/companies/{$company->id}/emails/" . self::NON_EDITABLE_KEY)
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Auth gate
    // ---------------------------------------------------------------

    public function test_endpoints_require_authentication(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->getJson("/api/v1/billing/companies/{$company->id}/smtp")
            ->assertStatus(401);

        $this->getJson("/api/v1/billing/companies/{$company->id}/emails")
            ->assertStatus(401);
    }
}
