<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Controllers\RedirectController;
use App\Modules\User\Models\Link;
use App\Services\Events\EventConnectService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Mobile parity for the Event Connect QR flow (Task #6687, web flow from
 * Task #6685):
 *
 *  - `qr`      → the host's view of the Connect QR for one of their own
 *                event links (SVG for inline display + a PNG payload for
 *                share/download), mirroring QrCodeController@connectQr.
 *  - `connect` → the guest side: a signed-in app user who opened a
 *                `?src=connect_qr` event URL confirms once and the shared
 *                EventConnectService RSVPs them "yes" + follows the host,
 *                exactly like the web prompt's `confirm` endpoint.
 */
class EventConnectQrApiController extends Controller
{
    use ApiResponses;

    /** Host: Connect QR payload for one of their own event links. */
    public function qr(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        $link = Link::where('user_id', $user->id)->find($id);
        if (!$link || $link->type !== 'ics') {
            return $this->notFound('Event not found');
        }

        $connectUrl = $link->getShortUrl() . '?src=connect_qr';

        $qrSvg = QrCode::format('svg')
            ->size(280)
            ->errorCorrection('M')
            ->margin(1)
            ->generate($connectUrl);

        // PNG for native share/save — base64 so the app can write it to a
        // local file without a second authenticated request. PNG rendering
        // needs the imagick backend; when it's unavailable the app falls
        // back to saving the SVG instead, so this is best-effort.
        $qrPng = null;
        try {
            $qrPng = QrCode::format('png')
                ->size(600)
                ->errorCorrection('M')
                ->margin(1)
                ->generate($connectUrl);
        } catch (\Throwable $e) {
            \Log::info('Connect QR PNG render unavailable: ' . $e->getMessage());
        }

        // Event details for the printable poster (Task #6693): name, date and
        // venue from ics_data so the app can compose the poster locally.
        $ics = $link->icsData;

        return $this->ok([
            'link'           => [
                'id'    => (int) $link->id,
                'alias' => $link->alias,
                'title' => $link->title,
            ],
            'event'          => [
                'name'       => $ics?->event_name ?: ($link->title ?: $link->alias),
                'start_date' => $ics?->start_date?->toIso8601String(),
                'all_day'    => (bool) ($ics?->all_day ?? false),
                'timezone'   => $ics?->timezone,
                'location'   => $ics?->location,
            ],
            'connect_url'    => $connectUrl,
            'qr_svg'         => (string) $qrSvg,
            'qr_png_base64'  => $qrPng !== null ? base64_encode($qrPng) : null,
        ]);
    }

    /**
     * Guest: one-tap RSVP & Connect for the signed-in app user. Mirrors the
     * web EventConnectQrController@confirm (the app has no anonymous viewer
     * session — every caller is an authenticated, existing account, so
     * `was_new_user` is always false here).
     */
    public function connect(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics' || !$link->isAccessible()) {
            return $this->notFound('Event not found');
        }
        if (!RedirectController::isRsvpAvailable($link)) {
            return response()->json(['success' => false, 'message' => 'RSVPs are not open for this event.'], 422);
        }

        [$payload, $status] = app(EventConnectService::class)
            ->connect($request, $link, $user, false);

        return response()->json($payload, $status);
    }
}
