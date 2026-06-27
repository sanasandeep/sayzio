<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Branded transactional welcome email sent immediately after a visitor
 * subscribes to the newsletter via the public site (footer / end-of-page CTA).
 *
 * Fired both for brand-new subscribers and for resubscribes (rows whose
 * unsubscribed_at was just cleared) so the confirmation experience is
 * symmetric. Failures are swallowed and logged — the in-page success
 * flash remains the source of truth for the user, and the row is already
 * persisted by the controller before we attempt delivery.
 */
class NewsletterWelcomeMail
{
    public static function dispatchFor(NewsletterSubscriber $subscriber): bool
    {
        if (! $subscriber->email) {
            return false;
        }

        $subject = 'Welcome to the ' . config('app.name') . ' newsletter';

        // Signed, no-login-required one-click unsubscribe link. Hits the
        // public NewsletterController@unsubscribe endpoint which flips
        // unsubscribed_at on this exact row. No expiry so a creator can
        // act on an old welcome email and still opt out.
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
            \App\Modules\Common\Services\Emailer::send('newsletter.welcome', $subscriber->email, [
                'app_name' => config('app.name'),
            ], [
                'related'   => $subscriber,
                'view_data' => $viewData,
            ]);
        } catch (\Throwable $e) {
            Log::warning("newsletter-welcome email failed for subscriber {$subscriber->id}: " . $e->getMessage());
            return false;
        }

        return true;
    }
}
