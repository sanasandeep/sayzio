<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Integrations\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Admin "Email / SMTP" settings page. Lets a super admin configure the
 * platform's outbound mail transport (mailer/driver, SMTP host/port/
 * encryption/username/password and the global "from" identity) at runtime,
 * stored DB-backed and encrypted via MailSettings — the same env-fallback
 * pattern used for the WhatsApp/integration keys.
 *
 * Secret-handling UX mirrors the API Keys page: the stored SMTP password is
 * always masked, a blank password field on save leaves the stored value
 * untouched, and an explicit "remove" checkbox clears it back to env.
 */
class MailSettingsController extends Controller
{
    public function index()
    {
        return view('admin.mail-settings.index', [
            'status'           => MailSettings::status(),
            'mailers'          => MailSettings::availableMailers(),
            'mailer'           => MailSettings::mailer(),
            'host'             => MailSettings::host(),
            'port'             => MailSettings::port(),
            'encryption'       => MailSettings::encryption(),
            'username'         => MailSettings::username(),
            'hasPassword'      => MailSettings::password() !== null,
            'maskedPassword'   => MailSettings::maskedPassword(),
            'fromAddress'      => MailSettings::fromAddress(),
            'fromName'         => MailSettings::fromName(),
            'encryptionOptions' => MailSettings::ENCRYPTION_OPTIONS,
        ]);
    }

    public function update(Request $request)
    {
        $mailers = MailSettings::availableMailers();

        $data = $request->validate([
            'mailer'       => ['required', 'string', Rule::in($mailers)],
            // Host/port are required only when the SMTP transport is chosen.
            'host'         => ['nullable', 'string', 'max:255', 'required_if:mailer,smtp'],
            'port'         => ['nullable', 'integer', 'min:1', 'max:65535', 'required_if:mailer,smtp'],
            'encryption'   => ['required', Rule::in(MailSettings::ENCRYPTION_OPTIONS)],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:1024'],
            'clear_password' => ['nullable', 'boolean'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['required', 'string', 'max:255'],
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

        // Secret: blank leaves the stored value untouched; explicit checkbox
        // clears it back to the env fallback.
        if ($request->boolean('clear_password')) {
            MailSettings::setPassword(null);
        } elseif (!empty($data['password'])) {
            MailSettings::setPassword($data['password']);
        }

        return redirect()->route('admin.mail-settings.index')
            ->with('success', 'Email / SMTP settings saved.');
    }

    /**
     * Send a real test email to a chosen address through the freshly-stored
     * transport, reporting success or the transport error inline.
     */
    public function sendTest(Request $request)
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        // Apply the saved settings to the current process before sending so
        // the test reflects exactly what was saved (the override otherwise
        // only runs at boot of a fresh request).
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
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }

        if (MailSettings::mailer() === 'log') {
            return back()->with('info', 'The mailer is set to "log" — the test email was written to the log, not delivered. Choose the SMTP mailer to send live.');
        }

        return back()->with('success', 'Test email dispatched to ' . $data['test_email'] . '.');
    }
}
