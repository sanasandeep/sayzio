<?php

namespace App\Modules\Common\Controllers;

use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;

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
        // The preview flow grants paid access without collecting money or
        // verifying a provider charge, so it must never render in production.
        if (!MonetizationCheckout::previewCheckoutAllowed()) {
            abort(404);
        }

        // The preview URL must be server-signed to prevent direct access
        // by an attacker who crafts their own query string.
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or tampered checkout link.');
        }

        $data = $request->validate([
            'provider'  => 'required|string|max:32',
            'kind'      => 'required|in:subscription,ppv,tip,one_time,dm_msg,dm_att,product,form,event_ticket,booking',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);

        // Pre-generate a server-signed confirm URL so the form action itself
        // is authenticated. Attackers cannot submit the confirm form without
        // this server-issued URL (which requires knowing APP_KEY to forge).
        $confirmUrl = URL::temporarySignedRoute(
            'checkout.preview.confirm',
            now()->addMinutes(35),
            ['kind' => $data['kind'] === 'one_time' ? 'tip' : $data['kind'], 'reference' => $data['reference'], 'token' => $data['token']],
        );

        return view('public.monetization.checkout-preview', array_merge($data, ['confirmUrl' => $confirmUrl]));
    }

    public function confirmPreview(Request $request)
    {
        // Preview confirmation grants paid access without a real charge, so it
        // must never be reachable in production.
        if (!MonetizationCheckout::previewCheckoutAllowed()) {
            abort(404);
        }

        // Require a server-signed URL so this endpoint cannot be driven
        // directly by an attacker who has only the token from the preview page.
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or tampered confirmation link.');
        }

        $data = $request->validate([
            'kind'      => 'required|in:subscription,ppv,tip,dm_msg,dm_att,product,form,event_ticket,booking',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);
        return redirect()->route('checkout.return', $data);
    }

    /**
     * Live Razorpay hosted-checkout page. The RazorpayRouteAdapter creates
     * the order server-side and signs this URL; the page mounts Razorpay
     * Checkout (checkout.js) against that order and redirects the fan to
     * checkout.return afterwards. Actual settlement is done by the
     * signature-verified webhook, never by this page.
     */
    public function razorpay(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or tampered checkout link.');
        }

        $data = $request->validate([
            'order_id'  => 'required|string|max:64',
            'kind'      => 'required|in:subscription,ppv,tip,one_time,dm_msg,dm_att,product,form,event_ticket,booking',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
            'amount'    => 'required|integer|min:1',
            'currency'  => 'required|string|max:8',
        ]);

        return view('public.monetization.razorpay-checkout', array_merge($data, [
            'razorpayKey' => (string) env('RAZORPAY_KEY_ID'),
            'returnUrl'   => route('checkout.return', [
                'kind'      => $data['kind'] === 'one_time' ? 'tip' : $data['kind'],
                'reference' => $data['reference'],
                'token'     => $data['token'],
            ]),
        ]));
    }

    /**
     * Hosted Cashfree checkout page (creator payouts, Task #6643). The
     * CreatorPayouts CashfreeAdapter created the order (with a 100%
     * Easy Split to the creator's vendor) server-side and signs this
     * URL; the page mounts Cashfree's JS SDK against the
     * payment_session_id and redirects the fan to checkout.return
     * afterwards. Actual settlement is done by the signature-verified
     * webhook, never by this page.
     */
    public function cashfreePayout(Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or tampered checkout link.');
        }

        $data = $request->validate([
            'order_id'   => 'required|string|max:64',
            'session_id' => 'required|string|max:2048',
            'kind'       => 'required|in:subscription,ppv,tip,one_time,dm_msg,dm_att,product,form,event_ticket,booking',
            'reference'  => 'required|string|max:191',
            'token'      => 'required|string|max:64',
            'amount'     => 'required|integer|min:1',
            'currency'   => 'required|string|max:8',
        ]);

        return view('public.monetization.cashfree-checkout', array_merge($data, [
            'mode'      => strtolower((string) env('CASHFREE_ENV', 'live')) === 'sandbox' ? 'sandbox' : 'production',
            'returnUrl' => route('checkout.return', [
                'kind'      => $data['kind'] === 'one_time' ? 'tip' : $data['kind'],
                'reference' => $data['reference'],
                'token'     => $data['token'],
            ]),
        ]));
    }

    public function returnHandler(Request $request)
    {
        $data = $request->validate([
            'kind'      => 'required|in:subscription,ppv,tip,dm_msg,dm_att,product,form,event_ticket,booking',
            'reference' => 'required|string|max:191',
            'token'     => 'required|string|max:64',
        ]);
        $checkout = app(MonetizationCheckout::class);
        $r = $checkout->confirm($data['kind'], $data['reference'], $data['token']);
        // Live-provider flows (e.g. Razorpay Route) are settled by the
        // signature-verified webhook, and confirm() is preview-only (blocked
        // in production). If the webhook already settled this checkout, send
        // the buyer to the success destination instead of an error.
        if (!$r) $r = $checkout->settledResult($data['kind'], $data['reference'], $data['token']);
        if (!$r) return redirect('/')->with('error', 'Checkout link expired or already used.');
        return redirect()->away($r['url'])->with('success', $r['message'] ?? 'Done.');
    }
}
