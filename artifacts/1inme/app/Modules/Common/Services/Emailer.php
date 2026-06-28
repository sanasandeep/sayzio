<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\EmailLog;
use App\Services\Integrations\BillingNotificationSettings;
use App\Services\Integrations\EmailTemplateSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

/**
 * Central outbound-email pipeline.
 *
 * Every templated/transactional send goes through here keyed by an
 * EmailTemplateRegistry key. The pipeline:
 *   1. resolves the admin override (EmailTemplateSettings) or the registry
 *      default — so with no override the content is identical to before;
 *   2. substitutes {{tokens}} into the subject/body;
 *   3. sends via Mail::html / Mail::raw (or the supplied Mailable);
 *   4. writes an email_logs row (status sent|failed) capturing enough of the
 *      rendered message to power the admin log + a per-row Resend.
 *
 * Messages sent here carry an X-Sayzio-Logged header so the catch-all
 * MessageSent listener (App\Listeners\LogOutboundEmail) does not double-log
 * them. Mailables sent without an override are tagged X-Email-Key instead and
 * logged by that listener (which reads the final rendered message).
 *
 * Existing call-site prefersChannel() gates are intentionally left in place;
 * the registry's pref_type is documentation only.
 */
class Emailer
{
    /** Set on messages this service has already logged, so the listener skips them. */
    public const LOGGED_HEADER = 'X-Sayzio-Logged';

    /** Carries the registry key to the catch-all listener for un-logged sends. */
    public const KEY_HEADER = 'X-Email-Key';

    /**
     * Send a templated email by registry key.
     *
     * @param  string  $key     EmailTemplateRegistry key
     * @param  string  $to      recipient email
     * @param  array<string,mixed>  $tokens  {{token}} => value (subject + inline/override body)
     * @param  array<string,mixed>  $opts    view_data, subject, body, format, user, related,
     *                                        cc, bcc, reply_to, from, attachments, to_name
     */
    public static function send(string $key, string $to, array $tokens = [], array $opts = []): ?EmailLog
    {
        $entry = EmailTemplateRegistry::get($key) ?? [];

        // A per-send template override (e.g. a creator's per-company customisation
        // of a client-facing accounting email) takes precedence over the
        // admin/global override, which in turn falls back to the registry default.
        $override = (isset($opts['template_override']) && is_array($opts['template_override']))
            ? $opts['template_override']
            : EmailTemplateSettings::get($key);

        $format  = $override['format'] ?? $opts['format'] ?? $entry['format'] ?? 'html';
        $subject = self::resolveSubject($key, $entry, $override, $tokens, $opts);
        $body    = self::resolveBody($key, $entry, $override, $tokens, $opts, $format);

        return self::dispatch($key, $to, $subject, (string) $body, $format, $opts);
    }

    /**
     * Send a pre-built Mailable through the pipeline (rich branded layouts).
     *
     * With an admin override present, the override body is sent instead of the
     * Mailable (and logged here). Otherwise the Mailable is sent as-is, tagged
     * with the registry key so the catch-all listener logs the exact rendered
     * message.
     *
     * @param  array<string,mixed>  $tokens
     * @param  array<string,mixed>  $opts
     */
    public static function sendMailable(string $key, string $to, Mailable $mailable, array $tokens = [], array $opts = []): ?EmailLog
    {
        $entry    = EmailTemplateRegistry::get($key) ?? [];
        $override = EmailTemplateSettings::get($key);

        // An override replaces the branded Mailable with the admin's content.
        if ($override && !empty($override['body'])) {
            $format  = $override['format'] ?? $entry['format'] ?? 'html';
            $subject = self::renderTokens((string) ($override['subject'] ?? ($entry['subject'] ?? '')), $tokens);
            $body    = self::renderTokens((string) $override['body'], $tokens);

            return self::dispatch($key, $to, $subject, $body, $format, $opts);
        }

        // No override: send the Mailable as-is, tag it so the listener logs the
        // final rendered message under this key.
        $opts = self::applyBillingCc($key, $opts);
        try {
            $mailable->withSymfonyMessage(function ($message) use ($key) {
                $message->getHeaders()->addTextHeader(self::KEY_HEADER, $key);
            });

            $pending = Mail::to($to);
            if (!empty($opts['cc']))  $pending->cc($opts['cc']);
            if (!empty($opts['bcc'])) $pending->bcc($opts['bcc']);
            if (!empty($opts['queue'])) {
                $pending->queue($mailable);
            } else {
                $pending->send($mailable);
            }
        } catch (\Throwable $e) {
            Log::warning("Emailer::sendMailable failed [{$key}]: " . $e->getMessage());
            // Record the failure here since the listener only fires on success.
            return self::writeLog($key, $to, self::renderTokens((string) ($entry['subject'] ?? ''), $tokens), null, $entry['format'] ?? 'html', 'failed', $e->getMessage(), $opts);
        }

        // Success is logged by the listener (it has the rendered message).
        return null;
    }

