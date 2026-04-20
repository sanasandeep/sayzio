<?php

namespace App\Modules\User\Services\Contacts;

class ContactImportParser
{
    /**
     * Parse a CSV or vCard file and return a list of normalized rows ready
     * to feed into the same create-contact pipeline used by manual entry.
     *
     * Each row has the shape:
     *   [
     *     'display_name' => string|null,
     *     'given_name'   => string|null,
     *     'family_name'  => string|null,
     *     'organization' => string|null,
     *     'phones' => [['label' => ?string, 'value' => string], ...],
     *     'emails' => [['label' => ?string, 'value' => string], ...],
     *     'source_line' => int,    // 1-indexed pointer back into the file
     *   ]
     */
    public function parse(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === 'vcf' || $ext === 'vcard') {
            return $this->parseVcard(file_get_contents($path));
        }
        return $this->parseCsv($path);
    }

    // ---- CSV --------------------------------------------------------------

    public function parseCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (!$handle) return $rows;

        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (!$header) { fclose($handle); return $rows; }
        $map = $this->buildHeaderMap($header);

        $line = 1; // header was line 1
        while (($cols = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            // Skip totally blank rows.
            if (count(array_filter($cols, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $get = function (string $key) use ($map, $cols) {
                $idx = $map[$key] ?? null;
                if ($idx === null || !array_key_exists($idx, $cols)) return null;
                $v = trim((string) $cols[$idx]);
                return $v === '' ? null : $v;
            };

            $row = [
                'display_name' => $get('name'),
                'given_name'   => $get('given_name'),
                'family_name'  => $get('family_name'),
                'organization' => $get('organization'),
                'phones'       => [],
                'emails'       => [],
                'source_line'  => $line,
            ];
            if ($p = $get('phone')) {
                $row['phones'][] = ['label' => 'Mobile', 'value' => $p];
            }
            if ($e = $get('email')) {
                $row['emails'][] = ['label' => 'Personal', 'value' => $e];
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Map common header variants to canonical keys: name, given_name,
     * family_name, organization, phone, email.
     */
    protected function buildHeaderMap(array $header): array
    {
        $aliases = [
            'name'         => ['name', 'full name', 'display name', 'displayname', 'contact name'],
            'given_name'   => ['first name', 'first', 'given name', 'givenname'],
            'family_name'  => ['last name', 'last', 'surname', 'family name', 'familyname'],
            'organization' => ['organization', 'organisation', 'company', 'org', 'employer'],
            'phone'        => ['phone', 'phone number', 'mobile', 'cell', 'telephone', 'tel'],
            'email'        => ['email', 'e-mail', 'email address', 'mail'],
        ];
        $map = [];
        foreach ($header as $i => $h) {
            $norm = strtolower(trim((string) $h));
            foreach ($aliases as $canon => $opts) {
                if (in_array($norm, $opts, true) && !isset($map[$canon])) {
                    $map[$canon] = $i;
                }
            }
        }
        return $map;
    }

    // ---- vCard ------------------------------------------------------------

    public function parseVcard(string $content): array
    {
        // Normalise line endings, then unfold (RFC 6350 §3.2):
        // a line beginning with a space/tab continues the prior line.
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/\n[ \t]/', '', $content);

        $rows = [];
        $cards = preg_split('/BEGIN:VCARD/i', $content);
        $cursor = 0; // running line counter to give each card a source_line
        foreach ($cards as $idx => $card) {
            // Track approximate source line for nicer errors.
            $cursor += substr_count($card, "\n") + ($idx === 0 ? 0 : 1);
            if ($idx === 0) continue; // text before first BEGIN:VCARD
            $card = preg_replace('/END:VCARD.*$/is', '', $card);

            $row = [
                'display_name' => null,
                'given_name'   => null,
                'family_name'  => null,
                'organization' => null,
                'phones'       => [],
                'emails'       => [],
                'source_line'  => $cursor,
            ];

            foreach (preg_split('/\n+/', trim($card)) as $rawLine) {
                $rawLine = trim($rawLine);
                if ($rawLine === '') continue;
                if (!str_contains($rawLine, ':')) continue;
                [$head, $value] = explode(':', $rawLine, 2);
                $parts = explode(';', $head);
                $prop  = strtoupper(array_shift($parts));
                // Skip params we don't need but keep TYPE for label hints.
                $types = [];
                foreach ($parts as $p) {
                    if (stripos($p, 'TYPE=') === 0) {
                        foreach (explode(',', substr($p, 5)) as $t) {
                            $types[] = strtolower(trim($t, '"'));
                        }
                    }
                }
                $value = $this->vcardUnescape($value);

                switch ($prop) {
                    case 'FN':
                        $row['display_name'] = $value;
                        break;
                    case 'N':
                        // family;given;additional;prefix;suffix
                        $bits = explode(';', $value);
                        $row['family_name'] = trim($bits[0] ?? '') ?: null;
                        $row['given_name']  = trim($bits[1] ?? '') ?: null;
                        break;
                    case 'ORG':
                        $row['organization'] = trim(explode(';', $value)[0]) ?: null;
                        break;
                    case 'TEL':
                        if (trim($value) !== '') {
                            $row['phones'][] = ['label' => $this->labelFromTypes($types, 'phone'), 'value' => $value];
                        }
                        break;
                    case 'EMAIL':
                        if (trim($value) !== '') {
                            $row['emails'][] = ['label' => $this->labelFromTypes($types, 'email'), 'value' => $value];
                        }
                        break;
                }
            }

            // A card with no usable data is skipped silently.
            if (!$row['display_name'] && !$row['given_name'] && !$row['family_name']
                && !$row['phones'] && !$row['emails']) continue;
            $rows[] = $row;
        }

        return $rows;
    }

    protected function vcardUnescape(string $v): string
    {
        return str_replace(['\\n', '\\,', '\\;', '\\\\'], ["\n", ',', ';', '\\'], $v);
    }

    protected function labelFromTypes(array $types, string $kind): string
    {
        $map = $kind === 'phone'
            ? ['cell' => 'Mobile', 'mobile' => 'Mobile', 'work' => 'Work', 'home' => 'Home', 'main' => 'Main']
            : ['work' => 'Work', 'home' => 'Personal', 'personal' => 'Personal'];
        foreach ($types as $t) {
            if (isset($map[$t])) return $map[$t];
        }
        return $kind === 'phone' ? 'Other' : 'Other';
    }
}
