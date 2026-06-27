<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Sends a one-click unsubscribe link to a subscriber after they request
 * one from the public Unsubscribe Center (`/subscriptions/manage`).
 *
 * Reuses the same signed-route shape as `NewsletterWelcomeMail` so the
 * link is a no-login GET that flips `unsubscribed_at` on the row. Failures
 * are swallowed and logged — the controller always returns the same
 * generic "if that email exists, we sent a link" response so visitors
 * can't enumerate which addresses are subscribed.
 */
class NewsletterUnsubscribeLinkMail
{
    public static function dispatchFor(NewsletterSubscriber $subscriber): bool
    {
        if (! $subscriber->email) {
            return false;
        }

        $subject = 'Manage your ' . config('app.name') . ' subscription';

        $unsubscribeUrl = URL::signedRoute(
            'site.newsletter.unsubscribe',
            ['subscriber' => $subscriber->id]
        );

        $viewData = [
            'subject'        => $subject,
            'appName'        => config('app.name'),
            'siteUrl'        => url('/'),
            'unsubscribeUrl' => $unsubscribeUrl,
        ];

        try {
            \App\Modules\Common\Services\Emailer::send('newsletter.unsubscribe_link', $subscriber->email, [
                'app_name' => config('app.name'),
            ], [
                'related'   => $subscriber,
                'view_data' => $viewData,
            ]);
        } catch (\Throwable $e) {
            Log::warning("newsletter-unsubscribe-link email failed for subscriber {$subscriber->id}: " . $e->getMessage());
            return false;
        }

        return true;
    }
}
