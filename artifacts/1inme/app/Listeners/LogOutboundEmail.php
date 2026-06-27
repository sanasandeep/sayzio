<?php

namespace App\Listeners;

use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

/**
 * Catch-all activity logger for outbound email.
 *
 * Wired by Laravel's event auto-discovery (typed handle(MessageSent)). It logs
 * every successfully-sent message that the central pipeline did NOT already log
 * — i.e. raw/Mail::send sites not yet migrated, branded Mailables sent without
 * an override, and any future send — giving the admin Email Log complete
 * coverage.
 *
 * Messages tagged X-Sayzio-Logged are skipped (Emailer already wrote their
 * row). The registry key, when known, travels on the X-Email-Key header.
 *
 * Wholly best-effort: a logging failure must never break mail delivery.
 */
class LogOutboundEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            $message = $event->message; // Symfony\Component\Mime\Email
            $headers = $message->getHeaders();

            // Already captured by the central pipeline.
            if ($headers->has(Emailer::LOGGED_HEADER)) {
                return;
            }

            $key = $headers->has(Emailer::KEY_HEADER)
                ? trim((string) $headers->get(Emailer::KEY_HEADER)->getBodyAsString())
                : 'uncategorized';
            if ($key === '') {
                $key = 'uncategorized';
            }

            $to = '';
            foreach ($message->getTo() as $addr) {
                $to = $addr->getAddress();
                break;
            }
            if ($to === '') {
                return; // nothing meaningful to log
            }

            $html = $message->getHtmlBody();
            $text = $message->getTextBody();
            $format = $html !== null ? 'html' : 'text';
            $body = $html !== null ? (string) $html : (string) $text;

            $cc = [];
            foreach ($message->getCc() as $addr) {
                $cc[] = $addr->getAddress();
            }

            EmailLog::create([
                'email_key' => $key,
                'category'  => EmailTemplateRegistry::categoryFor($key),
                'recipient' => $to,
                'subject'   => $message->getSubject() ? mb_substr((string) $message->getSubject(), 0, 255) : null,
                'body'      => $body !== '' ? $body : null,
                'format'    => $format,
                'status'    => 'sent',
                'meta'      => $cc ? ['cc' => $cc] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('LogOutboundEmail failed: ' . $e->getMessage());
        }
    }
}
