<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends per-recipient in-app reminders for calendar events happening *today*.
 * A recipient is any user who OWNS or FOLLOWS a calendar that has an event
 * starting on their local calendar day.
 *
 * The command is *recipient-timezone aware*: run hourly by the scheduler, it
 * only fires for a recipient whose **local** time is currently 8 AM (taken
 * from their User.timezone). We dedupe per recipient per event per local day
 * (UTC window against `created_at`) so reruns within the same day are
 * idempotent. Pass --force to bypass the 8-AM gate (tests / ad-hoc runs).
 *
 * Named `calendars:send-today-reminders` to avoid colliding with the existing
 * `calendars:sync` (Google account sync) command.
 */
class SendCalendarTodayReminders extends Command
{
    protected $signature = 'calendars:send-today-reminders {--force : Run for all recipients regardless of local hour}';
    protected $description = 'Notify calendar owners and followers about events happening today (recipient-tz aware).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $sent  = 0;

        // Build recipient → calendar-id map: owners of any calendar plus
        // followers of any public calendar. Keyed by user id so each person
        // is processed once against the union of calendars they care about.
        $recipientCalendars = []; // [userId => array<int calendarId>]

        Calendar::query()->select('id', 'user_id', 'is_public')->chunk(500, function ($cals) use (&$recipientCalendars) {
            foreach ($cals as $cal) {
                $recipientCalendars[$cal->user_id][] = $cal->id;
            }
        });

        CalendarFollow::query()
            ->select('calendar_follows.follower_id', 'calendar_follows.calendar_id')
            ->join('calendars', 'calendars.id', '=', 'calendar_follows.calendar_id')
            ->where('calendars.is_public', true)
            ->chunk(1000, function ($rows) use (&$recipientCalendars) {
                foreach ($rows as $row) {
                    $recipientCalendars[$row->follower_id][] = $row->calendar_id;
                }
            });

        if (empty($recipientCalendars)) {
            $this->info('No calendar recipients.');
            return self::SUCCESS;
        }

        $users = User::query()
            ->whereIn('id', array_keys($recipientCalendars))
            ->get(['id', 'timezone']);

        foreach ($users as $user) {
            $tz = \App\Support\PlatformTimezone::forUser($user);
            $nowLocal = Carbon::now($tz);

            // Schedule fires hourly; only do real work at 8 AM local time.
            if (!$force && $nowLocal->hour !== 8) {
                continue;
            }

            $calendarIds = array_values(array_unique($recipientCalendars[$user->id] ?? []));
            if (empty($calendarIds)) {
                continue;
            }

            $sent += $this->remindUser($user->id, $tz, $nowLocal, $calendarIds);
        }

        $this->info("Sent {$sent} calendar event reminders.");
        return self::SUCCESS;
    }

    /** Notify one recipient about each of today's events across their calendars. */
    private function remindUser(int $userId, string $tz, Carbon $nowLocal, array $calendarIds): int
    {
        $sent = 0;

        // Recipient's local calendar day, expressed as a UTC window for both
        // the event lookup (start_at is stored UTC) and the dedupe query.
        $dayStartUtc = $nowLocal->copy()->startOfDay()->timezone('UTC');
        $dayEndUtc   = $nowLocal->copy()->endOfDay()->timezone('UTC');

        CalendarEvent::query()
            ->whereIn('calendar_id', $calendarIds)
            ->whereBetween('start_at', [$dayStartUtc, $dayEndUtc])
            ->with('calendar:id,title')
            ->orderBy('start_at')
            ->chunk(200, function ($events) use ($userId, $tz, $dayStartUtc, $dayEndUtc, &$sent) {
                foreach ($events as $event) {
                    // Per-recipient, per-event, per-local-day dedupe.
                    $already = UserNotification::where('user_id', $userId)
                        ->where('type', 'calendar.event_today')
                        ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc])
                        ->where('data->event_id', $event->id)
                        ->exists();
                    if ($already) {
                        continue;
                    }

                    $eventTz = $event->timezone ?: $tz;
                    $when = $event->all_day
                        ? 'all day'
                        : Carbon::parse($event->start_at, 'UTC')->timezone($eventTz)->format('g:i A');

                    UserNotification::create([
                        'user_id' => $userId,
                        'type'    => 'calendar.event_today',
                        'data'    => [
                            'message'       => 'Today: ' . $event->title . ' (' . $when . ')',
                            'event_id'      => $event->id,
                            'calendar_id'   => $event->calendar_id,
                            'calendar_name' => optional($event->calendar)->title,
                            'url'           => route('user.calendars.mine'),
                        ],
                        'created_at' => now(),
                    ]);
                    $sent++;
                }
            });

        return $sent;
    }
}
