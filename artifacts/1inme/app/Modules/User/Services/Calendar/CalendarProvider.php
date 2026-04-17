<?php

namespace App\Modules\User\Services\Calendar;

use App\Modules\User\Models\CalendarAccount;
use Carbon\CarbonInterface;

/**
 * Common contract for every calendar provider driver (Google, Microsoft, CalDAV, ...).
 *
 * Each method should be idempotent and tolerate the "external account is gone" case
 * by throwing CalendarSyncException with a helpful message.
 *
 * Event payload shape (associative array, used both for pull and push):
 *   external_event_id (string|null on push for new events)
 *   external_calendar_id (string|null)
 *   ical_uid (string|null)
 *   etag (string|null)
 *   summary (string)
 *   description (string|null)
 *   location (string|null)
 *   start (CarbonInterface)
 *   end   (CarbonInterface)
 *   timezone (string)
 *   all_day (bool)
 *   url (string|null)
 *   organizer (array|null with name/email)
 *   recurrence (array|null with freq/interval/count/until/byday)
 *   updated_at (CarbonInterface|null)
 */
interface CalendarProvider
{
    /** Provider key (google|microsoft|caldav). */
    public function key(): string;

    /** Build the OAuth authorization URL for a fresh connect flow. */
    public function authorizationUrl(string $stateToken, string $redirectUri): string;

    /** Exchange the OAuth code for tokens and persist a new CalendarAccount. */
    public function exchangeCode(int $userId, string $code, string $redirectUri): CalendarAccount;

    /** Refresh the OAuth token in place. */
    public function refreshIfNeeded(CalendarAccount $account): void;

    /**
     * Fetch upcoming events from the connected calendar.
     *
     * @return iterable<array> Yields normalised event payloads.
     */
    public function listEvents(CalendarAccount $account, CarbonInterface $from, CarbonInterface $to): iterable;

    /** Create a new event in the external calendar. Returns the saved external_event_id + etag. */
    public function createEvent(CalendarAccount $account, array $event): array;

    /** Update an existing event by external id. */
    public function updateEvent(CalendarAccount $account, string $externalEventId, array $event): array;

    /** Delete an event from the external calendar. */
    public function deleteEvent(CalendarAccount $account, string $externalEventId): void;
}

class CalendarSyncException extends \RuntimeException {}
