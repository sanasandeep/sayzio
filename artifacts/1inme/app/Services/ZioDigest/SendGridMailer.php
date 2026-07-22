<?php

namespace App\Services\ZioDigest;

use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Services\Integrations\SendGridSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal SendGrid v3 Mail Send client for the Zio Digest channel.
 *
 * Delivery goes out over the SendGrid HTTP API (not the platform SMTP
 * transport), but every attempt still writes an email_logs row under the
 * central pipeline conventions (registry key, category, transport,
 * status/error) so digest mail is auditable alongside all other mail.
 */
class SendGridMailer
{
    public const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /**
     * Send one HTML email via SendGrid and log it.
     *
     * @param  array<string,string>  $headers  extra headers (e.g. List-Unsubscribe)
     * @return array{ok:bool,error:?string,log:?EmailLog}
     */
    public function send(
        string $key,
        string $to,
        ?string $toName,
        string $subject,
        string $html,
        array $headers = [],
        array $logOpts = [],
    ): array {
        $apiKey = SendGridSettings::apiKey();

        if ($apiKey === null) {
            $error = 'SendGrid API key is not configured.';

            return ['ok' => false, 'error' => $error, 'log' => $this->writeLog($key, $to, $subject, $html, 'failed', $error, $logOpts)];
        }

        $payload = [
            'personalizations' => [[
                'to' => [array_filter(['email' => $to, 'name' => $toName ?: null])],
            ]],
            'from'    => ['email' => SendGridSettings::fromEmail(), 'name' => SendGridSettings::fromName()],
            'subject' => $subject,
            'content' => [['type' => 'text/html', 'value' => $html]],
        ];
        if (!empty($headers)) {
            $payload['headers'] = $headers;
        }

        $status = 'sent';
        $error  = null;
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post(self::ENDPOINT, $payload);

            // SendGrid returns 202 on acceptance.
            if ($response->status() >= 300) {
                $status = 'failed';
                $error  = 'SendGrid HTTP ' . $response->status() . ': ' . mb_substr($response->body(), 0, 400);
                Log::warning("SendGridMailer send failed [{$key}] to {$to}: {$error}");
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = mb_substr($e->getMessage(), 0, 400);
            Log::warning("SendGridMailer send threw [{$key}]: {$error}");
        }

        return ['ok' => $status === 'sent', 'error' => $error, 'log' => $this->writeLog($key, $to, $subject, $html, $status, $error, $logOpts)];
    }

    private function writeLog(string $key, string $to, string $subject, string $html, string $status, ?string $error, array $opts): ?EmailLog
    {
        try {
            return EmailLog::create([
                'email_key'    => $key,
                'category'     => EmailTemplateRegistry::categoryFor($key),
                'recipient'    => $to,
                'subject'      => $subject !== '' ? mb_substr($subject, 0, 255) : null,
                'body'         => $html,
                'format'       => 'html',
                'transport'    => 'sendgrid',
                'status'       => $status,
                'error'        => $error,
                'user_id'      => $opts['user_id'] ?? null,
                'related_type' => $opts['related_type'] ?? null,
                'related_id'   => isset($opts['related_id']) ? (string) $opts['related_id'] : null,
                'meta'         => $opts['meta'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("SendGridMailer::writeLog failed [{$key}]: " . $e->getMessage());

            return null;
        }
    }
}
