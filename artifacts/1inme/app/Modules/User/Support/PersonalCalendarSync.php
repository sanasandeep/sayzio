<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\User;

/**
 * Task #6477 — mirrors Task Board card due dates and Dialer note/checklist
 * reminders onto a per-user "Tasks & Reminders" calendar so they show up in
 * "My Calendar" (web + mobile) and flow through ICS export / Google push like
 * any other owned calendar event.
 *
 * Follows the {@see DeliveryProjectCalendarSync} pattern: each source row
 * stores a `calendar_event_id` back-reference, syncs from its model's
 * saved/deleting hooks, and writes back via `forceFill()->saveQuietly()` so
 * re-persisting the pointer never re-fires the hook (no recursion).
 *
 * One-way by design: the card/note is the source of truth; editing the
 * mirrored event never writes back.
 */
class PersonalCalendarSync
{
    /** Slug prefix identifying the auto-provisioned personal calendar. */
    public const SLUG_PREFIX = 'tr-';

    public const PREF_KEY        = 'calendar_mirror';
    public const PREF_TASK_DUES  = 'task_due_dates';
    public const PREF_REMINDERS  = 'note_reminders';

    /** Per-user opt-out — both sources default ON. */
    public static function enabledFor(?User $user, string $source): bool
    {
        if (!$user) {
            return false;
        }

        return (bool) (($user->settings[self::PREF_KEY][$source] ?? true));
    }

    /**
     * The user's personal "Tasks & Reminders" calendar, created lazily on
     * first mirrored item so users with no due dates/reminders never grow an
     * empty calendar row. Not public, not followable, no bridged Link.
     */
    public static function ensureCalendar(User $user): Calendar
    {
        return Calendar::firstOrCreate(
            ['user_id' => $user->id, 'slug' => self::SLUG_PREFIX . $user->id],
            [
                'title'       => 'Tasks & Reminders',
                'description' => 'Auto-generated from your task due dates and note reminders.',
                'is_public'   => false,
                'timezone'    => \App\Support\PlatformTimezone::forUser($user),
            ]
        );
    }

    /* ── Task Board cards ─────────────────────────────────────────── */

    public static function syncTaskCard(TaskCard $card): void
    {
        // Always re-read the owner: cached relation instances can carry stale
        // `settings` (the opt-out toggles) from earlier in the request.
        $owner = $card->creator()->first();

        if (!$card->due_date || $card->archived_at || !$owner || !static::enabledFor($owner, self::PREF_TASK_DUES)) {
            static::deleteTaskCardEvent($card, true);
            return;
        }

        $calendar = static::ensureCalendar($owner);

        $attributes = [
            'calendar_id' => $calendar->id,
            'user_id'     => $owner->id,
            'title'       => 'Task: ' . $card->title,
            'description' => static::taskCardDescription($card),
            'start_at'    => $card->due_date->copy()->startOfDay(),
            'end_at'      => $card->due_date->copy()->endOfDay(),
            'timezone'    => $calendar->effectiveTimezone(),
            'all_day'     => true,
        ];

        $event = $card->calendar_event_id ? $card->calendarEvent()->first() : null;

        if ($event) {
            $event->fill($attributes)->save();
        } else {
            $event = $calendar->events()->create($attributes);
            $card->forceFill(['calendar_event_id' => $event->id])->saveQuietly();
        }
    }

    protected static function taskCardDescription(TaskCard $card): string
    {
        $board = $card->board ?? $card->board()->first();
        $text  = 'Task board card' . ($board ? ' from "' . $board->title . '"' : '') . '.';

        if ($board) {
            try {
                $text .= ' Open the board: ' . route('user.tasks.show', $board->id);
            } catch (\Throwable) {
                // Route registry unavailable (e.g. queue context) — skip the link.
            }
        }

        return $text;
    }

    /**
     * @param bool $updateSource False when the card itself is mid-delete.
     */
    public static function deleteTaskCardEvent(TaskCard $card, bool $updateSource = false): void
    {
        if (!$card->calendar_event_id) {
            return;
        }

        $card->calendarEvent()->first()?->delete();

        if ($updateSource && $card->exists) {
            $card->forceFill(['calendar_event_id' => null])->saveQuietly();
        }
    }

    /* ── Dialer notes / checklists ────────────────────────────────── */

