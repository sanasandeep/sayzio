<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only health view of the admin contact-notification pipeline for the
 * admin dashboard banner.
 *
 * Leads (callback / WhatsApp / email quick-contact requests + the public
 * contact form) are always persisted as `contact_messages`, but the admin is
 * only actively notified by email when a recipient address is configured —
 * either the `contact_recipient_email` admin setting or, as a fallback, the
 * platform `mail.from.address`. When neither is set, {@see
 * \App\Modules\Common\Services\QuickContactService::notifyAdmin()} can't send
 * anything, so on a fresh deploy real leads pile up in the inbox with nobody
 * notified.
 *
 * This surface makes that silent state detectable: it resolves the effective
 * recipient with the exact same precedence and reports whether it's configured
 * plus how many leads have already landed, so the dashboard can warn instead of
 * swallowing the problem.
 */
class ContactRecipientHealth
{
    private const CACHE_KEY = 'contact_recipient_health';
    private const TTL = 120;

    /**
     * Resolve the effective admin-notification recipient using the same
     * precedence as the notifier: the `contact_recipient_email` admin setting
     * first, then the platform `mail.from.address` fallback. Returns an empty
     * string when neither is configured.
     */
    public static function recipient(): string
    {
        $recipient = trim((string) AppSetting::get('contact_recipient_email', ''));
        if ($recipient === '') {
            $recipient = trim((string) config('mail.from.address', ''));
        }
        return $recipient;
    }

    /** Whether any admin-notification recipient is configured. */
    public static function isConfigured(): bool
    {
        return self::recipient() !== '';
    }

    /** Cached snapshot for the dashboard banner. */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            return self::compute();
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort; a cache miss just recomputes
        }
    }

    /**
     * @return array{
     *   available:bool,
     *   configured:bool,
     *   recipient:string,
     *   pending_leads:int,
     *   total_leads:int
     * }
     */
    public static function compute(): array
    {
        $configured = self::isConfigured();

        // Lead counts are only meaningful (and only worth querying) when no
        // recipient is configured — that's the one case the banner cares about.
        $pending = 0;
        $total = 0;
        $available = true;

        if (! $configured) {
            try {
                $total = ContactMessage::count();
                $pending = ContactMessage::where('status', 'new')->count();
            } catch (\Throwable $e) {
                // Un-migrated environment: degrade gracefully rather than 500
                // the dashboard. Without the table there are no leads at risk.
                $available = false;
            }
        }

        return [
            'available'     => $available,
            'configured'    => $configured,
            'recipient'     => self::recipient(),
            'pending_leads' => $pending,
            'total_leads'   => $total,
        ];
    }
}
