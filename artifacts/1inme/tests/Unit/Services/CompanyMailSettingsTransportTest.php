<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\CompanyMailSettings;
use Tests\TestCase;

/**
 * Unit coverage for the transport-selection contract that keeps a creator's
 * client-facing invoices/receipts sending even when their per-company SMTP is
 * off or half-configured.
 *
 * {@see CompanyMailSettings::emailOpts()} is the single decision point: when a
 * company has SMTP disabled — or enabled but with no host — it must hand back a
 * 'system' transport label and NOTHING else, so the central Emailer pipeline
 * falls back to the platform mailer instead of trying (and failing) to build a
 * broken transport. Only a fully configured company yields its own mailer +
 * credentials + "from".
 *
 * Built in-memory (no DB) so the fallback branches can't silently regress
 * behind a slow integration boot.
 */
class CompanyMailSettingsTransportTest extends TestCase
{
    private function company(array $attrs): BillingCompany
    {
        $c = new BillingCompany();
        $c->forceFill(array_merge([
            'name'  => 'Acme Studio',
            'email' => 'studio@acme.test',
        ], $attrs));
        $c->id = $attrs['id'] ?? 42;
        return $c;
    }

    public function test_smtp_disabled_falls_back_to_platform_transport(): void
    {
        // Disabled even though a host happens to be present.
        $c = $this->company([
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.acme.test',
        ]);

        $settings = CompanyMailSettings::for($c);
        $opts     = $settings->emailOpts();

        $this->assertFalse($settings->isConfigured());
        $this->assertSame(['transport_label' => 'system'], $opts);
        $this->assertArrayNotHasKey('mailer', $opts);
        $this->assertArrayNotHasKey('mailer_config', $opts);
        $this->assertArrayNotHasKey('from', $opts);
    }

    public function test_enabled_but_missing_host_falls_back_to_platform_transport(): void
    {
        // Enabled but the host is unusable in every empty-ish form.
        foreach ([null, '', '   '] as $host) {
            $c        = $this->company([
                'smtp_enabled' => true,
                'smtp_host'    => $host,
            ]);
            $settings = CompanyMailSettings::for($c);

            $this->assertFalse(
                $settings->isConfigured(),
                'host=' . var_export($host, true) . ' should not be configured'
            );
            $this->assertSame(
                ['transport_label' => 'system'],
                $settings->emailOpts(),
                'host=' . var_export($host, true) . ' must fall back to platform transport'
            );
        }
    }

    public function test_fully_configured_uses_company_transport_with_from_and_credentials(): void
    {
        $c = $this->company([
            'id'                => 7,
            'smtp_enabled'      => true,
            'smtp_host'         => 'smtp.acme.test',
            'smtp_port'         => 2525,
            'smtp_encryption'   => 'tls',
            'smtp_username'     => 'apikey',
            'smtp_from_address' => 'billing@acme.test',
            'smtp_from_name'    => 'Acme Billing',
        ]);
        $settings = CompanyMailSettings::for($c);
        $settings->setPassword('s3cret-pw');

        $this->assertTrue($settings->isConfigured());

        $opts = $settings->emailOpts();

        $this->assertSame('company_smtp_7', $opts['mailer']);
        $this->assertSame('company:7', $opts['transport_label']);
        $this->assertSame(['address' => 'billing@acme.test', 'name' => 'Acme Billing'], $opts['from']);

        $cfg = $opts['mailer_config'];
        $this->assertSame('smtp', $cfg['transport']);
        $this->assertSame('smtp.acme.test', $cfg['host']);
        $this->assertSame(2525, $cfg['port']);
        $this->assertSame('smtp', $cfg['scheme']);
        $this->assertSame('apikey', $cfg['username']);
        // The stored password is encrypted at rest and decrypted into the config.
        $this->assertSame('s3cret-pw', $cfg['password']);
    }

    public function test_ssl_encryption_uses_smtps_scheme_and_default_port(): void
    {
        $c = $this->company([
            'smtp_enabled'    => true,
            'smtp_host'       => 'smtp.acme.test',
            'smtp_port'       => 0, // unset -> default by encryption
            'smtp_encryption' => 'ssl',
        ]);
        $cfg = CompanyMailSettings::for($c)->emailOpts()['mailer_config'];

        $this->assertSame('smtps', $cfg['scheme']);
        $this->assertSame(465, $cfg['port']);
    }

    public function test_invalid_encryption_and_unset_port_default_to_tls_587(): void
    {
        $c = $this->company([
            'smtp_enabled'    => true,
            'smtp_host'       => 'smtp.acme.test',
            'smtp_port'       => 0,
            'smtp_encryption' => 'bogus',
        ]);
        $cfg = CompanyMailSettings::for($c)->emailOpts()['mailer_config'];

        $this->assertSame('smtp', $cfg['scheme']);
        $this->assertSame(587, $cfg['port']);
    }

    public function test_from_falls_back_to_company_email_and_name(): void
    {
        $c = $this->company([
            'id'                => 9,
            'smtp_enabled'      => true,
            'smtp_host'         => 'smtp.acme.test',
            'smtp_from_address' => null,
            'smtp_from_name'    => null,
            'email'             => 'studio@acme.test',
            'name'              => 'Acme Studio',
        ]);

        $from = CompanyMailSettings::for($c)->emailOpts()['from'];

        $this->assertSame(['address' => 'studio@acme.test', 'name' => 'Acme Studio'], $from);
    }
}
