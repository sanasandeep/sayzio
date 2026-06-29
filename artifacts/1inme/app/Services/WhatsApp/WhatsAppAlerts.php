<?php

namespace App\Services\WhatsApp;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort, system-initiated WhatsApp alerts to a creator (Task #2765).
 *
 * A thin helper used by the form-submission and payment notification paths
 * to ping a creator on WhatsApp. It:
 *   - resolves the creator's verified WhatsApp (phone) number;
 *   - no-ops (with a log) when no number is on file;
 *   - delegates the actual send to WhatsAppCloudApi, which already degrades
 *     to preview-mode logging when delivery credentials are absent;
 *   - never throws into the caller, so a WhatsApp hiccup can never break a
 *     form submission or a payment confirmation (mirrors how email is sent).
 */
class WhatsAppAlerts
{
    /**
     * Attempt to send a WhatsApp alert to $creator. Returns true only when the
     * Cloud API actually accepted the message; false when skipped (no number /
     * no credentials / preview mode) or on any failure.
     */
    public static function send(?User $creator, string $message): bool
    {
        if (!$creator) {
            return false;
        }

        try {
            $number = $creator->whatsappNumber();
            if ($number === null) {
                Log::info('WhatsApp alert skipped (no verified number) for user ' . $creator->id);
                return false;
            }

            return app(WhatsAppCloudApi::class)->sendText($number, $message);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp alert failed for user ' . $creator->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
