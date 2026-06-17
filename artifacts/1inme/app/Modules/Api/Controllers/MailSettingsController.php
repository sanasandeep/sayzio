<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\Integrations\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Bearer-token parity for the admin "Email / SMTP" settings page so a
 * super admin can check the effective outbound-mail transport, fire a live
 * test email and now fully edit the transport from the 1INME Mobile app
 * while troubleshooting on the go.
 *
 * Editing (update) mirrors the web admin page exactly: it persists via the
 * same MailSettings setters, the SMTP password follows the "blank leaves
 * untouched, explicit clear resets to env" UX, and an SMTP save runs the
 * same lightweight connection check. The stored SMTP password is never
 * exposed. Every endpoint is gated behind the same `settings.manage`
 * permission the web routes use, so only platform admins reach them.
 */
class MailSettingsController extends Controller
{
    use ApiResponses;

    /**
     * Effective mailer + from-identity and a status badge so the mobile
     * screen can render "configured / env fallback / log driver" exactly
     * like the web admin badge, plus the picker options it needs to edit.
     */
    public function status(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view mail settings.');
        }

        return $this->ok($this->statusPayload());
    }

    /**
     * Persist the mailer/host/port/encryption/username/password and the
     * from-identity, mirroring the web admin update(). Returns the refreshed
     * status payload plus an optional SMTP connection-check result so the
     * mobile screen can surface "saved, but the connection check failed"
     * exactly like the web page does.
     */
    public function update(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to edit mail settings.');
        }

        $mailers = MailSettings::availableMailers();

        $data = $request->validate([
            'mailer'         => ['required', 'string', Rule::in($mailers)],
            // Host/port are required only when the SMTP transport is chosen.
            'host'           => ['nullable', 'string', 'max:255', 'required_if:mailer,smtp'],
            'port'           => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:mailer,smtp'],
            'encryption'     => ['required', Rule::in(MailSettings::ENCRYPTION_OPTIONS)],
            'username'       => ['nullable', 'string', 'max:255'],
            'password'       => ['nullable', 'string', 'max:1024'],
            'clear_password' => ['nullable', 'boolean'],
            'from_address'   => ['required', 'email', 'max:255'],
            'from_name'      => ['required', 'string', 'max:255'],
        ], [
            'host.required_if' => 'The SMTP host is required when the SMTP mailer is selected.',
            'port.required_if' => 'The SMTP port is required when the SMTP mailer is selected.',
        ]);

        // Plain scalars: always written from the submitted value; empty clears
        // back to the env fallback.
        MailSettings::setMailer($data['mailer']);
        MailSettings::setHost($data['host'] ?? null);
        MailSettings::setPort(isset($data['port']) ? (int) $data['port'] : null);
        MailSettings::setEncryption($data['encryption']);
        MailSettings::setUsername($data['username'] ?? null);
        MailSettings::setFromAddress($data['from_address']);
        MailSettings::setFromName($data['from_name']);

        // Secret: blank leaves the stored value untouched; explicit flag
        // clears it back to the env fallback.
        if ($request->boolean('clear_password')) {
            MailSettings::setPassword(null);
        } elseif (!empty($data['password'])) {
            MailSettings::setPassword($data['password']);
        }

        // Lightweight connection check on save so typos / bad credentials are
        // caught now rather than silently breaking every outbound email. The
        // check never blocks the save — the values are already persisted above.
        $verify = null;
        if ($data['mailer'] === 'smtp') {
            $result = MailSettings::verifyConnection();
            $verify = ['ok' => $result['ok'], 'error' => $result['error']];
        }

        return $this->ok($this->statusPayload() + ['verify' => $verify]);
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
                'message' => 'The mailer is set to "log" — the test email was written to the log, not delivered. Choose the SMTP mailer to send live.',
            ]);
        }

        return $this->ok([
            'sent'    => true,
            'to'      => $data['test_email'],
            'message' => 'Test email dispatched to ' . $data['test_email'] . '.',
        ]);
    }

    /**
     * Effective transport + picker options shared by status() and update().
     * Never exposes the stored SMTP password — only whether one is set.
     */
    private function statusPayload(): array
    {
        return [
            'status'            => MailSettings::status(),
            'mailer'            => MailSettings::mailer(),
            'host'              => MailSettings::host(),
            'port'              => MailSettings::port(),
            'encryption'        => MailSettings::encryption(),
            'username'          => MailSettings::username(),
            'from_address'      => MailSettings::fromAddress(),
            'from_name'         => MailSettings::fromName(),
            'has_password'      => MailSettings::password() !== null,
            'mailers'           => MailSettings::availableMailers(),
            'encryption_options' => MailSettings::ENCRYPTION_OPTIONS,
        ];
    }
}
