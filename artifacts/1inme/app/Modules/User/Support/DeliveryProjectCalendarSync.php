<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\DeliveryProjectTask;

/**
 * Task #3584 — keeps a {@see DeliveryProjectTask}'s start/due dates in sync
 * with a single all-day {@see CalendarEvent} on its project's calendar.
 *
 * Called from `DeliveryProjectTask::booted()` (saved/deleting hooks). Writes
 * back to the task via `saveQuietly()`/`forceFill()` so re-persisting the
 * `calendar_event_id` never re-fires the `saved` hook (no recursion) and
 * never re-touches `updated_at` in a way that would loop the sync.
 */
class DeliveryProjectCalendarSync
{
    public static function syncTaskEvent(DeliveryProjectTask $task): void
    {
        $hasDates = $task->start_date || $task->due_date;

        if (!$hasDates) {
            static::deleteTaskEvent($task, true);
            return;
        }

        $project = $task->project ?? $task->project()->first();
        if (!$project) {
            return;
        }

        $calendar = $project->ensureCalendar();

        $start = $task->start_date ?? $task->due_date;
        $end   = $task->due_date ?? $task->start_date;

        $attributes = [
            'calendar_id'  => $calendar->id,
            'user_id'      => $project->created_by_user_id,
            'title'        => $task->title,
            'description'  => 'Delivery project task from "' . $project->title . '".',
            'start_at'     => $start->copy()->startOfDay(),
            'end_at'       => $end->copy()->endOfDay(),
            'timezone'     => $calendar->timezone,
            'all_day'      => true,
        ];

        $event = $task->calendar_event_id ? $task->calendarEvent()->first() : null;

        if ($event) {
            $event->fill($attributes)->save();
        } else {
            $event = $calendar->events()->create($attributes);
            $task->forceFill(['calendar_event_id' => $event->id])->saveQuietly();
        }
    }

    /**
     * @param bool $updateTask False when the task itself is mid-delete (its
     *   own row is about to disappear, so there's no point writing to it).
     */
    public static function deleteTaskEvent(DeliveryProjectTask $task, bool $updateTask = false): void
    {
        if (!$task->calendar_event_id) {
            return;
        }

        $event = $task->calendarEvent()->first();
        $event?->delete();

        if ($updateTask && $task->exists) {
            $task->forceFill(['calendar_event_id' => null])->saveQuietly();
        }
    }
}
