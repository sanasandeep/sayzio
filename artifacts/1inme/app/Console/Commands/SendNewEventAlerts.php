<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\EventNewAlertSent;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Location-based "new event near you" alerts (Task #3593).
 *
 * A recipient opts in via /user/settings (event_alerts_enabled + a saved
 * lat/lng + radius_km). For every public, upcoming `ics` event created
 * recently that falls inside a recipient's radius (Haversine, mirrors
 * EventsDirectoryController's "near me" filter) and that the recipient
 * hasn't already been alerted about (event_new_alerts_sent is the
 * per-recipient-per-event idempotency guard — a row there means "never
 * alert again", regardless of delivery mode), we deliver:
 *
 *   - in-app notification (NotificationService, honors the `event.new_nearby`
 *     preference toggle),
 *   - email (Emailer, honors the same preference's email channel),
 *
 * ...immediately for `event_alert_frequency = instant` recipients (this
 * command is scheduled hourly, so "instant" really means "within the hour"),
 * or batched into a single once-daily digest email/notification (all
 * pending events since their last send) for `daily_digest` recipients,
 * fired only when the recipient's **local** time (User.timezone) is 9 AM.
 *
 * 18+ gating: an event hosted by a creator with adult content enabled is
 * skipped for recipients who haven't enabled adult content themselves —
 * mirrors the /creators and /@handle age-gate policy.
 */
class SendNewEventAlerts extends Command
{
    protected $signature = 'events:send-new-alerts {--force : Bypass the 9 AM local-time gate for daily_digest recipients}';
    protected $description = 'Alert opted-in users about new public events near their saved location (instant + daily digest).';

    /** How far back to look for "new" events; idempotency table prevents repeats beyond this. */
    private const LOOKBACK_DAYS = 14;

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $recipients = User::query()
            ->where('event_alerts_enabled', true)
            ->whereNotNull('event_alert_latitude')
            ->whereNotNull('event_alert_longitude')
            ->get(['id', 'timezone', 'event_alert_latitude', 'event_alert_longitude', 'event_alert_radius_km', 'event_alert_frequency', 'adult_content_enabled']);

        if ($recipients->isEmpty()) {
            $this->info('No opted-in recipients.');
            return self::SUCCESS;
        }

        $notifier = app(NotificationService::class);
        $sentTotal = 0;

        foreach ($recipients as $user) {
            $frequency = $user->event_alert_frequency ?: 'instant';

            if ($frequency === 'daily_digest' && !$force) {
                $tz = \App\Support\PlatformTimezone::forUser($user);
                if (Carbon::now($tz)->hour !== 9) {
                    continue;
                }
            }

            $events = $this->findPendingEventsFor($user);
            if ($events->isEmpty()) {
                continue;
            }

            if ($frequency === 'daily_digest') {
                $sentTotal += $this->sendDigest($notifier, $user, $events) ? $events->count() : 0;
            } else {
                foreach ($events as $event) {
                    $sentTotal += $this->sendInstant($notifier, $user, $event) ? 1 : 0;
                }
            }
        }

        $this->info("Sent {$sentTotal} new-event alerts.");
        return self::SUCCESS;
    }

    /** Public, upcoming, nearby events this recipient hasn't been alerted about yet. */
    private function findPendingEventsFor(User $user)
    {
        $lat = (float) $user->event_alert_latitude;
        $lng = (float) $user->event_alert_longitude;
        $radiusKm = max(1, (int) ($user->event_alert_radius_km ?: 25));

        $alreadySentIds = EventNewAlertSent::where('user_id', $user->id)->pluck('link_id');

        return Link::query()
            ->where('type', 'ics')
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->where('created_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->whereNotIn('id', $alreadySentIds)
            ->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'")
            ->with(['icsData', 'user:id,name,username,adult_content_enabled,adult_flag_suspended_at'])
            ->whereHas('icsData', function ($w) use ($lat, $lng, $radiusKm) {
                $w->where('start_date', '>=', now())
                  ->whereNotNull('latitude')->whereNotNull('longitude')
                  ->whereRaw(
                      '(6371 * acos(least(1, greatest(-1,
                          cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                          + sin(radians(?)) * sin(radians(latitude))
                      )))) <= ?',
                      [$lat, $lng, $lat, $radiusKm],
                  );
            })
            ->get()
            ->filter(function (Link $event) use ($user) {
                $host = $event->user;
                $hostIsAdult = $host && (bool) $host->adult_content_enabled && empty($host->adult_flag_suspended_at);
                if ($hostIsAdult && !$user->adult_content_enabled) {
                    return false;
                }
                return true;
            })
            ->values();
    }

    private function sendInstant(NotificationService $notifier, User $user, Link $event): bool
    {
        $title = $event->title ?: optional($event->icsData)->event_name ?: 'New event';
        $when = optional($event->icsData)->start_date;
        $whenLabel = $when ? Carbon::parse($when)->format('M j, g:i A') : '';
        $url = url('/' . $event->alias);

        $notifier->notify($user, 'event.new_nearby', [
            'message'  => "New event near you: {$title}" . ($whenLabel ? " ({$whenLabel})" : ''),
            'link_id'  => $event->id,
            'title'    => $title,
            'url'      => $url,
        ]);

        if ($notifier->prefersChannel($user->id, 'event.new_nearby', 'email') && $user->email) {
            Emailer::send('events.new_nearby_alert', $user->email, [
                'title' => $title,
                'when'  => $whenLabel ?: 'Date TBD',
                'url'   => $url,
            ], ['user' => $user]);
        }

        return $this->markSent($event->id, $user->id);
    }

    /** Batch every pending event for this recipient into one digest send. */
    private function sendDigest(NotificationService $notifier, User $user, $events): bool
    {
        $lines = $events->map(function (Link $event) {
            $title = $event->title ?: optional($event->icsData)->event_name ?: 'Event';
            $when = optional($event->icsData)->start_date;
            $whenLabel = $when ? Carbon::parse($when)->format('M j, g:i A') : '';
            $url = url('/' . $event->alias);
            return trim("{$title} — {$whenLabel}") . " ({$url})";
        })->values()->all();

        $notifier->notify($user, 'event.new_nearby', [
            'message' => count($lines) === 1
                ? ('New event near you: ' . $events->first()->title)
                : (count($lines) . ' new events near you today'),
            'digest'  => $lines,
            'url'     => route('events.index'),
        ]);

        if ($notifier->prefersChannel($user->id, 'event.new_nearby', 'email') && $user->email) {
            Emailer::send('events.new_nearby_digest', $user->email, [
                'count' => (string) count($lines),
                'list'  => implode("\n", $lines),
            ], ['user' => $user]);
        }

        foreach ($events as $event) {
            $this->markSent($event->id, $user->id);
        }

        return true;
    }

    private function markSent(int $linkId, int $userId): bool
    {
        try {
            EventNewAlertSent::firstOrCreate(
                ['link_id' => $linkId, 'user_id' => $userId],
                ['sent_at' => now()],
            );
            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SendNewEventAlerts: failed to record alert-sent marker', [
                'link_id' => $linkId,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
