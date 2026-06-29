<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Support\ContactRecipientHealth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared multi-channel quick-contact logic used by BOTH the Site Assistant
 * handoff and the standalone quick-contact widget. Validates the chosen
 * channel + contact value, persists a ContactMessage carrying the channel +
 * phone, and notifies the admin support inbox by email.
 *
 * Channels:
 *   - callback : Indian phone number only (+91 / 10-digit, 6-9 leading)
 *   - whatsapp : phone number WITH country code (E.164-ish, +<digits>)
 *   - email    : a valid email address
 */
class QuickContactService
{
    public const CHANNELS = ['callback', 'whatsapp', 'email'];

    /**
     * How long an identical submission from the same caller is treated as a
     * duplicate. Long enough to absorb double-taps, retries and scripted
     * floods; short enough that a genuine follow-up minutes later still
     * reaches the inbox.
     */
    public const DEDUPE_WINDOW_SECONDS = 120;

    /**
     * Status given to a submission that looks spammy (link/spam-pattern body)
     * or arrives during an implausible global burst. It is still persisted so
     * a false positive stays recoverable, but it is queued in a separate
     * review bucket and NEVER emails the admin — so a distributed flood that
     * rotates IPs and varies the message can't bury real leads or spam the
     * inbox with notifications.
     */
    public const SPAM_STATUS = 'spam';

    /**
     * Rolling global-volume guard. Quick-contact is a low-traffic surface, so
     * an implausibly high number of submissions across ALL callers in a short
     * window is a distributed-bot signal even when each IP/message differs.
     * Once the window ceiling is crossed, further submissions are quarantined
     * (status=spam) rather than dropped, so a genuine lead caught in the burst
     * is still reviewable.
     */
    public const GLOBAL_RATE_WINDOW_SECONDS = 300; // 5 minutes
    public const GLOBAL_RATE_MAX = 40;             // accepted submissions per window before quarantine

    /**
     * Minimum plausible time (milliseconds) a human takes to read the widget,
     * pick a channel, type a phone/email and submit. A real visitor never
     * clears this in under two seconds; a form filled and posted faster than
     * this is almost always a script. The client reports the elapsed time
     * since the form opened (a same-clock delta, so it is immune to client
     * clock skew). Absent/garbage values are treated as "not fast" so older
     * clients and odd timers never penalize a real lead — the honeypot and
     * volume guards still cover those.
     */
    public const MIN_FILL_MS = 2000;

    /** Human label for each channel, for the inbox + admin email. */
    public static function channelLabel(?string $channel): string
    {
        return match ($channel) {
            'callback' => 'Call back (phone)',
            'whatsapp' => 'WhatsApp call',
            'email'    => 'Email',
            default    => 'Message',
        };
    }

    /**
     * Normalize an Indian phone number to a canonical +91XXXXXXXXXX form, or
     * return null when it is not a valid Indian mobile number. Accepts an
     * optional +91 / 0 prefix and tolerates spaces, dashes and parentheses.
     */
    public static function normalizeIndianPhone(string $raw): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if ($digits === null || $digits === '') return null;

