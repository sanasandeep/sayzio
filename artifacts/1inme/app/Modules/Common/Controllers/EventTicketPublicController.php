<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\EventTicketTier;
use App\Modules\User\Models\Link;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Public buy + QR-ticket-view surface for `ics` events with ticketing
 * enabled (Task #3589). Registered under the alias catch-all's reserved
 * multi-segment `/{alias}/tickets/*` paths so it never collides with the
 * single-segment `/{alias}` redirect route.
 */
class EventTicketPublicController extends Controller
{
    public function buy(Request $request, string $alias, MonetizationCheckout $checkout)
    {
        $link = Link::where('alias', $alias)->where('type', 'ics')->firstOrFail();
        abort_unless((bool) (($link->settings ?? [])['ticketing_enabled'] ?? false), 404);
        // Cancelled events don't sell tickets (Sayzio events cancel flow).
        // Shared gate with the API buy flow so the two can't drift.
        if (($reason = $link->eventTicketSalesClosedReason()) !== null) {
            return back()->with('error', $reason);
        }

        $data = $request->validate([
            'tier_id'  => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255'],
            'phone'    => ['nullable', 'string', 'max:50'],
        ]);

        $tier = EventTicketTier::where('link_id', $link->id)->where('is_active', true)
            ->findOrFail($data['tier_id']);

        $fan = Auth::guard('web')->user();
        if (!$fan) {
            // Guests can buy tickets without an account — attach to (or
            // create) a lightweight user record keyed by email, mirroring
            // the paid-page/product-checkout guest flow.
            $fan = \App\Modules\User\Models\User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => bcrypt(\Illuminate\Support\Str::random(24))],
            );
        }

        $result = $checkout->startEventTicket(
            $fan,
            $tier,
            (int) ($data['quantity'] ?? 1),
            ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null],
            route('redirect.handle', ['alias' => $alias]),
        );

        return redirect($result['url']);
    }

    public function viewTicket(Request $request, string $alias, string $code)
    {
        $link = Link::where('alias', $alias)->where('type', 'ics')->firstOrFail();
        $ticket = EventTicket::where('link_id', $link->id)->where('code', $code)
            ->with(['tier', 'link.icsData'])->firstOrFail();

        $checkinUrl = route('user.events.checkin.lookup', ['link' => $link->id, 'code' => $ticket->code]);
        $qr = QrCode::format('svg')->size(260)->margin(1)->generate($checkinUrl);

        return view('common.event-ticket', compact('link', 'ticket', 'qr'));
    }
}
