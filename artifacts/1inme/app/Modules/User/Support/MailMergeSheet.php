<?php

namespace App\Modules\User\Support;

use Illuminate\Http\UploadedFile;

/**
 * Self-contained spreadsheet reader for the biolink mail-merge flow.
 *
 * Parses CSV, pasted text (tab- or comma-separated), and .xlsx workbooks into
 * a normalized structure:
 *
 *   ['headers' => string[], 'rows' => array<array<string,string>>]
 *
 * The first non-empty line/row is treated as the header. Header names are
 * lowercased + trimmed; each data row is an associative array keyed by those
 * normalized headers.
 *
 * The XLSX reader is intentionally dependency-free (ZipArchive + SimpleXML,
 * both bundled with PHP) so bulk mail-merge needs no composer additions.
 */
class MailMergeSheet
{
    /** Parse an uploaded file by extension (.xlsx → workbook, else CSV). */
    public static function fromUpload(UploadedFile $file): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'xlsx') {
            return self::fromXlsx($file->getRealPath());
        }
        return self::fromCsv($file->getRealPath());
    }

    /** Parse a CSV file on disk. */
    public static function fromCsv(string $path): array
    {
        $grid = [];
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return ['headers' => [], 'rows' => []];
        }
        while (($r = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $grid[] = array_map(fn ($v) => (string) $v, $r);
        }
        fclose($handle);
        return self::gridToRows($grid);
    }

    /** Parse pasted text. Delimiter is auto-detected: a tab wins, else comma. */
    public static function fromPaste(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        $delim = ',';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $delim = str_contains($line, "\t") ? "\t" : ',';
                break;
            }
        }

        $grid = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $grid[] = str_getcsv($line, $delim, '"', '\\');
        }
        return self::gridToRows($grid);
    }

    /** Parse an .xlsx workbook on disk (first worksheet). */
    public static function fromXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return ['headers' => [], 'rows' => []];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return ['headers' => [], 'rows' => []];
        }

        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false && $ssXml !== '') {
            $shared = self::parseSharedStrings($ssXml);
        }

        $sheetName = self::firstSheetEntry($zip);
        $sheetXml = $sheetName ? $zip->getFromName($sheetName) : false;
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            return ['headers' => [], 'rows' => []];
        }

        return self::gridToRows(self::parseWorksheet($sheetXml, $shared));
    }

    /**
     * Collapse a raw 2-D grid into headers + assoc rows. Leading fully-empty
     * rows are skipped; the first row with any content becomes the header.
     */
    private static function gridToRows(array $grid): array
    {
        $headerRow = null;
        $dataRows = [];

        foreach ($grid as $row) {
            $nonEmpty = array_filter(array_map(fn ($v) => trim((string) $v), $row), fn ($v) => $v !== '');
            if ($headerRow === null) {
                if (empty($nonEmpty)) {
                    continue;
                }
                $headerRow = $row;
                continue;
            }
            if (empty($nonEmpty)) {
                continue;
            }
            $dataRows[] = $row;
        }

        if ($headerRow === null) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = [];
        foreach ($headerRow as $h) {
            $headers[] = strtolower(trim((string) $h));
        }

        $rows = [];
        foreach ($dataRows as $r) {
            $assoc = [];
            foreach ($headers as $i => $h) {
                if ($h === '') {
                    continue;
                }
                $assoc[$h] = trim((string) ($r[$i] ?? ''));
            }
            $rows[] = $assoc;
        }

        $headers = array_values(array_filter($headers, fn ($h) => $h !== ''));
        return ['headers' => $headers, 'rows' => $rows];
    }

    /** Pick the lowest-numbered worksheet XML entry in the workbook. */
    private static function firstSheetEntry(\ZipArchive $zip): ?string
    {
        $candidates = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^xl/worksheets/sheet(\d+)\.xml$#', $name, $m)) {
                $candidates[(int) $m[1]] = $name;
            }
        }
        if (!empty($candidates)) {
            ksort($candidates);
            return reset($candidates);
        }

        // Fallback: any worksheet XML, whatever its name.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false && preg_match('#^xl/worksheets/.+\.xml$#', $name)) {
                return $name;
            }
        }
        return null;
    }

    /** Build the shared-strings lookup table. */
    private static function parseSharedStrings(string $xml): array
    {
        $out = [];
        $sx = @simplexml_load_string($xml);
        if ($sx === false) {
            return $out;
        }
        foreach ($sx->si as $si) {
            $out[] = self::richText($si);
        }
        return $out;
    }

    /** Flatten a shared-string / inline-string node (plain <t> or rich runs). */
    private static function richText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    /** Turn a worksheet XML body into a dense 2-D grid of cell strings. */
    private static function parseWorksheet(string $xml, array $shared): array
    {
        $sx = @simplexml_load_string($xml);
        if ($sx === false || !isset($sx->sheetData)) {
            return [];
        }

        $grid = [];
        foreach ($sx->sheetData->row as $row) {
            $cells = [];
            $maxIdx = -1;
            $autoIdx = 0;
            foreach ($row->c as $c) {
                $ref = (string) ($c['r'] ?? '');
                $colIdx = $ref !== '' ? self::colIndex($ref) : $autoIdx;
                $autoIdx = $colIdx + 1;

                $type = (string) ($c['t'] ?? '');
                if ($type === 'inlineStr') {
                    $val = isset($c->is) ? self::richText($c->is) : '';
                } elseif ($type === 's') {
                    $idx = (int) ((string) $c->v);
                    $val = $shared[$idx] ?? '';
                } else {
                    $val = isset($c->v) ? (string) $c->v : '';
                }

                $cells[$colIdx] = $val;
                if ($colIdx > $maxIdx) {
                    $maxIdx = $colIdx;
                }
            }

            $dense = [];
            for ($i = 0; $i <= $maxIdx; $i++) {
                $dense[] = $cells[$i] ?? '';
            }
            $grid[] = $dense;
        }
        return $grid;
    }

    /** Convert an A1-style cell ref's column letters to a 0-based index. */
    private static function colIndex(string $ref): int
    {
        if (!preg_match('/^([A-Za-z]+)/', $ref, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }
}
