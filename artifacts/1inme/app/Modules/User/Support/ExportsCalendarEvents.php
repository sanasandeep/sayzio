<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared "My Calendar" export rendering used by both the web
 * {@see \App\Modules\User\Controllers\CalendarController::myCalendarExport} and
 * the mobile Sanctum {@see \App\Modules\Api\Controllers\MyCalendarController::export}.
 *
 * Keeps the ICS (RFC 5545) and CSV serialisation in one place so the two
 * surfaces never drift. Each event is expected to have its `calendar`
 * relation eager-loaded (for the calendar title + timezone fallback).
 *
 * @phpstan-param Collection<int,\App\Modules\User\Models\CalendarEvent> $events
 */
trait ExportsCalendarEvents
{
    /** Stream events as a downloadable ICS (RFC 5545) file. */
    protected function exportCalendarIcs(Collection $events, string $filename, string $userTz, string $calName): StreamedResponse
    {
        $body = $this->composeMyCalendarIcs($events, $userTz, $calName);

        return response()->stream(function () use ($body) {
            echo $body;
        }, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Build the aggregated "My Calendar" VCALENDAR (RFC 5545) string from a
     * collection of events spanning multiple owned/followed calendars. Shared
     * by the one-time export ({@see exportCalendarIcs}) and the live
     * subscription feed ({@see \App\Modules\User\Controllers\CalendarController::myCalendarFeed})
     * so both emit identical ICS. Accepts any iterable of events (including an
     * empty array for the empty-feed case).
     */
    protected function composeMyCalendarIcs($events, string $userTz, string $calName): string
    {
        $fold = function (string $line): string {
            // RFC 5545 §3.1 — no physical line may exceed 75 octets (excluding
            // the CRLF). Continuation lines start with a single space, which
            // itself counts toward the 75, so continuation payload is capped at
            // 74 octets. Splitting on raw bytes is permitted by §3.1 (readers
            // MUST unfold before decoding UTF-8).
            if (strlen($line) <= 75) {
                return $line;
            }
            $out  = substr($line, 0, 75);
            $rest = substr($line, 75);
            while ($rest !== '') {
                $out .= "\r\n " . substr($rest, 0, 74);
                $rest = substr($rest, 74);
            }
            return $out;
        };

        $esc = fn (string $v): string => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $v);

        // Collect the distinct IANA timezones referenced by timed events plus
        // the overall date range, so a matching VTIMEZONE can be emitted for
        // every TZID we reference (RFC 5545 §3.6.5 — a TZID reference without a
        // corresponding VTIMEZONE triggers a missing-timezone warning in strict
        // clients such as Google Calendar and Apple Calendar).
        $usedTz    = [];
        $rangeMin  = null;
        $rangeMax  = null;
        foreach ($events as $ev) {
            if ($ev->all_day || !$ev->start_at) {
                continue;
            }
            $etz          = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? $userTz);
            $usedTz[$etz] = true;
            $s            = $ev->start_at;
            $e            = $ev->end_at ?: $s;
            $rangeMin     = ($rangeMin === null || $s->lt($rangeMin)) ? $s->copy() : $rangeMin;
            $rangeMax     = ($rangeMax === null || $e->gt($rangeMax)) ? $e->copy() : $rangeMax;
        }
        $rangeMin ??= now();
        $rangeMax ??= now();

        $out  = "BEGIN:VCALENDAR\r\n";
        $out .= "VERSION:2.0\r\n";
        $out .= "PRODID:-//1inme//MyCalendar//EN\r\n";
        $out .= $fold("X-WR-CALNAME:{$esc($calName)}'s Calendar") . "\r\n";
        $out .= "X-WR-TIMEZONE:{$userTz}\r\n";
        $out .= "CALSCALE:GREGORIAN\r\n";
        $out .= "METHOD:PUBLISH\r\n";

        // Emit a VTIMEZONE for every referenced TZID before the events.
        foreach (array_keys($usedTz) as $tzid) {
            $out .= $this->buildVtimezone($tzid, $rangeMin, $rangeMax, $fold);
        }

        foreach ($events as $ev) {
            $etz   = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? $userTz);
            $start = $ev->start_at ? $ev->start_at->timezone($etz) : null;
            $end   = $ev->end_at   ? $ev->end_at->timezone($etz)   : ($start ? $start->copy()->addHour() : null);

            if (!$start) { continue; }

            $uid = 'ev-' . $ev->id . '@1inme';

            $out .= "BEGIN:VEVENT\r\n";
            $out .= $fold("UID:{$uid}") . "\r\n";

            if ($ev->all_day) {
                $out .= $fold("DTSTART;VALUE=DATE:{$start->format('Ymd')}") . "\r\n";
                $endDate = $end ? $end->copy()->addDay() : $start->copy()->addDay();
                $out .= $fold("DTEND;VALUE=DATE:{$endDate->format('Ymd')}") . "\r\n";
            } else {
                $out .= $fold("DTSTART;TZID={$etz}:{$start->format('Ymd\THis')}") . "\r\n";
                if ($end) {
                    $out .= $fold("DTEND;TZID={$etz}:{$end->format('Ymd\THis')}") . "\r\n";
                }
            }

            $out .= $fold("SUMMARY:{$esc($ev->title)}") . "\r\n";
            $out .= $fold("DTSTAMP:" . now()->utc()->format('Ymd\THis\Z')) . "\r\n";
            $out .= $fold("CREATED:" . ($ev->created_at ? $ev->created_at->utc()->format('Ymd\THis\Z') : now()->utc()->format('Ymd\THis\Z'))) . "\r\n";

            if ($ev->description) {
                $out .= $fold("DESCRIPTION:{$esc(strip_tags($ev->description))}") . "\r\n";
            }
            if ($ev->location) {
                $out .= $fold("LOCATION:{$esc($ev->location)}") . "\r\n";
            }
            if ($ev->lat !== null && $ev->lng !== null) {
                $out .= $fold("GEO:{$ev->lat};{$ev->lng}") . "\r\n";
            }
            if ($ev->payment_url) {
                $out .= $fold("URL:{$ev->payment_url}") . "\r\n";
            }
            if (!empty($ev->hashtags)) {
                $out .= $fold("CATEGORIES:" . implode(',', array_map($esc, $ev->hashtags))) . "\r\n";
            }
            if ($ev->calendar?->title) {
                $out .= $fold("X-CALENDAR:{$esc($ev->calendar->title)}") . "\r\n";
            }

            $out .= "END:VEVENT\r\n";
        }

