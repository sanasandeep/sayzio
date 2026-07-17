<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use Illuminate\Support\Collection;

/**
 * Builds bulk CSV and multi-entry vCard exports from a contacts collection.
 *
 * CSV column layout is kept in lockstep with ContactImportParser so that
 * re-importing an export produces no data loss and no phantom changes.
 * Numbers of phone/email columns in the export header must match the N limit
 * recognised by the parser.
 */
class ContactExportBuilder
{
    /** Max per-contact phone and email slots emitted in the CSV. */
    public const CSV_PHONE_SLOTS = 5;
    public const CSV_EMAIL_SLOTS = 5;

    /**
     * Build a CSV string for the given contact collection.
     * Columns: name, given_name, family_name, organization, job_title, notes,
     * tags, phone 1 .. N (+ phone N label), email 1 .. N (+ email N label).
     */
    public function buildCsv(Collection $contacts): string
    {
        $out = fopen('php://temp', 'r+');

        // Header row.
        $header = [
            'name', 'given_name', 'family_name', 'organization',
            'job_title', 'notes', 'tags',
        ];
        for ($i = 1; $i <= self::CSV_PHONE_SLOTS; $i++) {
            $header[] = "phone {$i}";
            $header[] = "phone {$i} label";
        }
        for ($i = 1; $i <= self::CSV_EMAIL_SLOTS; $i++) {
            $header[] = "email {$i}";
            $header[] = "email {$i} label";
        }
        fputcsv($out, $header);

        foreach ($contacts as $contact) {
            $phones = $contact->phones ?? collect();
            $emails = $contact->emails ?? collect();
            $tags   = $contact->tags ?? [];

            $row = [
                $contact->display_name ?? '',
                $contact->given_name   ?? '',
                $contact->family_name  ?? '',
                $contact->organization ?? '',
                $contact->job_title    ?? '',
                $contact->notes        ?? '',
                implode(', ', (array) $tags),
            ];

            for ($i = 0; $i < self::CSV_PHONE_SLOTS; $i++) {
                $p = $phones->get($i);
                $row[] = $p ? ($p->value_e164 ?: $p->value) : '';
                $row[] = $p ? ($p->label ?? '') : '';
            }
            for ($i = 0; $i < self::CSV_EMAIL_SLOTS; $i++) {
                $e = $emails->get($i);
                $row[] = $e ? $e->value : '';
                $row[] = $e ? ($e->label ?? '') : '';
            }

            fputcsv($out, $row);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return (string) $csv;
    }

    /**
     * Build a standards-valid multi-entry vCard 3.0 string.
     * Each contact becomes its own BEGIN:VCARD ... END:VCARD block.
     * Tags are exported as CATEGORIES.
     */
    public function buildVcf(Collection $contacts): string
    {
        $parts = [];
        foreach ($contacts as $contact) {
            $parts[] = $this->contactToVcf($contact);
        }
        return implode("\r\n", $parts);
    }

    // ---- private helpers --------------------------------------------------

    private function contactToVcf(Contact $contact): string
    {
        $lines = [];
        $lines[] = 'BEGIN:VCARD';
        $lines[] = 'VERSION:3.0';

        // Name.
        $fn = $contact->display_name ?? trim(($contact->given_name ?? '') . ' ' . ($contact->family_name ?? ''));
        if ($fn === '') {
            // Construct from phones/emails as last resort; keep FN required.
            $p = $contact->phones?->first();
            $fn = $p ? ($p->value_e164 ?: $p->value) : 'Unknown';
        }
        $lines[] = 'FN:' . $this->esc($fn);
        $lines[] = 'N:' . $this->esc($contact->family_name ?? '') . ';'
                       . $this->esc($contact->given_name   ?? '') . ';;;';

        if ($contact->organization) {
            $lines[] = 'ORG:' . $this->esc($contact->organization);
        }
        if ($contact->job_title) {
            $lines[] = 'TITLE:' . $this->esc($contact->job_title);
        }

        // Phone numbers.
        foreach (($contact->phones ?? collect()) as $phone) {
            $val = $phone->value_e164 ?: $phone->value;
            if (!$val) continue;
            $type = $this->vcfPhoneType($phone->label ?? '');
            $lines[] = "TEL;TYPE={$type}:" . $this->esc($val);
        }

        // Emails.
        foreach (($contact->emails ?? collect()) as $email) {
            if (!$email->value) continue;
            $type = $this->vcfEmailType($email->label ?? '');
            $lines[] = "EMAIL;TYPE={$type}:" . $this->esc($email->value);
        }

        // Notes.
        if ($contact->notes) {
            $lines[] = 'NOTE:' . $this->esc(str_replace(["\r\n", "\n", "\r"], '\\n', (string) $contact->notes));
        }

        // Tags → CATEGORIES.
        $tags = (array) ($contact->tags ?? []);
        if (!empty($tags)) {
            $lines[] = 'CATEGORIES:' . implode(',', array_map([$this, 'esc'], $tags));
        }

        $lines[] = 'END:VCARD';
        return implode("\r\n", $lines);
    }

    private function esc(string $v): string
    {
        return str_replace(['\\', ',', ';', "\n"], ['\\\\', '\\,', '\\;', '\\n'], $v);
    }

    private function vcfPhoneType(string $label): string
    {
        return match (strtolower(trim($label))) {
            'mobile', 'cell' => 'CELL',
            'work'           => 'WORK',
            'home'           => 'HOME',
            'main'           => 'MAIN',
            default          => 'VOICE',
        };
    }

    private function vcfEmailType(string $label): string
    {
        return match (strtolower(trim($label))) {
            'work'           => 'WORK',
            'personal', ''   => 'INTERNET',
            default          => 'INTERNET',
        };
    }
}
