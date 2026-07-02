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
        $fold = function (string $line): string {
            // RFC 5545 §3.1 — fold lines longer than 75 octets.
            $out   = '';
            $bytes = strlen($line);
            $pos   = 0;
            while ($pos < $bytes) {
                $chunk = substr($line, $pos, 75);
                $out  .= ($pos === 0 ? '' : "\r\n ") . $chunk;
                $pos  += 75;
            }
            return $out;
        };

        $esc = fn (string $v): string => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $v);

        return response()->stream(function () use ($events, $userTz, $calName, $fold, $esc) {
            echo "BEGIN:VCALENDAR\r\n";
            echo "VERSION:2.0\r\n";
            echo "PRODID:-//1inme//MyCalendar//EN\r\n";
            echo $fold("X-WR-CALNAME:{$esc($calName)}'s Calendar") . "\r\n";
            echo "X-WR-TIMEZONE:{$userTz}\r\n";
            echo "CALSCALE:GREGORIAN\r\n";
            echo "METHOD:PUBLISH\r\n";

            foreach ($events as $ev) {
                $etz   = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? $userTz);
                $start = $ev->start_at ? $ev->start_at->timezone($etz) : null;
                $end   = $ev->end_at   ? $ev->end_at->timezone($etz)   : ($start ? $start->copy()->addHour() : null);

                if (!$start) { continue; }

                $uid = 'ev-' . $ev->id . '@1inme';

                echo "BEGIN:VEVENT\r\n";
                echo $fold("UID:{$uid}") . "\r\n";

                if ($ev->all_day) {
                    echo $fold("DTSTART;VALUE=DATE:{$start->format('Ymd')}") . "\r\n";
                    $endDate = $end ? $end->copy()->addDay() : $start->copy()->addDay();
                    echo $fold("DTEND;VALUE=DATE:{$endDate->format('Ymd')}") . "\r\n";
                } else {
                    echo $fold("DTSTART;TZID={$etz}:{$start->format('Ymd\THis')}") . "\r\n";
                    if ($end) {
                        echo $fold("DTEND;TZID={$etz}:{$end->format('Ymd\THis')}") . "\r\n";
                    }
                }

                echo $fold("SUMMARY:{$esc($ev->title)}") . "\r\n";
                echo $fold("DTSTAMP:" . now()->utc()->format('Ymd\THis\Z')) . "\r\n";
                echo $fold("CREATED:" . ($ev->created_at ? $ev->created_at->utc()->format('Ymd\THis\Z') : now()->utc()->format('Ymd\THis\Z'))) . "\r\n";

                if ($ev->description) {
                    echo $fold("DESCRIPTION:{$esc(strip_tags($ev->description))}") . "\r\n";
                }
                if ($ev->location) {
                    echo $fold("LOCATION:{$esc($ev->location)}") . "\r\n";
                }
                if ($ev->lat !== null && $ev->lng !== null) {
                    echo $fold("GEO:{$ev->lat};{$ev->lng}") . "\r\n";
                }
                if ($ev->payment_url) {
                    echo $fold("URL:{$ev->payment_url}") . "\r\n";
                }
                if (!empty($ev->hashtags)) {
                    echo $fold("CATEGORIES:" . implode(',', array_map($esc, $ev->hashtags))) . "\r\n";
                }
                if ($ev->calendar?->title) {
                    echo $fold("X-CALENDAR:{$esc($ev->calendar->title)}") . "\r\n";
                }

                echo "END:VEVENT\r\n";
            }

            echo "END:VCALENDAR\r\n";
        }, 200, [
            'Content-Type'        => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
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
