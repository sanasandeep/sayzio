<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;

/**
 * Builds a multi-event VCALENDAR feed for a {@see Calendar}. Mirrors the
 * VEVENT / escaping conventions used by IcsData::toIcs() (the single-event
 * `ics` link type) but loops over a calendar's whole event collection so it
 * can be downloaded once or subscribed to from Google / Apple / Outlook as a
 * live feed.
 */
class CalendarIcs
{
    /**
     * Build a VCALENDAR feed for a calendar. Pass $from / $to to export only a
     * date range (partial export); both are inclusive on the event start. When
     * both are null the full calendar is exported.
     */
    public static function build(Calendar $calendar, ?\Carbon\CarbonInterface $from = null, ?\Carbon\CarbonInterface $to = null): string
    {
        $now = now()->format('Ymd\THis\Z');

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Sayzio//Calendar//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= 'X-WR-CALNAME:' . self::escape((string) $calendar->title) . "\r\n";
        $ics .= 'X-WR-TIMEZONE:' . ($calendar->effectiveTimezone()) . "\r\n";
        if (filled($calendar->description)) {
            $ics .= 'X-WR-CALDESC:' . self::escape((string) $calendar->description) . "\r\n";
        }

        $events = $calendar->events;
        if ($from || $to) {
            $events = $events->filter(function (CalendarEvent $event) use ($from, $to) {
                $start = $event->start_at;
                if (!$start) {
                    return false;
                }
                if ($from && $start->lt($from)) {
                    return false;
                }
                if ($to && $start->gt($to)) {
                    return false;
                }

                return true;
            });
        }

        foreach ($events as $event) {
            $ics .= self::renderVevent($event, $now);
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    protected static function renderVevent(CalendarEvent $event, string $now): string
    {
        $tz    = $event->effectiveTimezone();
        $start = $event->start_at;
        $end   = $event->end_at ?: ($start ? (clone $start)->addHour() : null);
        if (!$start) {
            return '';
        }

        $uid = sprintf('calendar-event-%d@sayzio', $event->id);

        if ($event->all_day) {
            $startStr = $start->copy()->format('Ymd');
            // DTEND is exclusive for all-day events.
            $endStr = ($end ?: $start)->copy()->addDay()->format('Ymd');
            $dt = "DTSTART;VALUE=DATE:{$startStr}\r\nDTEND;VALUE=DATE:{$endStr}\r\n";
        } else {
            $s = $start->copy()->setTimezone($tz)->format('Ymd\THis');
            $e = ($end ?: $start)->copy()->setTimezone($tz)->format('Ymd\THis');
            $dt = "DTSTART;TZID={$tz}:{$s}\r\nDTEND;TZID={$tz}:{$e}\r\n";
        }

        $out  = "BEGIN:VEVENT\r\n";
        $out .= "UID:{$uid}\r\n";
        $out .= "DTSTAMP:{$now}\r\n";
        $out .= $dt;
        $out .= 'SUMMARY:' . self::escape((string) $event->title) . "\r\n";

        $description = trim((string) $event->description);
        $hashtags = is_array($event->hashtags) ? $event->hashtags : [];
        if ($hashtags) {
            $tagLine = implode(' ', array_map(fn ($t) => '#' . $t, $hashtags));
            $description = trim($description . "\n" . $tagLine);
        }
        if (filled($event->payment_url)) {
            $description = trim($description . "\nTickets / payment: " . $event->payment_url);
        }
        if ($description !== '') {
            $out .= 'DESCRIPTION:' . self::escape($description) . "\r\n";
        }
        if (filled($event->location)) {
            $out .= 'LOCATION:' . self::escape((string) $event->location) . "\r\n";
        }
        if ($event->lat !== null && $event->lng !== null) {
            $out .= 'GEO:' . (float) $event->lat . ';' . (float) $event->lng . "\r\n";
        }
        if (filled($event->payment_url)) {
            $out .= 'URL:' . $event->payment_url . "\r\n";
        }
        $out .= "END:VEVENT\r\n";

        return $out;
    }

    protected static function escape(string $text): string
    {
        return str_replace(["\n", ",", ";", "\\"], ["\\n", "\\,", "\\;", "\\\\"], $text);
    }
}
