<?php

namespace App\Modules\User\Services\Calendar;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarEventMirror;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bidirectional sync engine.
 *
 * Pull (external → Sayzio): mirrors every event in the lookahead window as a
 * type=ics Link with IcsData. Mirrored links are tagged via calendar_event_mirror
 * so subsequent updates/deletes propagate. Users may "detach" a mirrored link
 * to stop overwriting it.
 *
 * Push (Sayzio → external): created on demand by IcsLinkController when the user
 * picks a "save to my calendar" account on save.
 */
class CalendarSyncService
{
    public function __construct(private CalendarProviderRegistry $registry) {}

    public function syncAccount(CalendarAccount $account, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];
        if (!$account->mirror_enabled) {
            return $stats;
        }

        $account->update(['last_sync_status' => 'running', 'last_sync_error' => null]);

        try {
            $provider = $this->registry->get($account->provider);
            $from = $from ? Carbon::instance($from) : now()->subDays(7);
            $to   = $to   ? Carbon::instance($to)   : now()->addMonths(6);

            $seenExternalIds = [];
            foreach ($provider->listEvents($account, $from, $to) as $event) {
                try {
                    $result = $this->upsertMirror($account, $event);
                    $stats[$result] = ($stats[$result] ?? 0) + 1;
                    $seenExternalIds[] = $event['external_event_id'];
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::warning('Calendar event upsert failed', ['event' => $event['external_event_id'] ?? null, 'err' => $e->getMessage()]);
                }
            }

            // Anything previously mirrored from this account but not seen this run is gone — soft delete the link.
            // SAFETY: skip stale cleanup entirely if we saw zero events AND zero errors — this is most likely a transient
            // provider outage or empty pagination response, not a legit "everything was deleted" state. Deleting blindly
            // would wipe every mirrored Event Invite link. Only run cleanup when at least one event was successfully seen
            // OR the pull window genuinely is empty AND no errors occurred during pagination.
            $hadErrors = $stats['errors'] > 0;
            $sawEvents = count($seenExternalIds) > 0;
            if (!$hadErrors && $sawEvents) {
                $stale = CalendarEventMirror::where('calendar_account_id', $account->id)
                    ->where('source', 'pull')
                    ->where('detached', false)
                    ->whereNotIn('external_event_id', $seenExternalIds)
                    ->get();
                foreach ($stale as $m) {
                    if ($m->link) $m->link->delete();
                    $m->delete();
                    $stats['deleted']++;
                }
            } else {
                Log::info('Calendar sync: skipping stale cleanup', [
                    'account_id' => $account->id, 'had_errors' => $hadErrors, 'saw_events' => $sawEvents,
                ]);
            }

            $account->update([
                'last_sync_status' => 'ok',
                'last_synced_at'   => now(),
                'last_sync_error'  => null,
            ]);
        } catch (\Throwable $e) {
            $account->update([
                'last_sync_status' => 'error',
                'last_sync_error'  => Str::limit($e->getMessage(), 500),
            ]);
            $stats['errors']++;
            Log::error('Calendar sync failed', ['account' => $account->id, 'err' => $e->getMessage()]);
        }

