<?php

namespace App\Modules\Common\Support;

/**
 * Turns a raw transport error message (as stored in email_logs.error) into a
 * short, human-friendly reason that is safe to show end users.
 *
 * Raw transport errors frequently leak server hostnames, credentials hints,
 * stack-trace fragments and protocol noise, so we never echo them verbatim.
 * Instead we classify the message into a small set of known causes and return
 * a fixed, sanitized sentence — falling back to a generic line when the cause
 * is unrecognised.
 */
class EmailErrorSummary
{
    /**
     * Classify a raw transport error into a safe, human-friendly reason.
     * Returns null when there is nothing useful/safe to show (empty input).
     */
    public static function summarize(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $haystack = strtolower($raw);

        // Order matters: more specific causes are matched before generic ones.
        $rules = [
            // Recipient address itself is malformed / can't be parsed.
            ['needles' => ['invalid address', 'address.*invalid', 'unable to parse', 'malformed', 'not a valid email', 'invalid recipient', 'syntactically incorrect'],
             'reason'  => "The recipient's email address looks invalid."],

            // SMTP authentication / credentials rejected by the mail server.
            ['needles' => ['authentication failed', 'auth failed', 'authentication required', 'authentication unsuccessful', '535', 'username and password not accepted', 'invalid login', 'bad credentials'],
             'reason'  => 'The email server rejected the sign-in credentials. Check your SMTP settings.'],

            // Recipient mailbox is over quota / full.
            ['needles' => ['mailbox full', 'quota exceeded', 'over quota', 'insufficient storage', 'mailbox is full', '552'],
             'reason'  => "The recipient's mailbox is full."],

            // Recipient mailbox doesn't exist / is unavailable.
            ['needles' => ['mailbox unavailable', 'no such user', 'user unknown', 'does not exist', "doesn't exist", 'recipient address rejected', 'no such recipient', 'unrouteable', 'unroutable', '550', '553'],
             'reason'  => "The recipient's mailbox doesn't exist or isn't accepting mail."],

            // Couldn't reach the mail server at all.
            ['needles' => ['connection refused', 'could not connect', 'connection timed out', 'connection time', 'timed out', 'timeout', 'network is unreachable', 'unable to connect', 'no route to host', 'connection reset', 'host unreachable'],
             'reason'  => "Couldn't reach the email server. It may be down or misconfigured."],

            // TLS / certificate problems with the mail server.
            ['needles' => ['tls', 'ssl', 'certificate', 'starttls'],
             'reason'  => 'A secure connection to the email server could not be established.'],

            // Relay / policy rejection by the receiving server.
            ['needles' => ['relay access denied', 'relaying denied', 'relay not permitted', 'spam', 'blocked', 'blacklist', 'policy', 'rejected', '554', '521'],
             'reason'  => "The email was rejected by the recipient's mail server."],

            // Sender / rate limits.
            ['needles' => ['rate limit', 'too many', 'throttl', '421', '450'],
             'reason'  => 'The email server is rate-limiting sends right now. Try again shortly.'],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['needles'] as $needle) {
                if (self::matches($haystack, $needle)) {
                    return $rule['reason'];
                }
            }
        }

        // Unrecognised cause: never leak the raw transport detail.
        return "The email couldn't be delivered.";
    }

    /**
     * Match a needle against the haystack. Needles containing regex
     * metacharacters (`.*`) are treated as patterns; everything else is a
     * plain substring match.
     */
    protected static function matches(string $haystack, string $needle): bool
    {
        if (str_contains($needle, '.*')) {
            return (bool) preg_match('/' . $needle . '/', $haystack);
        }
        return str_contains($haystack, $needle);
    }
}