    public static function syncDialerNote(DialerNote $note): void
    {
        // Always re-read the owner (see syncTaskCard — stale settings guard).
        $owner = $note->user()->first();

        if (!$note->remind_at || !$owner || !static::enabledFor($owner, self::PREF_REMINDERS)) {
            static::deleteDialerNoteEvent($note, true);
            return;
        }

        $calendar = static::ensureCalendar($owner);

        $title = trim((string) $note->title);
        if ($title === '') {
            $title = \Illuminate\Support\Str::limit(trim((string) $note->body), 60) ?: 'Dialer note';
        }

        $attributes = [
            'calendar_id' => $calendar->id,
            'user_id'     => $owner->id,
            'title'       => 'Reminder: ' . $title,
            'description' => static::dialerNoteDescription($note),
            'start_at'    => $note->remind_at->copy(),
            'end_at'      => $note->remind_at->copy()->addMinutes(30),
            'timezone'    => $calendar->effectiveTimezone(),
            'all_day'     => false,
        ];

        $event = $note->calendar_event_id ? $note->calendarEvent()->first() : null;

        if ($event) {
            $event->fill($attributes)->save();
        } else {
            $event = $calendar->events()->create($attributes);
            $note->forceFill(['calendar_event_id' => $event->id])->saveQuietly();
        }
    }

    protected static function dialerNoteDescription(DialerNote $note): string
    {
        $text = $note->kind === 'checklist' ? 'Dialer checklist reminder.' : 'Dialer note reminder.';

        try {
            $text .= ' Open your notes: ' . route('user.dialer.notes');
        } catch (\Throwable) {
            // Route registry unavailable — skip the link.
        }

        return $text;
    }

    /**
     * @param bool $updateSource False when the note itself is mid-delete.
     */
    public static function deleteDialerNoteEvent(DialerNote $note, bool $updateSource = false): void
    {
        if (!$note->calendar_event_id) {
            return;
        }

        $note->calendarEvent()->first()?->delete();

        if ($updateSource && $note->exists) {
            $note->forceFill(['calendar_event_id' => null])->saveQuietly();
        }
    }

    /* ── Opt-out cleanup + backfill ───────────────────────────────── */

    /**
     * Persist the two per-source toggles and mirror/unmirror existing rows to
     * match: turning a source OFF removes its mirrored events, turning it ON
     * backfills current data.
     */
    public static function applyPreferences(User $user, bool $taskDues, bool $reminders): void
    {
        $settings = (array) ($user->settings ?? []);
        $settings[self::PREF_KEY] = [
            self::PREF_TASK_DUES => $taskDues,
            self::PREF_REMINDERS => $reminders,
        ];
        $user->forceFill(['settings' => $settings])->save();

        if ($taskDues) {
            static::backfillTaskCards($user);
        } else {
            static::removeTaskCardEvents($user);
        }

        if ($reminders) {
            static::backfillDialerNotes($user);
        } else {
            static::removeDialerNoteEvents($user);
        }
    }

    /** Mirror every un-mirrored, dated card owned by $user (or all users when null). */
    public static function backfillTaskCards(?User $user = null): int
    {
        $count = 0;
        TaskCard::withoutGlobalScopes()
            ->whereNotNull('due_date')
            ->whereNull('archived_at')
            ->whereNull('calendar_event_id')
            ->when($user, fn ($q) => $q->where('created_by_user_id', $user->id))
            ->orderBy('id')
            ->chunkById(200, function ($cards) use (&$count) {
                foreach ($cards as $card) {
                    static::syncTaskCard($card);
                    $count++;
                }
            });

        return $count;
    }

    /** Mirror every un-mirrored note with a future reminder for $user (or all users). */
    public static function backfillDialerNotes(?User $user = null): int
    {
        $count = 0;
        DialerNote::query()
            ->whereNotNull('remind_at')
            ->where('remind_at', '>=', now())
            ->whereNull('calendar_event_id')
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('id')
            ->chunkById(200, function ($notes) use (&$count) {
                foreach ($notes as $note) {
                    static::syncDialerNote($note);
                    $count++;
                }
            });

        return $count;
    }

    public static function removeTaskCardEvents(User $user): void
    {
        TaskCard::withoutGlobalScopes()
            ->whereNotNull('calendar_event_id')
            ->where('created_by_user_id', $user->id)
            ->orderBy('id')
            ->chunkById(200, function ($cards) {
                foreach ($cards as $card) {
                    static::deleteTaskCardEvent($card, true);
                }
            });
    }

    public static function removeDialerNoteEvents(User $user): void
    {
        DialerNote::query()
            ->whereNotNull('calendar_event_id')
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->chunkById(200, function ($notes) {
                foreach ($notes as $note) {
                    static::deleteDialerNoteEvent($note, true);
                }
            });
    }

    /** Current preference pair for API/web surfaces. */
    public static function preferences(User $user): array
    {
        return [
            self::PREF_TASK_DUES => static::enabledFor($user, self::PREF_TASK_DUES),
            self::PREF_REMINDERS => static::enabledFor($user, self::PREF_REMINDERS),
        ];
    }
}