        return $stats;
    }

    /** Returns 'created' | 'updated' | 'skipped'. */
    protected function upsertMirror(CalendarAccount $account, array $event): string
    {
        $mirror = CalendarEventMirror::where('calendar_account_id', $account->id)
            ->where('external_event_id', $event['external_event_id'])
            ->first();

        if ($mirror && $mirror->detached) {
            $mirror->update(['last_seen_at' => now()]);
            return 'skipped';
        }

        return DB::transaction(function () use ($account, $event, $mirror) {
            if ($mirror) {
                $link = $mirror->link;
                $isNew = false;
            } else {
                $link = new Link();
                $isNew = true;
            }

            $link->user_id    = $account->user_id;
            $link->type       = 'ics';
            $link->title      = $event['summary'] ?: '(no title)';
            $link->is_active  = true;
            if ($isNew) {
                $link->alias = $this->makeAlias($event['summary'] ?: 'event');
            }
            // Mark in settings so UI shows "synced" badge.
            $settings = (array) ($link->settings ?? []);
            $settings['calendar_sync'] = [
                'account_id' => $account->id,
                'provider'   => $account->provider,
                'event_id'   => $event['external_event_id'],
                'mirror_id'  => $mirror->id ?? null,
                'origin'     => 'pull',
            ];
            $link->settings = $settings;
            $link->save();

            $orgName  = $event['organizer']['name']  ?? null;
            $orgEmail = $event['organizer']['email'] ?? null;

            IcsData::updateOrCreate(
                ['link_id' => $link->id],
                [
                    'event_name'      => $event['summary'] ?: '(no title)',
                    'description'     => $event['description'],
                    'location'        => $event['location'],
                    'organizer'       => $orgName,
                    'organizer_email' => $orgEmail,
                    'start_date'      => $event['start'],
                    'end_date'        => $event['end'],
                    'timezone'        => $event['timezone'] ?: 'UTC',
                    'all_day'         => (bool) $event['all_day'],
                    'url'             => $event['url'],
                    'recurrence_freq' => null,  // singleEvents=true expands; we mirror per-instance
                    'extra_schedules' => [],
                ]
            );

            if (!$mirror) {
                $mirror = CalendarEventMirror::create([
                    'calendar_account_id'  => $account->id,
                    'link_id'              => $link->id,
                    'external_calendar_id' => $event['external_calendar_id'],
                    'external_event_id'    => $event['external_event_id'],
                    'etag'                 => $event['etag'],
                    'ical_uid'             => $event['ical_uid'],
                    'source'               => 'pull',
                    'external_updated_at'  => $event['updated_at'],
                    'last_seen_at'         => now(),
                ]);
                $link->settings = array_merge((array) $link->settings, [
                    'calendar_sync' => array_merge($link->settings['calendar_sync'], ['mirror_id' => $mirror->id]),
                ]);
                $link->save();
            } else {
                $mirror->update([
                    'etag'                => $event['etag'],
                    'ical_uid'            => $event['ical_uid'],
                    'external_updated_at' => $event['updated_at'],
                    'last_seen_at'        => now(),
                ]);
            }

            return $isNew ? 'created' : 'updated';
        });
    }

    public function pushLink(CalendarAccount $account, Link $link): void
    {
        if (!$account->push_enabled) return;
        $ics = $link->icsData;
        if (!$ics) return;

        $provider = $this->registry->get($account->provider);
        $payload  = [
            'summary'     => $ics->event_name,
            'description' => $ics->description,
            'location'    => $ics->location,
            'start'       => $ics->start_date,
            'end'         => $ics->end_date,
            'timezone'    => $ics->timezone ?: 'UTC',
            'all_day'     => (bool) $ics->all_day,
            'url'         => $ics->url,
            'organizer'   => $ics->organizer ? ['name' => $ics->organizer, 'email' => $ics->organizer_email] : null,
        ];

        $existing = CalendarEventMirror::where('calendar_account_id', $account->id)
            ->where('link_id', $link->id)->first();

        try {
            if ($existing) {
                $res = $provider->updateEvent($account, $existing->external_event_id, $payload);
                $existing->update([
                    'etag' => $res['etag'] ?? null,
                    'external_updated_at' => now(),
                    'last_seen_at' => now(),
                ]);
            } else {
                $res = $provider->createEvent($account, $payload);
                CalendarEventMirror::create([
                    'calendar_account_id'  => $account->id,
                    'link_id'              => $link->id,
                    'external_calendar_id' => $res['external_calendar_id'] ?? null,
                    'external_event_id'    => $res['external_event_id'],
                    'etag'                 => $res['etag'] ?? null,
                    'ical_uid'             => $res['ical_uid'] ?? null,
                    'source'               => 'push',
                    'external_updated_at'  => now(),
                    'last_seen_at'         => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Calendar push failed', ['link' => $link->id, 'err' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deletePushedLink(CalendarAccount $account, Link $link): void
    {
        $mirror = CalendarEventMirror::where('calendar_account_id', $account->id)
            ->where('link_id', $link->id)->where('source', 'push')->first();
        if (!$mirror) return;
        try {
            $this->registry->get($account->provider)->deleteEvent($account, $mirror->external_event_id);
        } catch (\Throwable $e) {
            Log::warning('Calendar push-delete failed', ['link' => $link->id, 'err' => $e->getMessage()]);
        }
        $mirror->delete();
    }

    /**
     * Push every event of a followable {@see Calendar} to the connected
     * external calendar (full export, or a date range when $from/$to given).
     * Reuses the same provider + CalendarEventMirror machinery as `ics` links,
     * but keys mirrors on calendar_event_id instead of link_id.
     *
     * @return array{pushed:int,failed:int,total:int}
     */
    public function pushCalendar(CalendarAccount $account, Calendar $calendar, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $events = $calendar->events()
            ->when($from, fn ($q) => $q->where('start_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_at', '<=', $to))
            ->orderBy('start_at')
            ->get();

        $pushed = 0;
        $failed = 0;
        foreach ($events as $event) {
            try {
                $this->pushCalendarEvent($account, $event);
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Calendar event push failed', ['event' => $event->id, 'err' => $e->getMessage()]);
            }
        }

        return ['pushed' => $pushed, 'failed' => $failed, 'total' => $events->count()];
    }

    /** Create or update a single followable CalendarEvent in the external calendar. */
    public function pushCalendarEvent(CalendarAccount $account, CalendarEvent $event): void
    {
        if (!$account->push_enabled) {
            return;
        }
        $start = $event->start_at;
        if (!$start) {
            return;
        }
        $end = $event->end_at ?: (clone $start)->addHour();

        $provider = $this->registry->get($account->provider);
        $payload  = [
            'summary'     => $event->title,
            'description' => $event->description,
            'location'    => $event->location,
            'start'       => $start,
            'end'         => $end,
            'timezone'    => $event->effectiveTimezone() ?: 'UTC',
            'all_day'     => (bool) $event->all_day,
            'url'         => $event->payment_url,
        ];

        $existing = CalendarEventMirror::where('calendar_account_id', $account->id)
            ->where('calendar_event_id', $event->id)->first();

        if ($existing) {
            $res = $provider->updateEvent($account, $existing->external_event_id, $payload);
            $existing->update([
                'etag'                => $res['etag'] ?? null,
                'external_updated_at' => now(),
                'last_seen_at'        => now(),
            ]);
        } else {
            $res = $provider->createEvent($account, $payload);
            CalendarEventMirror::create([
                'calendar_account_id'  => $account->id,
                'link_id'              => null,
                'calendar_event_id'    => $event->id,
                'external_calendar_id' => $res['external_calendar_id'] ?? null,
                'external_event_id'    => $res['external_event_id'],
                'etag'                 => $res['etag'] ?? null,
                'ical_uid'             => $res['ical_uid'] ?? null,
                'source'               => 'push',
                'external_updated_at'  => now(),
                'last_seen_at'         => now(),
            ]);
        }
    }

    /** Delete a previously pushed followable CalendarEvent from the external calendar. */
    public function deletePushedEvent(CalendarAccount $account, CalendarEvent $event): void
    {
        $mirror = CalendarEventMirror::where('calendar_account_id', $account->id)
            ->where('calendar_event_id', $event->id)->where('source', 'push')->first();
        if (!$mirror) {
            return;
        }
        try {
            $this->registry->get($account->provider)->deleteEvent($account, $mirror->external_event_id);
        } catch (\Throwable $e) {
            Log::warning('Calendar event push-delete failed', ['event' => $event->id, 'err' => $e->getMessage()]);
        }
        $mirror->delete();
    }

    private function makeAlias(string $base): string
    {
        $slug = Str::slug(Str::limit($base, 40, ''));
        if ($slug === '') $slug = 'event';
        $candidate = $slug . '-' . Str::random(5);
        // Ensure uniqueness
        while (Link::where('alias', $candidate)->exists()) {
            $candidate = $slug . '-' . Str::random(6);
        }
        return $candidate;
    }
}
