<?php

namespace App\Modules\Common\Services;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
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
        $contact = ContactMessage::create([
            'name'            => Str::limit(trim((string) ($attrs['name'] ?? '')), 250, ''),
            'email'           => Str::limit(trim((string) ($attrs['email'] ?? '')), 250, ''),
            'subject'         => Str::limit(trim((string) ($attrs['subject'] ?? 'New contact request')), 500, ''),
            'message'         => (string) ($attrs['message'] ?? ''),
            'contact_channel' => in_array($channel, self::CHANNELS, true) ? $channel : null,
            'contact_phone'   => Str::limit(trim((string) ($attrs['phone'] ?? '')), 40, '') ?: null,
            'ip'              => $attrs['ip'] ?? null,
            'status'          => 'new',
        ]);

        self::notifyAdmin($contact);

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
     * Email the admin support inbox about a new contact request, including
     * the chosen channel + the phone/email to follow up on. Best-effort:
     * a missing recipient or a transport error never breaks the request.
     */
    public static function notifyAdmin(ContactMessage $contact): void
    {
        $recipient = trim((string) AppSetting::get('contact_recipient_email', ''));
        if ($recipient === '') {
            $recipient = trim((string) config('mail.from.address', ''));
        }
        if ($recipient === '') {
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
