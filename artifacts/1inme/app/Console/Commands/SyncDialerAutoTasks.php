<?php

namespace App\Console\Commands;

use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\EventInterest;
use App\Modules\User\Models\EventTicket;
use Illuminate\Console\Command;

/**
 * Task #5508: auto-added tasks in the dialer notes / to-do list.
 *
 * Creates (and refreshes) DialerNote rows from platform activity so upcoming
 * commitments surface as tasks without the user typing anything:
 *
 * - Events the user bought a ticket for or marked "interested", starting
 *   within the next 60 days → one task per (user, event link), reminded
 *   2 hours before start.
 * - Scheduled dialer call-backs still in the future → one task per lookup
 *   row, reminded at the callback time. (The existing callback command still
 *   delivers its own due alert; the task gives it a visible checklist home.)
 *
 * Idempotent via the (user_id, source_type, source_id) unique upsert. Tasks
 * the user marked done are never reopened or rewritten.
 */
class SyncDialerAutoTasks extends Command
{
    protected $signature = 'dialer:sync-auto-tasks';
    protected $description = 'Create/refresh auto tasks in dialer notes from upcoming events and call-backs.';

    public function handle(): int
    {
        $count = 0;
        $horizon = now()->addDays(60);

        // ── Ticketed events ──────────────────────────────────────────────
        EventTicket::query()
            ->whereNotNull('buyer_user_id')
            ->whereIn('status', ['valid', 'checked_in'])
            ->whereHas('link.icsData', fn ($q) => $q
                ->where('start_date', '>=', now())
                ->where('start_date', '<=', $horizon))
            ->with('link.icsData')
            ->chunkById(200, function ($tickets) use (&$count) {
                foreach ($tickets as $t) {
                    if (!$t->link) continue;
                    $this->upsertEventTask((int) $t->buyer_user_id, $t->link);
                    $count++;
                }
            });

        // ── "Interested" events ──────────────────────────────────────────
        EventInterest::query()
            ->where('status', 'interested')
            ->whereHas('link.icsData', fn ($q) => $q
                ->where('start_date', '>=', now())
                ->where('start_date', '<=', $horizon))
            ->with('link.icsData')
            ->chunkById(200, function ($rows) use (&$count) {
                foreach ($rows as $i) {
                    if (!$i->link) continue;
                    $this->upsertEventTask((int) $i->user_id, $i->link);
                    $count++;
                }
            });

        // ── Future call-backs ────────────────────────────────────────────
        DialerLookup::query()
            ->whereNotNull('callback_at')
            ->where('callback_at', '>', now())
            ->with('contact')
            ->chunkById(200, function ($rows) use (&$count) {
                foreach ($rows as $row) {
                    $who = $row->contact?->nameForDisplay() ?: ($row->number_e164 ?: 'a contact');
                    $this->upsertTask((int) $row->user_id, DialerNote::SOURCE_CALLBACK, (int) $row->id, [
                        'title' => "Call back {$who}",
                        'body' => $row->note ?: null,
                        'number_e164' => $row->number_e164,
                        'remind_at' => $row->callback_at,
                    ]);
                    $count++;
                }
            });

        $this->info("Synced {$count} dialer auto tasks.");
        return self::SUCCESS;
    }

    private function upsertEventTask(int $userId, $link): void
    {
        if ($userId <= 0) return;
        $ics = $link->icsData;
        $start = $ics?->start_date;
        $when = $start ? $start->copy()->subHours(2) : null;

        $bodyBits = array_filter([
            $start ? 'Starts ' . $start->toDayDateTimeString() : null,
            $ics?->location ? 'At ' . $ics->location : null,
            url('/' . $link->alias),
        ]);

        $this->upsertTask($userId, DialerNote::SOURCE_EVENT, (int) $link->id, [
            'title' => 'Event: ' . ($ics?->event_name ?: $link->title ?: $link->alias),
            'body' => implode("\n", $bodyBits) ?: null,
            'remind_at' => $when && $when->isFuture() ? $when : $start,
        ]);
    }

    /** Create or refresh one auto task; never touches a task marked done. */
    private function upsertTask(int $userId, string $sourceType, int $sourceId, array $attrs): void
    {
        $existing = DialerNote::where('user_id', $userId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if (!$existing) {
            DialerNote::create(array_merge($attrs, [
                'user_id' => $userId,
                'kind' => 'note',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ]));
            return;
        }

        if ($existing->done) return;

        $updates = [];
        foreach (['title', 'body', 'number_e164'] as $k) {
            if (array_key_exists($k, $attrs) && $attrs[$k] !== $existing->{$k}) {
                $updates[$k] = $attrs[$k];
            }
        }
        $newRemind = $attrs['remind_at'] ?? null;
        if ((string) $newRemind !== (string) $existing->remind_at) {
            $updates['remind_at'] = $newRemind;
            $updates['reminder_sent_at'] = null; // re-arm on reschedule
        }
        if ($updates !== []) $existing->update($updates);
    }
}