        // Strip a leading country code (91) or a trunk 0.
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Indian mobile numbers are 10 digits and start 6-9.
        if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            return null;
        }
        return '+91' . $digits;
    }

    /**
     * Normalize a WhatsApp phone number that MUST carry a country code.
     * Returns a canonical +<digits> string, or null when invalid.
     */
    public static function normalizeWhatsappPhone(string $raw): ?string
    {
        $trimmed = trim($raw);
        $hadPlus = str_starts_with($trimmed, '+');
        $digits  = preg_replace('/[^0-9]/', '', $trimmed);
        if ($digits === null || $digits === '') return null;

        // Require a country code: either an explicit leading "+", or enough
        // digits that a country code is plausibly included (>= 11).
        if (!$hadPlus && strlen($digits) < 11) {
            return null;
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }
        return '+' . $digits;
    }

    /**
     * Validate the channel-specific contact value. Returns an error string
     * (visitor-facing) or null when valid. On success the normalized phone
     * (if any) is written back into $phone by reference.
     *
     * @param  string|null  $phone  by-ref; normalized in place on success
     */
    public static function validate(string $channel, ?string &$phone, ?string $email): ?string
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            return 'Please choose how you would like to be contacted.';
        }

        if ($channel === 'email') {
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return 'Please enter a valid email address.';
            }
            $phone = null;
            return null;
        }

        $raw = trim((string) $phone);
        if ($raw === '') {
            return $channel === 'callback'
                ? 'Please enter your phone number for a call back.'
                : 'Please enter your WhatsApp number (with country code).';
        }

        if ($channel === 'callback') {
            $normalized = self::normalizeIndianPhone($raw);
            if ($normalized === null) {
                return 'Call back is available for Indian numbers only. Enter a valid 10-digit Indian mobile (e.g. +91 98765 43210).';
            }
            $phone = $normalized;
            return null;
        }

        // whatsapp
        $normalized = self::normalizeWhatsappPhone($raw);
        if ($normalized === null) {
            return 'Please enter a valid WhatsApp number including the country code (e.g. +1 555 123 4567).';
        }
        $phone = $normalized;
        return null;
    }

    /**
     * Persist a ContactMessage carrying the channel + phone and notify the
     * admin support inbox by email.
     *
     * @param  array{name?:string,email?:string,subject?:string,message?:string,channel?:string,phone?:string,ip?:string}  $attrs
     */
    public static function create(array $attrs): ContactMessage
    {
        $channel = (string) ($attrs['channel'] ?? '');
        $status  = (string) ($attrs['status'] ?? 'new');
        $status  = $status === self::SPAM_STATUS ? self::SPAM_STATUS : 'new';
        $contact = ContactMessage::create([
            'name'            => Str::limit(trim((string) ($attrs['name'] ?? '')), 250, ''),
            'email'           => Str::limit(trim((string) ($attrs['email'] ?? '')), 250, ''),
            'subject'         => Str::limit(trim((string) ($attrs['subject'] ?? 'New contact request')), 500, ''),
            'message'         => (string) ($attrs['message'] ?? ''),
            'contact_channel' => in_array($channel, self::CHANNELS, true) ? $channel : null,
            'contact_phone'   => Str::limit(trim((string) ($attrs['phone'] ?? '')), 40, '') ?: null,
            'ip'              => $attrs['ip'] ?? null,
            'status'          => $status,
        ]);

        // Quarantined submissions are queued for review only — never email
        // the admin, so a distributed flood can't spam the support inbox.
        if ($contact->status !== self::SPAM_STATUS) {
            self::notifyAdmin($contact);
        }

        return $contact;
    }

    /**
     * Build a stable fingerprint for a submission so identical requests from
     * the same caller collapse to one. Phone/email are taken AFTER channel
     * validation (normalized + lower-cased email) so cosmetic differences
     * (spaces, casing) still count as the same lead.
     *
     * @param  array{ip?:?string,user_id?:int|string|null,channel?:?string,phone?:?string,email?:?string,message?:?string}  $attrs
     */
    public static function fingerprint(array $attrs): string
    {
        $parts = [
            (string) ($attrs['ip'] ?? ''),
            (string) ($attrs['user_id'] ?? ''),
            (string) ($attrs['channel'] ?? ''),
            (string) ($attrs['phone'] ?? ''),
            mb_strtolower(trim((string) ($attrs['email'] ?? ''))),
            trim((string) ($attrs['message'] ?? '')),
        ];

        return 'quick_contact:dedupe:' . sha1(implode('|', $parts));
    }

    /**
     * Atomically claim a submission fingerprint for the dedupe window.
     * Returns true when newly claimed (the caller should proceed to create
     * the lead), or false when an identical submission was already claimed
     * within the window (the caller should skip persisting a duplicate).
     */
    public static function claimSubmission(string $fingerprint): bool
    {
        return Cache::add($fingerprint, true, self::DEDUPE_WINDOW_SECONDS);
    }

    /**
     * Whether a message body looks clearly spammy. Quick-contact leads are
     * short "call me back / email me" notes; bots paste links and promo copy.
     * Tuned to avoid false positives on real leads: a bare email address in
     * the body (e.g. "reach me at john@gmail.com") is NOT a link, so domains
     * are only flagged with an explicit scheme/www or a trailing path.
     */
    public static function looksSpammy(?string $message): bool
    {
        $m = mb_strtolower(trim((string) $message));
        if ($m === '') {
            return false;
        }

        // Explicit URLs / link markup — the strongest signal on this surface.
        if (preg_match('~https?://|www\.~i', $m)) {
            return true;
        }
        // A domain carrying a path ("foo.biz/promo") — bare email addresses
        // never have a trailing slash+path, so this stays false-positive safe.
        if (preg_match('~\b[a-z0-9-]+\.[a-z]{2,}/\S~i', $m)) {
            return true;
        }
        // HTML / BBCode link markup.
        if (preg_match('~</?a\b|\[url[=\]]|\[link[=\]]~i', $m)) {
            return true;
        }

        // Known spam keyword clusters.
        $patterns = [
            'viagra', 'cialis', 'casino', 'crypto', 'bitcoin', 'forex',
            'backlink', 'seo service', 'escort', 'porn', 'xxx',
            'click here', 'buy now', 'earn money', 'make money',
        ];
        foreach ($patterns as $p) {
            if (str_contains($m, $p)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record one submission against the rolling global-volume counter and
     * report whether the window ceiling is now exceeded. Counts ALL callers
     * (the guard is intentionally IP-agnostic so a distributed bot rotating
     * IPs still trips it). Best-effort: a cache backend error never blocks a
     * legitimate lead.
     */
    public static function registerSubmissionAndDetectBurst(): bool
    {
        try {
            $bucket = (int) floor(time() / self::GLOBAL_RATE_WINDOW_SECONDS);
            $key = 'quick_contact:global:count:' . $bucket;
            Cache::add($key, 0, self::GLOBAL_RATE_WINDOW_SECONDS + 60);
            $count = (int) Cache::increment($key);
            return $count > self::GLOBAL_RATE_MAX;
        } catch (\Throwable $e) {
            Log::warning('QuickContactService global-rate guard failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Whether a submission was filled and posted implausibly fast (a strong
     * bot signal that supplements the honeypot). $elapsedMs is the client-
     * reported time since the widget form opened. Only a present, non-negative,
     * numeric value below the floor counts as too fast; everything else
     * (missing field, non-numeric, negative) is treated as a genuine human so
     * legitimate leads are never penalized.
     *
     * @param  mixed  $elapsedMs
     */
    public static function tooFastSubmission($elapsedMs): bool
    {
        if ($elapsedMs === null || $elapsedMs === '' || !is_numeric($elapsedMs)) {
            return false;
        }
        $ms = (int) $elapsedMs;
        if ($ms < 0) {
            return false;
        }
        return $ms < self::MIN_FILL_MS;
    }

    /**
     * Email the admin support inbox about a new contact request, including
     * the chosen channel + the phone/email to follow up on. Best-effort:
     * a missing recipient or a transport error never breaks the request.
     */
    public static function notifyAdmin(ContactMessage $contact): void
    {
        // Make sure the dashboard banner re-checks now that a fresh lead has
        // landed (its cached snapshot counts pending leads).
        ContactRecipientHealth::flush();

        $recipient = ContactRecipientHealth::recipient();
        if ($recipient === '') {
            // No recipient configured anywhere: the lead is still persisted as
            // a ContactMessage (the caller created it), but nobody is actively
            // notified. Don't swallow this — log a monitored warning so the
            // silent-drop state is detectable. The admin dashboard also surfaces
            // it as a banner via ContactRecipientHealth.
            Log::warning('QuickContactService: no admin contact recipient configured — lead saved to inbox but no notification sent.', [
                'contact_message_id' => $contact->id,
                'channel'            => $contact->contact_channel,
            ]);
            return;
        }

        $channelLabel = self::channelLabel($contact->contact_channel);
        $reach = $contact->contact_channel === 'email'
            ? ($contact->email ?: '(no email)')
            : ($contact->contact_phone ?: '(no phone)');

        $lines = [
            'A new contact request was submitted.',
            '',
            'Name: ' . ($contact->name ?: '(not given)'),
            'Preferred contact: ' . $channelLabel,
            'Reach them at: ' . $reach,
        ];
        if ($contact->email) {
            $lines[] = 'Account email: ' . $contact->email;
        }
        $lines[] = '';
        $lines[] = $contact->message ?: '(no message)';

        try {
            Emailer::send('support.contact_request', $recipient, [], [
                'subject' => 'New contact request — ' . $channelLabel,
                'body'    => implode("\n", $lines),
                'format'  => 'text',
            ]);
        } catch (\Throwable $e) {
            Log::warning('QuickContactService admin notification failed: ' . $e->getMessage());
        }
    }
}
