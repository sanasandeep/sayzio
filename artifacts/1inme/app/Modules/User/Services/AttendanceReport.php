<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\AttendanceRequest;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;

class AttendanceReport
{
    public function rows($participants, CarbonImmutable $from, CarbonImmutable $to, string $zone, string $mode): array
    {
        $headers = $mode === 'summary' ? ['Staff', 'Email', 'From', 'To', 'Timezone', 'Recorded hours', 'Approved overtime minutes', 'Approved leave requests'] : ['Staff', 'Email', 'Date', 'Timezone', 'Recorded hours', 'Approved overtime minutes', 'Approved leave requests'];
        $rows = [$headers];
        foreach ($participants as $person) {
            $anchor = $person->punches()->where('occurred_at', '<', $from->utc())->latest('occurred_at')->latest('id')->first();
            $events = $person->punches()->where('occurred_at', '>=', $from->utc())->where('occurred_at', '<', $to->utc())->orderBy('occurred_at')->orderBy('id')->get();
            if ($anchor) {
                $events = collect([$anchor])->concat($events);
            }
            $requests = AttendanceRequest::where('participant_id', $person->id)->where('status', 'approved')->where('starts_on', '<', $to->toDateString())->where('ends_on', '>=', $from->toDateString())->get();
            $name = $person->display_name ?: $person->staff?->name;
            $email = $person->staff?->email;
            if ($mode === 'summary') {
                $rows[] = [$name, $email, $from->toDateString(), $to->subDay()->toDateString(), $zone, round(app(AttendanceService::class)->workedSeconds($events, $from, $to) / 3600, 2), $requests->where('type', 'overtime')->sum('minutes'), $requests->where('type', 'leave')->count()];
            } else {
                for ($day = $from; $day->lt($to); $day = $day->addDay()) {
                    $date = $day->toDateString();
                    $rows[] = [$name, $email, $date, $zone, round(app(AttendanceService::class)->workedSeconds($events, $day, $day->addDay()) / 3600, 2), $requests->where('type', 'overtime')->where('starts_on', $date)->sum('minutes'), $requests->where('type', 'leave')->filter(fn ($r) => $r->starts_on <= $date && $r->ends_on >= $date)->count()];
                }
            }
        }

return $rows;
    }

    public function csv(array $rows): string
    {
        $file = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($file, array_map(fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r\n]/u', $v) ? "'".$v : $v, $row), ',', '"', '');
        }rewind($file);
        $bytes = stream_get_contents($file);
        fclose($file);

        return $bytes;
    }

    public function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'attendance-xlsx-');
        $zip = new \ZipArchive;
        try {
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot create Excel export.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Attendance" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="2" width="28" customWidth="1"/><col min="3" max="8" width="22" customWidth="1"/></cols><sheetData>';
            foreach ($rows as $i => $row) {
                $xml .= '<row r="'.($i + 1).'">';
                foreach ($row as $j => $v) {
                    $ref = chr(65 + $j).($i + 1);
                    if (is_int($v) || is_float($v)) {
                        $xml .= '<c r="'.$ref.'"><v>'.$v.'</v></c>';
                    } else {
                        $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                    }
                }$xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function pdf(array $rows, array $meta): string
    {
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
        $pdf->loadHtml(view('user.attendance.report', ['rows' => $rows, 'meta' => $meta])->render());
        $pdf->setPaper('a4','landscape');
        $pdf->render();

        return $pdf->output();
    }
}
