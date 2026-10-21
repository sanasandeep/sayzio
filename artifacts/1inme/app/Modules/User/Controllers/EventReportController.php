<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Services\Events\EventAttendeeReport;
use App\Support\CsvSafe;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The attendee list as a file.
 *
 * Streamed through CsvSafe::put like every other export here, which is
 * also why EventAttendeeReport::exportRows() hands over raw values: a
 * name beginning =, +, - or @ is a formula to a spreadsheet, cell()
 * neutralises it, and escaping in both places would prefix twice and
 * corrupt the name it was meant to protect.
 */
class EventReportController extends Controller
{
    /**
     * Who may download this is the route's business, not this method's.
     * It sits behind `workspace.can:links.view` like every other event
     * route, and Link's route-model binding is workspace-scoped, so a
     * link that is not the caller's never arrives here in the first
     * place.
     *
     * An ownership check of my own was written here first -- user_id
     * against the signed-in id -- and it would have locked out every
     * team member with links.view, which is the exact bug #527 was
     * about. The guard that already exists is the right one.
     */
    public function csv(Link $link): StreamedResponse
    {
        abort_unless($link->type === 'ics', 404);

        $rows = EventAttendeeReport::exportRows($link);

        // The alias, not the title: a title can be anything a person
        // typed, including characters a filesystem will not take.
        $filename = 'attendees-' . $link->alias . '-' . date('Ymd-His') . '.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                CsvSafe::put($out, $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
