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
        'slots',
        'monthly_mode', 'monthly_weekday_ordinal', 'yearly_month',
        'latitude', 'longitude',
        // Task #3593 (Events overhaul): hashtags, richer page content and
        // badge-powered invite/entry rules.
        'hashtags', 'gallery', 'info_sections', 'cover_image_url',
        'required_badge_id', 'award_badge_id',
        'agenda', 'documents',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'all_day' => 'boolean',
            'recurrence_until' => 'date',
            'extra_schedules' => 'array',
            'slots' => 'array',
            'yearly_month' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'hashtags' => 'array',
            'gallery' => 'array',
            'info_sections' => 'array',
            'agenda' => 'array',
            'documents' => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function requiredBadge()
    {
        return $this->belongsTo(\App\Modules\Admin\Models\AccountBadge::class, 'required_badge_id');
    }

    public function awardBadge()
    {
        return $this->belongsTo(\App\Modules\Admin\Models\AccountBadge::class, 'award_badge_id');
    }

    /** Normalized hashtag list (lowercase, no leading #, deduped, capped). */
    public function hashtagList(): array
    {
        $tags = collect((array) $this->hashtags)
            ->map(fn ($t) => mb_strtolower(ltrim(trim((string) $t), '#')))
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->values();
        return $tags->take(15)->all();
    }

    /**
     * Resolve the per-occurrence time slots. New rows store an explicit
     * `slots` json array — `[{start, end, label?, location?}, …]`. Legacy
     * rows fall back to the single (start_date, end_date) pair plus any
     * historical extra_schedules so old events keep rendering correctly
     * during the rollout window.
     */
    public function resolvedSlots(): array
    {
        $slots = is_array($this->slots) ? array_values(array_filter(
            $this->slots,
            fn ($s) => is_array($s) && !empty($s['start']) && !empty($s['end'])
        )) : [];

        if (!empty($slots)) return $slots;

        $primary = [
            'start'    => $this->start_date ? $this->start_date->toIso8601String() : null,
            'end'      => $this->end_date   ? $this->end_date->toIso8601String()   : null,
            'label'    => null,
            'location' => null,
        ];
        if ($primary['start'] && $primary['end']) {
            $slots[] = $primary;
        }
        foreach ((array) $this->extra_schedules as $ex) {
            if (empty($ex['start']) || empty($ex['end'])) continue;
            $slots[] = [
                'start'    => $ex['start'],
                'end'      => $ex['end'],
                'label'    => $ex['label'] ?? null,
                'location' => $ex['location'] ?? null,
            ];
        }
        return $slots;
    }

    /**
     * Enumerate the next N upcoming occurrences as DateTime pairs taking
     * the recurrence rule + every slot into account. Used by the public
     * RSVP form so guests can pick which date(s) they're coming to.
     */
    public function upcomingOccurrences(int $limit = 12, ?\DateTimeInterface $from = null): array
    {
        $tz   = new \DateTimeZone(\App\Support\PlatformTimezone::resolve($this->timezone));
        $from = $from ? \DateTime::createFromInterface($from) : new \DateTime('now', $tz);
        $slots = $this->resolvedSlots();
        if (empty($slots)) return [];

        $base = $this->start_date ? \DateTime::createFromInterface($this->start_date) : new \DateTime($slots[0]['start'], $tz);
        $occurrences = $this->expandRecurrenceStarts($base, $tz, $limit, $from);

        $out = [];
        $baseSlots = [];
        foreach ($slots as $i => $s) {
            try {
                $start = new \DateTime($s['start'], $tz);
                $end   = new \DateTime($s['end'], $tz);
                $baseSlots[] = ['idx' => $i, 'start' => $start, 'end' => $end, 'label' => $s['label'] ?? null];
            } catch (\Throwable $e) { continue; }
        }

        $deltaToBase = function (\DateTime $d) use ($base) {
            return $d->getTimestamp() - $base->getTimestamp();
        };

        foreach ($occurrences as $occStart) {
            foreach ($baseSlots as $bs) {
                $shift = (clone $occStart)->modify('+' . $deltaToBase($bs['start']) . ' seconds');
                $endShift = (clone $occStart)->modify('+' . $deltaToBase($bs['end']) . ' seconds');
                if ($shift < $from) continue;
                $out[] = [
                    'key'   => $occStart->format('Y-m-d') . '#' . $bs['idx'],
                    'start' => $shift,
                    'end'   => $endShift,
                    'label' => $bs['label'],
                ];
                if (count($out) >= $limit) return $out;
            }
        }
        return $out;
    }

    protected function expandRecurrenceStarts(\DateTime $base, \DateTimeZone $tz, int $limit, \DateTime $from): array
    {
        $freq = strtoupper((string) $this->recurrence_freq);
        if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return [clone $base];
        }
        $interval = max(1, (int) $this->recurrence_interval);
        $cap = $this->recurrence_count ? min((int) $this->recurrence_count, $limit * 4) : $limit * 4;
        $until = $this->recurrence_until ? \DateTime::createFromInterface($this->recurrence_until) : null;

        $byday = [];
        if ($freq === 'WEEKLY' && !empty($this->recurrence_byday)) {
            $valid = ['MO','TU','WE','TH','FR','SA','SU'];
            $byday = array_filter(array_map('trim', explode(',', strtoupper($this->recurrence_byday))),
                fn ($c) => in_array($c, $valid, true));
        }

        $out  = [];
        $cur  = clone $base;
        $iter = 0;
        $weekdayMap = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

        while (count($out) < $limit && $iter < 4000) {
            $iter++;
            if ($until && $cur > $until) break;

            if ($freq === 'WEEKLY' && !empty($byday)) {
                $weekStart = (clone $cur)->modify('monday this week');
                foreach ($byday as $code) {
                    $occ = (clone $weekStart)->modify('+' . ($weekdayMap[$code] - 1) . ' days');
                    $occ->setTime((int)$cur->format('H'), (int)$cur->format('i'), (int)$cur->format('s'));
                    if ($occ < $base) continue;
                    if ($until && $occ > $until) break 2;
                    if ($occ >= $from) $out[] = clone $occ;
                    if (count($out) >= $limit) break;
                    if (count($out) >= $cap) break 2;
                }
                $cur->modify('+' . $interval . ' weeks');
                continue;
            }

            if ($cur >= $from) $out[] = clone $cur;
            if (count($out) >= $cap) break;

            switch ($freq) {
                case 'DAILY':   $cur->modify('+' . $interval . ' days');   break;
                case 'WEEKLY':  $cur->modify('+' . $interval . ' weeks');  break;
                case 'MONTHLY': $cur->modify('+' . $interval . ' months'); break;
                case 'YEARLY':  $cur->modify('+' . $interval . ' years');  break;
            }
        }
        return $out;
    }

    public function toIcs(): string
    {
        $now = now()->format('Ymd\THis\Z');

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Sayzio//Link Manager//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        $rrule = $this->buildRRule();
        $tz    = new \DateTimeZone(\App\Support\PlatformTimezone::resolve($this->timezone));
        $slots = $this->resolvedSlots();

        if (empty($slots)) {
            // Defensive: some legacy rows have no times at all.
            $ics .= $this->renderVevent(
                $this->event_name,
                $this->description,
                $this->location,
                $this->start_date,
                $this->end_date,
                $rrule,
                $now,
                uniqid('1inme-', true),
            );
        } else {
            foreach ($slots as $i => $slot) {
                try {
                    $s = new \DateTime($slot['start'], $tz);
                    $e = new \DateTime($slot['end'],   $tz);
                } catch (\Exception $err) { continue; }

                // Cross-midnight: end < start ⇒ roll the end forward by a day.
                if ($e <= $s) {
                    $e->modify('+1 day');
                }

                $ics .= $this->renderVevent(
                    !empty($slot['label']) ? $slot['label'] : $this->event_name,
                    $this->description,
                    !empty($slot['location']) ? $slot['location'] : $this->location,
                    $s,
                    $e,
                    $i === 0 ? $rrule : null,
                    $now,
                    uniqid('1inme-s' . $i . '-', true),
                );
            }
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    protected function renderVevent($summary, $description, $location, $start, $end, ?string $rrule, string $now, string $uid): string
    {
        $tz = \App\Support\PlatformTimezone::resolve($this->timezone);

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

        $valid = ['MO','TU','WE','TH','FR','SA','SU'];
        $byday = [];
        if (!empty($this->recurrence_byday)) {
            $byday = array_values(array_filter(
                array_map('trim', explode(',', strtoupper($this->recurrence_byday))),
                fn ($c) => in_array($c, $valid, true)
            ));
        }

        if ($freq === 'WEEKLY' && !empty($byday)) {
            $parts[] = 'BYDAY=' . implode(',', $byday);
        }

        if ($freq === 'MONTHLY') {
            $mode = $this->monthly_mode ?: 'day_of_month';
            if ($mode === 'weekday_ordinal') {
                $ord = (string) ($this->monthly_weekday_ordinal ?: '1');
                // Use first byday code if present, otherwise derive from start_date.
                $code = $byday[0] ?? null;
                if (!$code && $this->start_date) {
                    $weekdayCodes = ['Mon' => 'MO', 'Tue' => 'TU', 'Wed' => 'WE', 'Thu' => 'TH', 'Fri' => 'FR', 'Sat' => 'SA', 'Sun' => 'SU'];
                    $code = $weekdayCodes[$this->start_date->format('D')] ?? 'MO';
                }
                if ($code) $parts[] = 'BYDAY=' . $ord . $code;
            } elseif ($this->start_date) {
                $parts[] = 'BYMONTHDAY=' . (int) $this->start_date->format('j');
            }
        }

        if ($freq === 'YEARLY' && $this->start_date) {
            $month = $this->yearly_month ?: (int) $this->start_date->format('n');
            $parts[] = 'BYMONTH=' . (int) $month;
            $parts[] = 'BYMONTHDAY=' . (int) $this->start_date->format('j');
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
