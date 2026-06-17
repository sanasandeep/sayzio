<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\Integrations\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

/**
 * Bearer-token parity for the admin "Email / SMTP" settings page so a
 * super admin can check the effective outbound-mail transport and fire a
 * live test email from the 1INME Mobile app while troubleshooting on the
 * go.
 *
 * Read-only by design: it never exposes the stored SMTP password and never
 * mutates the saved configuration — editing the transport stays on the web
 * admin page. Both endpoints are gated behind the same `settings.manage`
 * permission the web routes use, so only platform admins reach them.
 */
class MailSettingsController extends Controller
{
    use ApiResponses;

    /**
     * Effective mailer + from-identity and a status badge so the mobile
     * screen can render "configured / env fallback / log driver" exactly
     * like the web admin badge.
     */
    public function status(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view mail settings.');
        }

        return $this->ok([
            'status'       => MailSettings::status(),
            'mailer'       => MailSettings::mailer(),
            'host'         => MailSettings::host(),
            'port'         => MailSettings::port(),
            'encryption'   => MailSettings::encryption(),
            'from_address' => MailSettings::fromAddress(),
            'from_name'    => MailSettings::fromName(),
            'has_password' => MailSettings::password() !== null,
        ]);
    }

    /**
     * Send a real test email to a chosen address through the saved
     * transport, mirroring the web admin "send test" control. Reports the
     * transport error inline on failure, and flags the no-delivery "log"
     * driver case so the admin isn't misled by a false success.
     */
    public function sendTest(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to send a test email.');
        }

        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        // Apply the saved settings to this process before sending so the
        // test reflects exactly what an admin saved on the web page.
        MailSettings::applyRuntimeConfig();

        try {
            Mail::raw(
                "This is a test email from 1INME.\n\nIf you received this, your SMTP / email settings are working.\n\nSent at " . now()->toDateTimeString() . '.',
                function ($message) use ($data) {
                    $message->to($data['test_email'])
                        ->subject('1INME — test email');
                }
            );
        } catch (\Throwable $e) {
            return $this->fail('Test email failed: ' . $e->getMessage(), 422, 'mail_send_failed');
        }

        if (MailSettings::mailer() === 'log') {
            return $this->ok([
                'sent'    => false,
                'driver'  => 'log',
                'message' => 'The mailer is set to "log" — the test email was written to the log, not delivered. Choose the SMTP mailer on the web admin to send live.',
            ]);
        }

        return $this->ok([
            'sent'    => true,
            'to'      => $data['test_email'],
            'message' => 'Test email dispatched to ' . $data['test_email'] . '.',
        ]);
    }
}
