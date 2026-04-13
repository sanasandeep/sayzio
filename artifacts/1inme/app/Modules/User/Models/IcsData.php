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
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function toIcs(): string
    {
        $uid = uniqid('1inme-', true);
        $now = now()->format('Ymd\THis\Z');
        $start = $this->start_date->setTimezone($this->timezone)->format('Ymd\THis');
        $end = $this->end_date->setTimezone($this->timezone)->format('Ymd\THis');

        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//1INME//Link Manager//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:{$uid}\r\n";
        $ics .= "DTSTAMP:{$now}\r\n";
        $ics .= "DTSTART;TZID={$this->timezone}:{$start}\r\n";
        $ics .= "DTEND;TZID={$this->timezone}:{$end}\r\n";
        $ics .= "SUMMARY:" . $this->escapeIcs($this->event_name) . "\r\n";

        if ($this->description) {
            $ics .= "DESCRIPTION:" . $this->escapeIcs($this->description) . "\r\n";
        }
        if ($this->location) {
            $ics .= "LOCATION:" . $this->escapeIcs($this->location) . "\r\n";
        }
        if ($this->organizer_email) {
            $name = $this->organizer ?: '';
            $ics .= "ORGANIZER;CN={$name}:mailto:{$this->organizer_email}\r\n";
        }
        if ($this->url) {
            $ics .= "URL:{$this->url}\r\n";
        }

        $ics .= "END:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    protected function escapeIcs(string $text): string
    {
        return str_replace(["\n", ",", ";", "\\"], ["\\n", "\\,", "\\;", "\\\\"], $text);
    }
}
