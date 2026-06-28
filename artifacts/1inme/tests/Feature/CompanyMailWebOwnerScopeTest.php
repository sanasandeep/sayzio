<?php

namespace Tests\Feature;

use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CompanyEmailTemplate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web (session-guard) security boundary for the per-billing-company SMTP
 * settings on the company edit form ({@see \App\Modules\User\Controllers\BillingCompanyController})
 * and the client-facing email-template editor
 * ({@see \App\Modules\User\Controllers\CompanyEmailTemplateController}).
 *
 * These are the human-facing twins of the Sanctum API surface locked down by
 * {@see CompanyMailApiOwnerScopeTest}. Both paths share the underlying
 * CompanyMailSettings / CompanyEmailTemplateSettings services and the same
 * {@see BillingCompany} owner check, so the web routes deserve the same
 * regression guard: a signed-in creator must never be able to read or rewrite
 * the SMTP transport or client-facing emails of a company owned by someone
 * else. Every such attempt must 404 (the controller's authorizeOwn()).
 *
 * Also re-asserts, on the web path, the same password UX the API test covers:
 *   - a blank password keeps the stored secret,
 *   - the explicit "clear" checkbox resets it, and
 *   - the raw password is never echoed back — the edit form only shows a
 *     masked tail.
 *
 * The company edit/update routes sit behind `auth` + workspace permission
 * middleware; the company owner owns their resolved workspace and so bypasses
 * the permission gate, leaving authorizeOwn() as the real boundary.
 */
class CompanyMailWebOwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private const EDITABLE_KEY = 'billing.client_invoice';
    private const EDITABLE_KEY_2 = 'billing.receipt';
    // A real registry key that is NOT in the editable allow-list.
    private const NON_EDITABLE_KEY = 'system.health_alert';

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
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
    // Owner scope: a creator can't touch another creator's company SMTP
    // ---------------------------------------------------------------

    public function test_web_smtp_actions_404_on_a_company_owned_by_someone_else(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();
        $company  = $this->makeCompany($owner);

        $this->actingAs($attacker, 'web');

        // Viewing the edit form (which renders the SMTP section).
        $this->get(route('user.billing.companies.edit', $company))
            ->assertStatus(404);

        // Saving SMTP settings via the company update form.
        $this->put(route('user.billing.companies.update', $company), [
            'name'          => 'Hijacked Co',
            'smtp_enabled'  => '1',
            'smtp_host'     => 'smtp.attacker.test',
            'smtp_password' => 'pwned',
        ])->assertStatus(404);

        // The "verify connection" and "send test" actions.
        $this->post(route('user.billing.companies.smtp.verify', $company))
            ->assertStatus(404);

        $this->post(route('user.billing.companies.smtp.test', $company), [
            'test_email' => 'x@ex.com',
        ])->assertStatus(404);

        // Deleting the company.
        $this->delete(route('user.billing.companies.destroy', $company))
            ->assertStatus(404);

        // The victim's company was never mutated or removed.
        $fresh = $company->fresh();
        $this->assertNotNull($fresh, 'The victim company must not be deleted.');
        $this->assertNull($fresh->smtp_host);
        $this->assertFalse((bool) $fresh->smtp_enabled);
        $this->assertSame($company->name, $fresh->name);
    }

    public function test_web_email_template_editor_404s_on_a_company_owned_by_someone_else(): void
    {
        $owner    = $this->makeUser();
        $attacker = $this->makeUser();
        $company  = $this->makeCompany($owner);

        $this->actingAs($attacker, 'web');

        $this->get(route('user.billing.companies.emails.index', $company))
            ->assertStatus(404);

        $this->get(route('user.billing.companies.emails.edit', [$company, self::EDITABLE_KEY]))
            ->assertStatus(404);

        $this->put(route('user.billing.companies.emails.update', [$company, self::EDITABLE_KEY]), [
            'subject' => 'Hijacked',
            'body'    => 'Pay me instead',
            'format'  => 'html',
        ])->assertStatus(404);

        $this->delete(route('user.billing.companies.emails.reset', [$company, self::EDITABLE_KEY]))
            ->assertStatus(404);

        $this->post(route('user.billing.companies.emails.preview', [$company, self::EDITABLE_KEY]), [
            'subject' => 'Hijacked',
            'body'    => 'Pay me instead',
            'format'  => 'html',
        ])->assertStatus(404);

        // No override leaked onto the victim's company.
        $this->assertDatabaseMissing('company_email_templates', [
            'billing_company_id' => $company->id,
        ]);
    }

    // ---------------------------------------------------------------
    // Password UX on the web path: set/masked, blank keeps, clear resets
    // ---------------------------------------------------------------

    public function test_web_smtp_password_is_stored_encrypted_and_never_shown_raw(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        // smtp_enabled stays off so saving doesn't attempt a live handshake —
        // we only care about how the password is persisted.
        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.update', $company), [
                'name'          => $company->name,
                'smtp_enabled'  => '0',
                'smtp_host'     => 'smtp.acme.test',
                'smtp_username' => 'mailer@acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertRedirect(route('user.billing.companies.edit', $company));

        // Stored encrypted at rest and decrypts back to what we sent.
        $stored = $company->fresh()->smtp_password_enc;
        $this->assertNotEmpty($stored);
        $this->assertNotSame('secret123', $stored);
        $this->assertSame('secret123', Crypt::decryptString($stored));

        // The edit form only ever shows a masked tail, never the raw secret.
        $resp = $this->actingAs($owner, 'web')
            ->get(route('user.billing.companies.edit', $company))
            ->assertOk();

        $resp->assertSee('••••••••t123');
        $resp->assertDontSee('secret123');
    }

    public function test_web_blank_smtp_password_leaves_the_stored_secret_untouched(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.update', $company), [
                'name'          => $company->name,
                'smtp_enabled'  => '0',
                'smtp_host'     => 'smtp.acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertRedirect();

        $encBefore = $company->fresh()->smtp_password_enc;
        $this->assertNotEmpty($encBefore);

        // A subsequent save WITHOUT a password (e.g. only editing the host)
        // must keep the previously stored secret.
        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.update', $company), [
                'name'         => $company->name,
                'smtp_enabled' => '0',
                'smtp_host'    => 'smtp.acme-2.test',
            ])
            ->assertRedirect();

        $fresh = $company->fresh();
        $this->assertSame('smtp.acme-2.test', $fresh->smtp_host);
        $this->assertSame('secret123', Crypt::decryptString($fresh->smtp_password_enc));
    }

    public function test_web_clear_password_flag_resets_the_stored_secret(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.update', $company), [
                'name'          => $company->name,
                'smtp_enabled'  => '0',
                'smtp_host'     => 'smtp.acme.test',
                'smtp_password' => 'secret123',
            ])
            ->assertRedirect();

        $this->assertNotEmpty($company->fresh()->smtp_password_enc);

        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.update', $company), [
                'name'                => $company->name,
                'smtp_enabled'        => '0',
                'smtp_host'           => 'smtp.acme.test',
                'smtp_clear_password' => '1',
            ])
            ->assertRedirect();

        $this->assertNull($company->fresh()->smtp_password_enc);
    }

    // ---------------------------------------------------------------
    // Editable-key allow-list on the web path: only client-facing keys
    // ---------------------------------------------------------------

    public function test_web_template_update_accepts_editable_keys_and_persists_override(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->actingAs($owner, 'web');

        foreach ([self::EDITABLE_KEY, self::EDITABLE_KEY_2] as $key) {
            $this->put(route('user.billing.companies.emails.update', [$company, $key]), [
                'subject' => 'Custom ' . $key,
                'body'    => 'Hello from ' . $key,
                'format'  => 'html',
            ])->assertRedirect(route('user.billing.companies.emails.edit', [$company, $key]));

            $this->assertDatabaseHas('company_email_templates', [
                'billing_company_id' => $company->id,
                'template_key'       => $key,
            ]);
        }
    }

    public function test_web_template_update_404s_on_a_non_editable_key(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->actingAs($owner, 'web')
            ->put(route('user.billing.companies.emails.update', [$company, self::NON_EDITABLE_KEY]), [
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

    public function test_web_template_reset_only_acts_on_editable_keys(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        // Seed an override on an editable key, then reset it.
        CompanyEmailTemplate::create([
            'billing_company_id' => $company->id,
            'template_key'       => self::EDITABLE_KEY,
            'subject'            => 'X',
            'body'               => 'Y',
            'format'             => 'html',
        ]);

        $this->actingAs($owner, 'web')
            ->delete(route('user.billing.companies.emails.reset', [$company, self::EDITABLE_KEY]))
            ->assertRedirect(route('user.billing.companies.emails.edit', [$company, self::EDITABLE_KEY]));

        $this->assertDatabaseMissing('company_email_templates', [
            'billing_company_id' => $company->id,
            'template_key'       => self::EDITABLE_KEY,
        ]);

        // A reset against a non-editable key 404s and doesn't touch anything.
        $this->actingAs($owner, 'web')
            ->delete(route('user.billing.companies.emails.reset', [$company, self::NON_EDITABLE_KEY]))
            ->assertStatus(404);
    }

    // ---------------------------------------------------------------
    // Auth gate: guests are bounced to login, never served the page
    // ---------------------------------------------------------------

    public function test_web_endpoints_require_authentication(): void
    {
        $owner   = $this->makeUser();
        $company = $this->makeCompany($owner);

        $this->get(route('user.billing.companies.edit', $company))
            ->assertRedirect();

        $this->get(route('user.billing.companies.emails.index', $company))
            ->assertRedirect();
    }
}