    /**
     * Re-send a previously logged email exactly as it went out, recording a new
     * log row that points back at the original. Throttling is the caller's
     * responsibility.
     */
    public static function resend(EmailLog $log): EmailLog
    {
        $opts = [
            'user'         => $log->user_id,
            'related'      => ['type' => $log->related_type, 'id' => $log->related_id],
            'resent_from'  => $log->id,
        ];
        if (!empty($log->meta['cc']))       $opts['cc'] = $log->meta['cc'];
        if (!empty($log->meta['reply_to'])) $opts['reply_to'] = $log->meta['reply_to'];
        if (!empty($log->meta['from']))     $opts['from'] = $log->meta['from'];

        return self::dispatch(
            $log->email_key,
            $log->recipient,
            (string) $log->subject,
            (string) $log->body,
            $log->format ?: 'html',
            $opts,
        ) ?? $log;
    }

    /**
     * Render a best-effort preview of a template using its documented sample
     * variables. Resolves exactly like a real send (override wins, otherwise
     * the registry default / Blade view), so the admin sees what recipients
     * would get. A pending (unsaved) override can be passed to preview edits
     * before saving.
     *
     * @param  array{subject?:string,body?:string,format?:string}|null  $draftOverride
     * @return array{subject:string, body:string, format:string}
     */
    public static function preview(string $key, ?array $draftOverride = null): array
    {
        $entry    = EmailTemplateRegistry::get($key) ?? [];
        $override = $draftOverride ?? EmailTemplateSettings::get($key);
        $tokens   = EmailTemplateRegistry::sampleTokens($key);

        $format  = $override['format'] ?? $entry['format'] ?? 'html';
        $subject = self::resolveSubject($key, $entry, $override, $tokens, []);

        $bodyType = $entry['body_type'] ?? 'inline';

        if ($override && !empty($override['body'])) {
            $body = self::renderTokens((string) $override['body'], $tokens);
        } elseif (in_array($bodyType, ['view', 'mailable'], true) && !empty($entry['view'])) {
            try {
                $body = View::make($entry['view'], $entry['sample_view'] ?? $tokens)->render();
            } catch (\Throwable $e) {
                $body = '[Live preview unavailable for this template: ' . $e->getMessage() . ']';
            }
        } else {
            $body = self::renderTokens((string) ($entry['body'] ?? ''), $tokens);
        }

        return ['subject' => $subject, 'body' => $body, 'format' => $format];
    }

    // ----------------------------------------------------------------------
    // Internals
    // ----------------------------------------------------------------------

    private static function resolveSubject(string $key, array $entry, ?array $override, array $tokens, array $opts): string
    {
        if ($override && isset($override['subject']) && $override['subject'] !== '') {
            return self::renderTokens((string) $override['subject'], $tokens);
        }
        if (isset($opts['subject']) && $opts['subject'] !== '') {
            return (string) $opts['subject'];
        }
        return self::renderTokens((string) ($entry['subject'] ?? ''), $tokens);
    }