        $out .= "END:VCALENDAR\r\n";

        return $out;
    }

    /**
     * Build a VTIMEZONE component for an IANA timezone id, covering the given
     * event range (padded a year on each side so the observance in force at the
     * range boundaries is always defined). Derived from PHP's DST transition
     * table, emitting an explicit STANDARD / DAYLIGHT observance per transition
     * (no RRULE) so the block is unambiguous for Google / Apple Calendar.
     *
     * Returns '' when the id is not a resolvable timezone (defensive — event
     * timezones are validated on write, but the export must never 500).
     */
    private function buildVtimezone(string $tzid, \Carbon\CarbonInterface $rangeStart, \Carbon\CarbonInterface $rangeEnd, callable $fold): string
    {
        try {
            $dtz = new \DateTimeZone($tzid);
        } catch (\Throwable) {
            return '';
        }

        $from = $rangeStart->copy()->subYear()->getTimestamp();
        $to   = $rangeEnd->copy()->addYear()->getTimestamp();

        $transitions = $dtz->getTransitions($from, $to);
        if (empty($transitions)) {
            return '';
        }

        $fmtOffset = static function (int $seconds): string {
            $sign    = $seconds < 0 ? '-' : '+';
            $seconds = abs($seconds);
            return sprintf('%s%02d%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        };

        $out  = "BEGIN:VTIMEZONE\r\n";
        $out .= $fold("TZID:{$tzid}") . "\r\n";

        $count = count($transitions);
        if ($count === 1) {
            // Fixed-offset zone (e.g. UTC): a single STANDARD observance.
            $t       = $transitions[0];
            $offset  = $fmtOffset((int) $t['offset']);
            $out    .= "BEGIN:STANDARD\r\n";
            $out    .= "DTSTART:19700101T000000\r\n";
            $out    .= "TZOFFSETFROM:{$offset}\r\n";
            $out    .= "TZOFFSETTO:{$offset}\r\n";
            if (!empty($t['abbr'])) {
                $out .= $fold("TZNAME:{$t['abbr']}") . "\r\n";
            }
            $out    .= "END:STANDARD\r\n";
        } else {
            // One observance per transition. DTSTART is the onset expressed in
            // local wall-clock time under the *previous* offset (TZOFFSETFROM).
            for ($i = 1; $i < $count; $i++) {
                $t          = $transitions[$i];
                $offsetFrom = (int) $transitions[$i - 1]['offset'];
                $offsetTo   = (int) $t['offset'];
                $onset      = gmdate('Ymd\THis', (int) $t['ts'] + $offsetFrom);
                $comp       = $t['isdst'] ? 'DAYLIGHT' : 'STANDARD';

                $out .= "BEGIN:{$comp}\r\n";
                $out .= "DTSTART:{$onset}\r\n";
                $out .= "TZOFFSETFROM:{$fmtOffset($offsetFrom)}\r\n";
                $out .= "TZOFFSETTO:{$fmtOffset($offsetTo)}\r\n";
                if (!empty($t['abbr'])) {
                    $out .= $fold("TZNAME:{$t['abbr']}") . "\r\n";
                }
                $out .= "END:{$comp}\r\n";
            }
        }

        $out .= "END:VTIMEZONE\r\n";

        return $out;
    }

    /** Stream events as a downloadable CSV file. */
    protected function exportCalendarCsv(Collection $events, string $filename, string $userTz): StreamedResponse
    {
        $safe = function ($value): string {
            $s = (string) $value;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
                return "'" . $s;
            }
            return $s;
        };

        return response()->stream(function () use ($events, $userTz, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Title', 'Calendar', 'Start', 'End', 'All Day', 'Location', 'Description', 'Tags', 'Ticket URL']);

            foreach ($events as $ev) {
                $etz   = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? $userTz);
                $start = $ev->start_at ? $ev->start_at->timezone($etz)->toDateTimeString() : '';
                $end   = $ev->end_at   ? $ev->end_at->timezone($etz)->toDateTimeString()   : '';

                fputcsv($out, [
                    $safe($ev->title),
                    $safe($ev->calendar?->title ?? ''),
                    $safe($start),
                    $safe($end),
                    $ev->all_day ? 'Yes' : 'No',
                    $safe($ev->location ?? ''),
                    $safe(strip_tags($ev->description ?? '')),
                    implode(', ', $ev->hashtags ?? []),
                    $safe($ev->payment_url ?? ''),
                ]);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
