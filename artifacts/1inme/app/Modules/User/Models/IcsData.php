<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class IcsData extends Model
{
    protected $table = 'ics_data';

    protected $fillable = [
        'link_id', 'event_name', 'description', 'location',
        'organizer', 'organizer_email',
        'start_date', 'end_date', 'timezone', 'url',
        'all_day',
        'recurrence_freq', 'recurrence_interval', 'recurrence_count',
        'recurrence_until', 'recurrence_byday',
        'extra_schedules',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'all_day' => 'boolean',
            'recurrence_until' => 'date',
            'extra_schedules' => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function toIcs(): string
    {
        $now = now()->format('Ymd\THis\Z');

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//1INME//Link Manager//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        // Primary event (with optional RRULE)
        $ics .= $this->renderVevent(
            $this->event_name,
            $this->description,
            $this->location,
            $this->start_date,
            $this->end_date,
            $this->buildRRule(),
            $now,
            uniqid('1inme-', true),
        );

        // Additional schedules — each becomes its own VEVENT.
        $extras = is_array($this->extra_schedules) ? $this->extra_schedules : [];
        foreach ($extras as $i => $ex) {
            if (empty($ex['start']) || empty($ex['end'])) continue;
            try {
                $s = new \DateTime($ex['start'], new \DateTimeZone($this->timezone));
                $e = new \DateTime($ex['end'], new \DateTimeZone($this->timezone));
            } catch (\Exception $err) { continue; }
            $ics .= $this->renderVevent(
                $ex['label'] ?? $this->event_name,
                $ex['description'] ?? null,
                $ex['location'] ?? $this->location,
                $s,
                $e,
                null,
                $now,
                uniqid('1inme-ex' . $i . '-', true),
            );
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    protected function renderVevent($summary, $description, $location, $start, $end, ?string $rrule, string $now, string $uid): string
    {
        $tz = $this->timezone ?: 'UTC';

        if ($this->all_day) {
            $startStr = $this->normalizeDate($start)->format('Ymd');
            $endStr   = $this->normalizeDate($end)->modify('+1 day')->format('Ymd'); // DTEND is exclusive
            $dt = "DTSTART;VALUE=DATE:{$startStr}\r\nDTEND;VALUE=DATE:{$endStr}\r\n";
        } else {
            $s = $this->normalizeDate($start)->setTimezone(new \DateTimeZone($tz))->format('Ymd\THis');
            $e = $this->normalizeDate($end)->setTimezone(new \DateTimeZone($tz))->format('Ymd\THis');
            $dt = "DTSTART;TZID={$tz}:{$s}\r\nDTEND;TZID={$tz}:{$e}\r\n";
        }

        $out  = "BEGIN:VEVENT\r\n";
        $out .= "UID:{$uid}\r\n";
        $out .= "DTSTAMP:{$now}\r\n";
        $out .= $dt;
        $out .= "SUMMARY:" . $this->escapeIcs((string) $summary) . "\r\n";

        if (!empty($description)) {
            $out .= "DESCRIPTION:" . $this->escapeIcs((string) $description) . "\r\n";
        }
        if (!empty($location)) {
            $out .= "LOCATION:" . $this->escapeIcs((string) $location) . "\r\n";
        }
        if ($this->organizer_email) {
            $name = $this->organizer ?: '';
            $out .= "ORGANIZER;CN={$name}:mailto:{$this->organizer_email}\r\n";
        }
        if ($this->url) {
            $out .= "URL:{$this->url}\r\n";
        }
        if ($rrule) {
            $out .= "RRULE:{$rrule}\r\n";
        }
        $out .= "END:VEVENT\r\n";
        return $out;
    }

    protected function normalizeDate($v): \DateTime
    {
        if ($v instanceof \DateTime) return clone $v;
        if ($v instanceof \DateTimeInterface) return new \DateTime($v->format(DATE_ATOM));
        return new \DateTime((string) $v);
    }

    protected function buildRRule(): ?string
    {
        $freq = strtoupper((string) $this->recurrence_freq);
        if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) return null;

        $parts = ["FREQ={$freq}"];
        $interval = max(1, (int) $this->recurrence_interval);
        if ($interval > 1) $parts[] = "INTERVAL={$interval}";

        if ($freq === 'WEEKLY' && !empty($this->recurrence_byday)) {
            // Sanitize: only valid day codes.
            $valid = ['MO','TU','WE','TH','FR','SA','SU'];
            $codes = array_filter(array_map('trim', explode(',', strtoupper($this->recurrence_byday))),
                fn ($c) => in_array($c, $valid, true));
            if (!empty($codes)) $parts[] = 'BYDAY=' . implode(',', $codes);
        }

        if ($this->recurrence_count) {
            $parts[] = 'COUNT=' . (int) $this->recurrence_count;
        } elseif ($this->recurrence_until) {
            $until = $this->recurrence_until instanceof \DateTimeInterface
                ? $this->recurrence_until
                : new \DateTime((string) $this->recurrence_until);
            $parts[] = 'UNTIL=' . $until->format('Ymd\T235959\Z');
        }

        return implode(';', $parts);
    }

    protected function escapeIcs(string $text): string
    {
        return str_replace(["\n", ",", ";", "\\"], ["\\n", "\\,", "\\;", "\\\\"], $text);
    }
}
