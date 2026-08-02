<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Shared cancel / reactivate business logic for event (`ics`) links.
 *
 * Extracted so the web ({@see \App\Modules\User\Controllers\IcsLinkController})
 * and mobile API ({@see \App\Modules\Api\Controllers\EventApiController}) cancel
 * flows can't drift: cancellation state is additive settings JSON
 * (`event_cancelled` + `event_cancelled_at`) so there's no migration, and any
 * bound calendar is kept in sync so the STATUS:CANCELLED VEVENT propagates to
 * subscribers where sync is enabled.
 */
class EventCancellationService
{
    /**
     * Mark an event as cancelled. Persists the settings state + timestamp and
     * pushes the cancelled VEVENT to any bound calendar. Does NOT send the
     * guest broadcast — callers decide whether/when to notify.
     */
    public function cancel(Link $link): Link
    {
        $settings = (array) ($link->settings ?? []);
        $settings['event_cancelled']    = true;
        $settings['event_cancelled_at'] = now()->toIso8601String();
        $link->update(['settings' => $settings]);

        $this->syncToCalendar($link->fresh('icsData'), 'cancelled');

        return $link->fresh('icsData');
    }

    /** Reactivate a previously-cancelled event, clearing the settings state. */
    public function reactivate(Link $link): Link
    {
        $settings = (array) ($link->settings ?? []);
        unset($settings['event_cancelled'], $settings['event_cancelled_at']);
        $link->update(['settings' => $settings ?: null]);

        $this->syncToCalendar($link->fresh('icsData'), 'reactivated');

        return $link->fresh('icsData');
    }

    /**
     * Push or update the link in the bound calendar account when sync is on.
     * Mirrors IcsLinkController::syncToCalendar — a best-effort push that
     * never fails the cancel/reactivate action.
     */
    private function syncToCalendar(?Link $link, string $event): void
    {
        if (!$link) return;
        $s = (array) ($link->settings ?? []);
        $mode = $s['calendar_sync_mode'] ?? 'off';
        if ($mode === 'off') return;

        $accountId = $s['push_calendar_account_id'] ?? null;
        if (!$accountId) return;
        $account = CalendarAccount::where('id', $accountId)
            ->where('user_id', $link->user_id)->first();
        if (!$account) return;

        try {
            app(CalendarSyncService::class)->pushLink($account, $link);
        } catch (\Throwable $e) {
            Log::warning('Calendar push from EventCancellationService failed', [
                'link' => $link->id, 'event' => $event, 'err' => $e->getMessage(),
            ]);
        }
    }
}
