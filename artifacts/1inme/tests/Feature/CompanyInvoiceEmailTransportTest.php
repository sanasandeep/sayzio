<?php

namespace Tests\Feature;

use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\ClientInvoiceService;
use App\Services\Billing\CompanyEmailTemplateSettings;
use App\Services\Billing\CompanyMailSettings;
use App\Services\Integrations\EmailTemplateSettings;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage that a creator's client-facing invoice/receipt emails
 * keep sending — through the right transport, with the right template — no
 * matter how their per-company SMTP is configured.
 *
 * The transport label and the rendered body are recorded on the email_logs row
 * the Emailer pipeline always writes (regardless of whether the underlying
 * transport actually delivers), so the assertions read that row rather than the
 * mail fake (Mail::fake() does not record raw()/html() sends — see the
 * Emailer/MailFake notes).
 *
 * Guards against a future change to Emailer / CompanyMailSettings /
 * CompanyEmailTemplateSettings silently breaking the SMTP fallback (which would
 * make client invoices fail to send) or the override precedence.
 */
class CompanyInvoiceEmailTransportTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'name'     => 'inv ' . Str::random(4),
            'email'    => 'inv' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function workspace(User $u): Workspace
    {
        return app(WorkspaceContext::class)->resolve($u);
    }

    /** @param array<string,mixed> $smtp */
    private function company(User $u, Workspace $ws, array $smtp = []): BillingCompany
    {
        $company = BillingCompany::create(array_merge([
            'user_id'      => $u->id,
            'workspace_id' => $ws->id,
            'name'         => 'Acme Studio',
            'email'        => 'studio@acme.test',
        ], $smtp));

        if (!empty($smtp['_password'])) {
            CompanyMailSettings::for($company)->setPassword($smtp['_password']);
            $company->save();
        }

        return $company;
    }

    private function invoice(User $u, Workspace $ws, ?BillingCompany $company): Invoice
    {
        $fy = InvoiceService::financialYearFor(now());

        return Invoice::create([
            'number'                   => 'INV/' . $fy . '/' . Str::upper(Str::random(6)),
            'financial_year'           => $fy,
            'seq'                      => random_int(100000, 999999),
            'kind'                     => 'client',
            'workspace_id'             => $ws->id,
            'user_id'                  => $u->id,
            'billing_company_id'       => $company?->id,
            'currency'                 => 'USD',
            'subtotal_minor'           => 5000,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 5000,
            'discount_minor'           => 0,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'line_items'               => [],
            'tax_breakdown'            => [],
            'status'                   => 'draft',
            'issued_at'                => now(),
            'recipient_email'          => 'client@ex.com',
        ]);
    }

    private function latestLog(string $key = 'billing.client_invoice'): EmailLog
    {
        $log = EmailLog::where('email_key', $key)->latest('id')->first();
        $this->assertNotNull($log, "Expected an email_logs row for {$key}");
        return $log;
    }

    public function test_configured_company_smtp_sends_through_company_transport(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled'      => true,
            'smtp_host'         => 'smtp.acme.test',
            'smtp_port'         => 2525,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => 'apikey',
            'smtp_from_address' => 'billing@acme.test',
            'smtp_from_name'    => 'Acme Billing',
            '_password'         => 's3cret-pw',
        ]);
        $invoice = $this->invoice($u, $ws, $company);

        app(ClientInvoiceService::class)->markSent($invoice);

        $log = $this->latestLog();
        $this->assertSame('company:' . $company->id, $log->meta['transport'] ?? null);
        $this->assertSame(['address' => 'billing@acme.test', 'name' => 'Acme Billing'], $log->meta['from'] ?? null);
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_configured_company_send_wires_the_companys_own_smtp_server_into_the_pipeline(): void
    {
        // The transport *label* alone doesn't prove the message would actually
        // leave through the company's server — that requires the live pipeline
        // to register a mailer pointed at the company's host/port/credentials.
        // This asserts the runtime mail config the Emailer builds during a real
        // markSent(), bridging emailOpts() (unit-tested in isolation) to the
        // send path the controller/API/recurring auto-send all use.
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled'      => true,
            'smtp_host'         => 'smtp.acme.test',
            'smtp_port'         => 2525,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => 'apikey',
            'smtp_from_address' => 'billing@acme.test',
            'smtp_from_name'    => 'Acme Billing',
            '_password'         => 's3cret-pw',
        ]);
        $invoice = $this->invoice($u, $ws, $company);

        app(ClientInvoiceService::class)->markSent($invoice);

        $mailer = config('mail.mailers.company_smtp_' . $company->id);
        $this->assertIsArray($mailer, 'The company SMTP mailer should be registered at send time.');
        $this->assertSame('smtp', $mailer['transport'] ?? null);
        $this->assertSame('smtp.acme.test', $mailer['host'] ?? null);
        $this->assertSame(2525, $mailer['port'] ?? null);
        $this->assertSame('smtp', $mailer['scheme'] ?? null);
        $this->assertSame('apikey', $mailer['username'] ?? null);
        // The password is stored Crypt-encrypted but handed to the live transport
        // in clear, so a real send would authenticate against the company server.
        $this->assertSame('s3cret-pw', $mailer['password'] ?? null);

        // And the log still confirms this exact transport carried the message.
        $this->assertSame('company:' . $company->id, $this->latestLog()->meta['transport'] ?? null);
    }

    public function test_disabled_company_smtp_falls_back_to_platform_transport(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.acme.test',
        ]);
        $invoice = $this->invoice($u, $ws, $company);

        app(ClientInvoiceService::class)->markSent($invoice);

        // 'system' transport label => platform MailSettings mailer, no per-company mailer.
        $this->assertSame('system', $this->latestLog()->meta['transport'] ?? null);
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_enabled_company_smtp_without_host_falls_back_to_platform_transport(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled' => true,
            'smtp_host'    => null,
        ]);
        $invoice = $this->invoice($u, $ws, $company);

        app(ClientInvoiceService::class)->markSent($invoice);

        $this->assertSame('system', $this->latestLog()->meta['transport'] ?? null);
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_invoice_without_company_sends_through_platform_transport(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $invoice = $this->invoice($u, $ws, null);

        app(ClientInvoiceService::class)->markSent($invoice);

        // No billing company => companyEmailOpts() returns [], so no transport
        // label is attached and the platform default mailer is used.
        $this->assertArrayNotHasKey('transport', $this->latestLog()->meta ?? []);
        $this->assertSame('sent', $invoice->fresh()->status);
    }

    public function test_company_template_override_wins_over_admin_override_and_registry(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled' => false,
            'smtp_host'    => null,
        ]);

        // Admin/global override is present...
        EmailTemplateSettings::put('billing.client_invoice', 'Admin subject', 'ADMIN-OVERRIDE-BODY', 'html');
        // ...but the creator's per-company override must win over it (and over
        // the registry's Blade view default).
        CompanyEmailTemplateSettings::put(
            $company->id,
            'billing.client_invoice',
            'Company subject {{invoice_number}}',
            'COMPANY-OVERRIDE-BODY for {{invoice_number}}',
            'html'
        );

        $invoice = $this->invoice($u, $ws, $company);
        app(ClientInvoiceService::class)->markSent($invoice);

        $log = $this->latestLog();
        $this->assertStringContainsString('COMPANY-OVERRIDE-BODY', (string) $log->body);
        $this->assertStringContainsString($invoice->number, (string) $log->body);
        $this->assertStringNotContainsString('ADMIN-OVERRIDE-BODY', (string) $log->body);
    }

    public function test_admin_override_wins_over_registry_when_no_company_override(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled' => false,
            'smtp_host'    => null,
        ]);

        // Admin override present, NO company override for this key.
        EmailTemplateSettings::put('billing.client_invoice', 'Admin subject', 'ADMIN-ONLY-BODY {{invoice_number}}', 'html');

        $invoice = $this->invoice($u, $ws, $company);
        app(ClientInvoiceService::class)->markSent($invoice);

        $log = $this->latestLog();
        $this->assertStringContainsString('ADMIN-ONLY-BODY', (string) $log->body);
        $this->assertStringContainsString($invoice->number, (string) $log->body);
    }

    public function test_delivery_warning_links_to_the_affected_invoice(): void
    {
        $u  = $this->user();
        $ws = $this->workspace($u);
        $company = $this->company($u, $ws, [
            'smtp_enabled' => true,
            'smtp_host'    => 'smtp.acme.test',
        ]);
        $invoice = $this->invoice($u, $ws, $company);

        // Record a failed client-invoice send on this company's transport, as
        // the Emailer pipeline would after a real SMTP failure.
        EmailLog::create([
            'email_key'    => 'billing.client_invoice',
            'recipient'    => $invoice->recipient_email,
            'subject'      => 'Invoice ' . $invoice->number,
            'body'         => 'body',
            'status'       => 'failed',
            'user_id'      => $u->id,
            'related_type' => Invoice::class,
            'related_id'   => (string) $invoice->id,
            'meta'         => ['transport' => 'company:' . $company->id],
        ]);

        $warning = CompanyMailSettings::for($company)->deliveryWarning();

        $this->assertNotNull($warning);
        $this->assertSame('danger', $warning['level']);
        $this->assertArrayHasKey('link', $warning);
        $this->assertSame(
            route('user.client-invoices.edit', $invoice->id),
            $warning['link']['url']
        );
    }

    public function test_unverified_warning_has_no_invoice_link(): void
    {
        $u  = $this->user();
        $ws = $this->workspace($u);
        // Enabled + host but never verified and no recent client email => the
        // "not verified yet" warning, which has no concrete invoice to link to.
        $company = $this->company($u, $ws, [
            'smtp_enabled' => true,
            'smtp_host'    => 'smtp.acme.test',
        ]);

        $warning = CompanyMailSettings::for($company)->deliveryWarning();

        $this->assertNotNull($warning);
        $this->assertArrayNotHasKey('link', $warning);
    }

    public function test_platform_non_billing_emails_are_unaffected(): void
    {
        Mail::fake();
        $u  = $this->user();
        $ws = $this->workspace($u);
        // A configured company exists in the workspace, but a generic platform
        // email must not pick up any per-company transport/override.
        $this->company($u, $ws, [
            'smtp_enabled'  => true,
            'smtp_host'     => 'smtp.acme.test',
            'smtp_username' => 'apikey',
            '_password'     => 's3cret-pw',
        ]);

        Emailer::send('auth.otp_code', $u->email, [
            'code'        => '123456',
            'ttl_minutes' => '10',
        ], ['user' => $u->id]);

        $log = $this->latestLog('auth.otp_code');
        // No company transport bled into the platform email...
        $this->assertArrayNotHasKey('transport', $log->meta ?? []);
        // ...and it carries the registry default content.
        $this->assertStringContainsString('123456', (string) $log->body);
    }
}
