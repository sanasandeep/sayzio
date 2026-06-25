<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\NewsletterSubscriber;
use App\Modules\Common\Services\NewsletterUnsubscribeLinkMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public Unsubscribe Center (`/subscriptions/manage`).
 *
 * The page lists every channel a visitor might be subscribed to (email
 * newsletter, WhatsApp Channel, WhatsApp DM) and explains how to opt
 * out of each. For email, visitors can request a one-click unsubscribe
 * link be mailed to them; for WhatsApp we surface the channel link plus
 * a pre-filled "STOP" wa.me URL since opt-out for those channels lives
 * inside WhatsApp itself.
 */
class SubscriptionsController extends Controller
{
    /**
     * Render the Unsubscribe Center page.
     */
    public function manage()
    {
        return view('public.subscriptions.manage', [
            'page' => (object) [
                'title'            => 'Manage subscriptions',
                'meta_description' => 'Unsubscribe from the Sayzio newsletter, WhatsApp Channel, or WhatsApp DMs.',
            ],
            'whatsappChannelUrl' => trim((string) AppSetting::get('marketing_whatsapp_channel_url', '')),
            'whatsappNumber'     => trim((string) AppSetting::get('marketing_whatsapp_number', '')),
        ]);
    }

    /**
     * Email a one-click unsubscribe link to the address the visitor enters.
     *
     * To prevent address-enumeration attacks the response is the same
     * regardless of whether the address is on the list — a generic
     * "if it's on file, we just emailed a link" flash. Honeypot, IP
     * rate limit and Laravel route throttle middleware all apply.
     */
    public function sendLink(Request $request)
    {
        $data = $request->validate([
            'email'   => 'required|email:rfc,filter|max:190',
            'website' => 'nullable|max:0', // honeypot
        ]);

        $generic = "If that email is on our list, we just sent a one-click unsubscribe link to it. Check your inbox (and spam folder).";

        // Honeypot tripped — silently succeed (don't tip off bots).
        if (!empty($request->input('website'))) {
            return back()->with('subscriptions_manage_status', $generic);
        }

        $key = 'subscriptions-manage:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withErrors(['email' => 'Too many attempts — please try again in a few minutes.'])
                ->withInput();
        }
        RateLimiter::hit($key, 600);

        $email = strtolower(trim($data['email']));
        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        // Only dispatch when the row exists *and* is currently active. A
        // row that is already opted-out doesn't need another link, and we
        // never confirm/deny existence to the visitor either way.
        if ($subscriber && ! $subscriber->unsubscribed_at) {
            NewsletterUnsubscribeLinkMail::dispatchFor($subscriber);
        }

        return back()->with('subscriptions_manage_status', $generic);
    }
}
