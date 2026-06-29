<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\CompanyMailSettings;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
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

    /**
     * The default test mailer is the array transport (MAIL_MAILER=array), so the
     * platform fallback path can be inspected without touching a real server.
     */
    private function platformArrayTransport(): ArrayTransport
    {
        $transport = Mail::mailer()->getSymfonyTransport();
        $this->assertInstanceOf(
            ArrayTransport::class,
            $transport,
            'Test env must use the array mailer so platform sends are inspectable.'
        );
        $transport->flush();
        return $transport;
    }

    public function test_sendraw_falls_back_to_platform_default_mailer_when_smtp_disabled(): void
    {
        $c = $this->company([
            // Disabled even though a host happens to be present.
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.acme.test',
        ]);

        $transport = $this->platformArrayTransport();

        $label = CompanyMailSettings::for($c)
            ->sendRaw('client@acme.test', 'Your portal access', 'Open the link');

        // Falls back to the platform mailer and reports the 'system' label so the
        // client-portal invite still goes out.
        $this->assertSame('system', $label);

        $messages = $transport->messages();
        $this->assertCount(1, $messages, 'The invite must send through the default platform mailer.');

        $email = $messages->first()->getOriginalMessage();
        $this->assertSame('client@acme.test', $email->getTo()[0]->getAddress());
        $this->assertSame('Your portal access', $email->getSubject());
    }

    public function test_sendraw_falls_back_to_platform_default_mailer_when_host_missing(): void
    {
        foreach ([null, '', '   '] as $host) {
            $c = $this->company([
                'smtp_enabled' => true,
                'smtp_host'    => $host,
            ]);

            $transport = $this->platformArrayTransport();

            $label = CompanyMailSettings::for($c)
                ->sendRaw('client@acme.test', 'Your portal access', 'Open the link');

            $this->assertSame(
                'system',
                $label,
                'host=' . var_export($host, true) . ' must fall back to the platform mailer'
            );
            $this->assertCount(
                1,
                $transport->messages(),
                'host=' . var_export($host, true) . ' must still deliver via the platform mailer'
            );
        }
    }

    public function test_sendraw_uses_company_transport_and_applies_from_identity(): void
    {
        $c = $this->company([
            'id'                => 7,
            'smtp_enabled'      => true,
            'smtp_host'         => 'smtp.acme.test',
            'smtp_username'     => 'apikey',
            'smtp_from_address' => 'billing@acme.test',
            'smtp_from_name'    => 'Acme Billing',
        ]);
        $settings = CompanyMailSettings::for($c);
        $settings->setPassword('s3cret-pw');

        // Capture the outgoing message and halt before the company SMTP transport
        // tries to open a (non-existent) connection — returning false from a
        // MessageSending listener cancels the actual delivery.
        $captured = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured) {
            $captured = $event->message;
            return false;
        });

        $label = $settings->sendRaw('client@acme.test', 'Your portal access', 'Open the link');

        $this->assertSame('company:7', $label);
        $this->assertNotNull($captured, 'The message must be dispatched through the company mailer.');

        $from = $captured->getFrom();
        $this->assertSame('billing@acme.test', $from[0]->getAddress());
        $this->assertSame('Acme Billing', $from[0]->getName());
        $this->assertSame('client@acme.test', $captured->getTo()[0]->getAddress());
    }

    public function test_sendraw_company_from_falls_back_to_company_email_and_name(): void
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

        $captured = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured) {
            $captured = $event->message;
            return false;
        });

        $label = CompanyMailSettings::for($c)
            ->sendRaw('client@acme.test', 'Your portal access', 'Open the link');

        $this->assertSame('company:9', $label);
        $this->assertNotNull($captured);

        $from = $captured->getFrom();
        $this->assertSame('studio@acme.test', $from[0]->getAddress());
        $this->assertSame('Acme Studio', $from[0]->getName());
    }

    // ------------------------------------------------------------------
    // deliveryWarning() decision core — exercised in-memory via the pure
    // evaluateDeliveryWarning() so the branch matrix has no DB dependency.
    // ------------------------------------------------------------------

    private function settings(): CompanyMailSettings
    {
        return CompanyMailSettings::for($this->company([
            'id'           => 7,
            'smtp_enabled' => true,
            'smtp_host'    => 'smtp.acme.test',
        ]));
    }

    public function test_warning_when_latest_send_succeeds_on_company_transport_is_none(): void
    {
        // Proven working — even if the verified stamp is missing, a successful
        // send via the company transport clears the warning.
        $this->assertNull(
            $this->settings()->evaluateDeliveryWarning('company:7', 'sent', '2 hours ago', false)
        );
    }

    public function test_danger_warning_when_latest_send_failed_on_company_transport(): void
    {
        $w = $this->settings()->evaluateDeliveryWarning('company:7', 'failed', '5 minutes ago', true);

        $this->assertNotNull($w);
        $this->assertSame('danger', $w['level']);
        $this->assertStringContainsString('5 minutes ago', $w['body']);
    }

    public function test_warning_when_latest_send_fell_back_to_platform_transport(): void
    {
        $w = $this->settings()->evaluateDeliveryWarning('system', 'sent', 'yesterday', true);

        $this->assertNotNull($w);
        $this->assertSame('warning', $w['level']);
        $this->assertStringContainsString('platform mailer', $w['body']);
    }

    public function test_unverified_warning_when_no_recent_send_and_never_verified(): void
    {
        $w = $this->settings()->evaluateDeliveryWarning(null, null, null, false);

        $this->assertNotNull($w);
        $this->assertSame('warning', $w['level']);
        $this->assertStringContainsString('verified', strtolower($w['title']));
    }

    public function test_no_warning_when_no_recent_send_but_verified(): void
    {
        $this->assertNull(
            $this->settings()->evaluateDeliveryWarning(null, null, null, true)
        );
    }

    public function test_no_warning_when_smtp_disabled(): void
    {
        // deliveryWarning() short-circuits before any DB lookup when SMTP is off.
        $settings = CompanyMailSettings::for($this->company([
            'id'           => 7,
            'smtp_enabled' => false,
            'smtp_host'    => 'smtp.acme.test',
        ]));

        $this->assertNull($settings->deliveryWarning());
    }
}