    private static function resolveBody(string $key, array $entry, ?array $override, array $tokens, array $opts, string $format): string
    {
        if ($override && !empty($override['body'])) {
            return self::renderTokens((string) $override['body'], $tokens);
        }

        $bodyType = $entry['body_type'] ?? 'inline';

        if ($bodyType === 'view' && !empty($entry['view'])) {
            $viewData = $opts['view_data'] ?? $tokens;
            try {
                return View::make($entry['view'], $viewData)->render();
            } catch (\Throwable $e) {
                Log::warning("Emailer view render failed [{$key}]: " . $e->getMessage());
                return (string) ($opts['body'] ?? '');
            }
        }

        if ($bodyType === 'dynamic') {
            // The call site computes the body; fall back to the documented default.
            if (isset($opts['body'])) {
                return (string) $opts['body'];
            }
            return self::renderTokens((string) ($entry['body'] ?? ''), $tokens);
        }

        // inline
        if (isset($opts['body'])) {
            return (string) $opts['body'];
        }
        return self::renderTokens((string) ($entry['body'] ?? ''), $tokens);
    }

    /**
     * Actually send + log a fully-rendered message.
     */
    private static function dispatch(string $key, string $to, string $subject, string $body, string $format, array $opts): ?EmailLog
    {
        $opts   = self::applyBillingCc($key, $opts);
        $status = 'sent';
        $error  = null;

        // An optional per-send mailer (e.g. a creator's per-company SMTP) lets a
        // single message go out through a transport other than the platform
        // default without disturbing global mail config. Registered just-in-time
        // and purged so a fresh transport is built from the supplied values.
        $mailerName = null;
        if (!empty($opts['mailer']) && is_string($opts['mailer'])
            && !empty($opts['mailer_config']) && is_array($opts['mailer_config'])) {
            $mailerName = $opts['mailer'];
            config(["mail.mailers.{$mailerName}" => $opts['mailer_config']]);
            try {
                Mail::purge($mailerName);
            } catch (\Throwable $e) {
                // purge is best-effort; the mailer will still resolve fresh config.
            }
        }

        $thrown = null;
        try {
            $callback = function ($m) use ($to, $subject, $opts) {
                $m->to($to, $opts['to_name'] ?? null);
                if ($subject !== '') {
                    $m->subject($subject);
                }
                if (!empty($opts['cc']))       $m->cc($opts['cc']);
                if (!empty($opts['bcc']))      $m->bcc($opts['bcc']);
                if (!empty($opts['reply_to'])) {
                    $rt = $opts['reply_to'];
                    is_array($rt) ? $m->replyTo($rt['address'] ?? '', $rt['name'] ?? null) : $m->replyTo($rt);
                }
                if (!empty($opts['from'])) {
                    $fr = $opts['from'];
                    is_array($fr) ? $m->from($fr['address'] ?? '', $fr['name'] ?? null) : $m->from($fr);
                }
                foreach (($opts['attachments'] ?? []) as $att) {
                    if (isset($att['data'])) {
                        $m->attachData($att['data'], $att['name'] ?? 'attachment', array_filter(['mime' => $att['mime'] ?? null]));
                    } elseif (isset($att['path'])) {
                        $m->attach($att['path'], array_filter(['as' => $att['name'] ?? null, 'mime' => $att['mime'] ?? null]));
                    }
                }
                foreach (($opts['headers'] ?? []) as $hName => $hValue) {
                    $m->getHeaders()->addTextHeader($hName, (string) $hValue);
                }
                $m->getHeaders()->addTextHeader(self::LOGGED_HEADER, '1');
                $m->getHeaders()->addTextHeader(self::KEY_HEADER, $key);
            };

            $mailer = $mailerName ? Mail::mailer($mailerName) : Mail::mailer();
            if ($format === 'text') {
                $mailer->raw($body, $callback);
            } else {
                $mailer->html($body, $callback);
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $error  = $e->getMessage();
            $thrown = $e;
            Log::warning("Emailer::dispatch failed [{$key}]: " . $error);
        }

        $log = self::writeLog($key, $to, $subject, $body, $format, $status, $error, $opts);

        // Opt-in: callers that must NOT proceed on a silent transport failure
        // (e.g. stamping a client invoice "sent" only after real delivery) pass
        // throw_on_failure so the swallowed error surfaces — after the failed
        // email_logs row is still written for the admin log + resend.
        if ($thrown !== null && !empty($opts['throw_on_failure'])) {
            throw new \App\Modules\Common\Exceptions\EmailDeliveryException(
                "Email delivery failed [{$key}]: " . $thrown->getMessage(),
                $log,
                $thrown,
            );
        }

        return $log;
    }

    private static function writeLog(string $key, string $to, string $subject, ?string $body, string $format, string $status, ?string $error, array $opts): ?EmailLog
    {
        try {
            $meta = [];
            if (!empty($opts['cc']))          $meta['cc'] = $opts['cc'];
            if (!empty($opts['bcc']))         $meta['bcc'] = $opts['bcc'];
            if (!empty($opts['reply_to']))    $meta['reply_to'] = $opts['reply_to'];
            if (!empty($opts['from']))        $meta['from'] = $opts['from'];
            if (!empty($opts['attachments'])) $meta['has_attachments'] = true;
            if (!empty($opts['resent_from'])) $meta['resent_from'] = $opts['resent_from'];
            if (!empty($opts['transport_label'])) $meta['transport'] = $opts['transport_label'];

            [$relatedType, $relatedId] = self::normalizeRelated($opts['related'] ?? null);

            return EmailLog::create([
                'email_key'    => $key,
                'category'     => EmailTemplateRegistry::categoryFor($key),
                'recipient'    => $to,
                'subject'      => $subject !== '' ? mb_substr($subject, 0, 255) : null,
                'body'         => $body, // capped by EmailLog's body mutator
                'format'       => $format,
                'status'       => $status,
                'error'        => $error,
                'user_id'      => self::normalizeUserId($opts['user'] ?? null),
                'related_type' => $relatedType,
                'related_id'   => $relatedId,
                'meta'         => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the actual send.
            Log::warning("Emailer::writeLog failed [{$key}]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Merge the admin-managed billing CC list into $opts['cc'] when this key is a
     * platform-billing email. The primary recipient's copy is unchanged; the CC
     * addresses are added in addition. De-duplicates case-insensitively while
     * preserving order, and is idempotent (safe to call more than once per send).
     *
     * @param  array<string,mixed>  $opts
     * @return array<string,mixed>
     */
    private static function applyBillingCc(string $key, array $opts): array
    {
        if (!BillingNotificationSettings::shouldCc($key)) {
            return $opts;
        }

        $recipients = BillingNotificationSettings::ccRecipients();
        if (empty($recipients)) {
            return $opts;
        }

        $existing = $opts['cc'] ?? [];
        $existing = is_array($existing) ? $existing : [$existing];

        $seen = [];
        $out  = [];
        foreach (array_merge($existing, $recipients) as $addr) {
            $addr = is_string($addr) ? trim($addr) : $addr;
            if (!is_string($addr) || $addr === '') {
                continue;
            }
            $lower = strtolower($addr);
            if (isset($seen[$lower])) {
                continue;
            }
            $seen[$lower] = true;
            $out[] = $addr;
        }

        $opts['cc'] = $out;
        return $opts;
    }

    private static function normalizeUserId($user): ?int
    {
        if (is_null($user)) return null;
        if (is_int($user)) return $user;
        if (is_numeric($user)) return (int) $user;
        if (is_object($user) && isset($user->id)) return (int) $user->id;
        return null;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private static function normalizeRelated($related): array
    {
        if (is_null($related)) return [null, null];
        if (is_object($related)) {
            $type = method_exists($related, 'getMorphClass') ? $related->getMorphClass() : get_class($related);
            $id   = $related->getKey() ?? ($related->id ?? null);
            return [$type, $id !== null ? (string) $id : null];
        }
        if (is_array($related)) {
            $type = $related['type'] ?? null;
            $id   = $related['id'] ?? null;
            return [$type ? (string) $type : null, ($id !== null && $id !== '') ? (string) $id : null];
        }
        return [null, null];
    }

    /**
     * Replace {{token}} / {{ token }} occurrences. Unknown tokens are left as-is.
     *
     * @param  array<string,mixed>  $tokens
     */
    public static function renderTokens(string $template, array $tokens): string
    {
        if ($template === '' || empty($tokens)) {
            return $template;
        }
        $replacements = [];
        foreach ($tokens as $name => $value) {
            $scalar = is_scalar($value) || is_null($value) ? (string) $value : json_encode($value);
            $replacements['{{' . $name . '}}']  = $scalar;
            $replacements['{{ ' . $name . ' }}'] = $scalar;
        }
        return strtr($template, $replacements);
    }
}
