<?php

namespace App\Console\Commands;

use App\Mail\EventRsvpReminderMail;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Hourly: email confirmed RSVP guests N hours before the next occurrence
 * of their event. The reminder window is read from the link's RSVP
 * settings (`rsvp_settings.reminder_hours_before`, default 24h, set to 0
 * to disable). Idempotent — each (rsvp_id, occurrence_iso) pair is
 * recorded in cache to suppress duplicates.
 */
class SendEventRsvpReminders extends Command
{
    protected $signature   = 'events:send-rsvp-reminders';
    protected $description = 'Email confirmed RSVP guests before each event occurrence.';

    public function handle(): int
    {
        $now    = now();
        $window = $now->copy()->addHours(72);
        $sent   = 0;

        $links = Link::query()
            ->withoutGlobalScope('workspace')
            ->where('type', 'ics')
            ->whereIn('id', function ($q) {
                $q->select('link_id')->from('rsvps')->where('status', 'confirmed');
            })
            ->with('icsData')
            ->cursor();

        foreach ($links as $link) {
            $s = (array) ($link->settings ?? []);
            // Task #3674: RSVP is available by default now, so reminders must
            // key off the same isRsvpAvailable() gate, not the legacy toggle.
            if (!\App\Modules\Common\Controllers\RedirectController::isRsvpAvailable($link)) continue;

            $rs = (array) ($s['rsvp_settings'] ?? []);
            $hours = isset($rs['reminder_hours_before']) ? (int) $rs['reminder_hours_before'] : 24;
            if ($hours <= 0) continue;
            if (!$link->icsData) continue;

            $occurrences = $link->icsData->upcomingOccurrences(8, $now);
            if (empty($occurrences)) continue;

            foreach ($occurrences as $occ) {
                $diffH = $now->diffInHours($occ['start'], false);
                if ($diffH < 0) continue;
                if ($diffH > $hours || $diffH < ($hours - 1)) continue;

                $occIso = $occ['start']->format('c');
                $rsvps = Rsvp::query()
                    ->where('link_id', $link->id)
                    ->where('status', 'confirmed')
                    ->whereIn('response', ['yes', 'maybe'])
                    ->whereNotNull('email')
                    ->get();

                foreach ($rsvps as $r) {
                    $cacheKey = 'rsvp_reminder:' . $r->id . ':' . md5($occIso);
                    if (\Cache::has($cacheKey)) continue;
                    try {
                        \App\Modules\Common\Services\Emailer::sendMailable('events.rsvp_reminder', $r->email, new EventRsvpReminderMail($link, $r, $occ['start']), ['title' => $link->title], ['related' => $link, 'user' => $link->user_id]);
                        \Cache::put($cacheKey, 1, now()->addDays(30));
                        $sent++;
                    } catch (\Throwable $e) {
                        $this->warn("Failed to send reminder for rsvp {$r->id}: {$e->getMessage()}");
                    }
                }
            }
        }

        $this->info("Sent {$sent} RSVP reminder(s).");
        return self::SUCCESS;
    }
}
