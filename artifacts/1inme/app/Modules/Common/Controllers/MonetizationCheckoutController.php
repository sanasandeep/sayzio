<?php

namespace App\Modules\Common\Controllers;

use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Hosts the preview-mode confirmation page that stands in for a real
 * provider checkout, plus the return-from-provider hand-off route.
 *
 * - GET  /checkout/preview  — renders a Stripe-style confirmation page;
 *                              "Confirm" POSTs to the return route.
 * - GET  /checkout/return   — provider success redirect; reconciles the
 *                              pending subscription / unlock / tip and
 *                              forwards the fan to the creator profile.
 *
 * In a fully wired adapter the provider's hosted checkout owns the
 * preview screen; the return route stays the same so swapping a real
 * adapter in is a one-line change in the adapter.
 */
class MonetizationCheckoutController extends Controller
{
    public function preview(Request $request)
    {
        $data = $request->validate([
            'provider'  => 'required|string|max:32',
            'kind'      => 'required|in:subscription,ppv,tip,one_time,dm_msg,dm_att,product,form',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);
        return view('public.monetization.checkout-preview', $data);
    }

    public function confirmPreview(Request $request)
    {
        $data = $request->validate([
            'kind'      => 'required|in:subscription,ppv,tip,dm_msg,dm_att,product,form',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);
        return redirect()->route('checkout.return', $data);
    }

    public function returnHandler(Request $request)
    {
        $data = $request->validate([
            'kind'      => 'required|in:subscription,ppv,tip,dm_msg,dm_att,product,form',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);
        $r = app(MonetizationCheckout::class)->confirm($data['kind'], $data['reference'], $data['token']);
        if (!$r) return redirect('/')->with('error', 'Checkout link expired or already used.');
        return redirect()->away($r['url'])->with('success', $r['message'] ?? 'Done.');
    }
}
