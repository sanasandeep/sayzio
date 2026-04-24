<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\NewsletterIssue;
use App\Modules\Common\Models\NewsletterIssueUnsubscribe;
use App\Modules\Common\Models\NewsletterSubscriber;
use App\Modules\Common\Services\NewsletterWelcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email'   => 'required|email:rfc,filter|max:190',
            'source'  => 'nullable|string|max:80',
            'website' => 'nullable|max:0', // honeypot
        ]);

        // Per-source flash key so when the visitor submits one card on a
        // page that hosts the 3-way Subscribe block, only that specific
        // card shows the success banner — not every block on every page
        // and not the WhatsApp cards next to it. Source values from the
        // new block look like `subscribe-block:<page>`; we strip the
        // prefix so the flash key stays compact.
        $rawSource    = (string) ($data['source'] ?? 'footer');
        $flashSuffix  = preg_replace('/^subscribe-block:/', '', $rawSource);
        $flashSuffix  = $flashSuffix !== '' ? $flashSuffix : 'footer';
        $flashKey     = 'newsletter_success_' . $flashSuffix;

        // Honeypot tripped — silently succeed.
        if (!empty($request->input('website'))) {
            return back()->with($flashKey, 'Thanks for subscribing!');
        }

        $key = 'newsletter:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withErrors(['email' => 'Too many attempts — please try again in a few minutes.'])
                ->withInput();
        }
        RateLimiter::hit($key, 600);

        $email = strtolower(trim($data['email']));

        $existing = NewsletterSubscriber::where('email', $email)->first();
        if ($existing) {
            // Re-subscribe if previously unsubscribed; resubscribes also get
            // a fresh welcome email so the confirmation experience is symmetric
            // with brand-new signups. If the row is already active we stay
            // silent (no email, no row mutation) to avoid welcome-email spam
            // from someone double-submitting the form.
            if ($existing->unsubscribed_at) {
                $existing->unsubscribed_at  = null;
                $existing->unsubscribe_source = null;
                // Refresh the source so admin reports reflect the channel
                // that re-engaged this subscriber, not the original one.
                $existing->source = $rawSource ?: $existing->source;
                $existing->save();
                NewsletterWelcomeMail::dispatchFor($existing);
            }
            return back()->with($flashKey, "You're already on the list — thanks!");
        }

        $subscriber = NewsletterSubscriber::create([
            'email'      => $email,
            'source'     => $rawSource,
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        NewsletterWelcomeMail::dispatchFor($subscriber);

        return back()->with($flashKey, 'Thanks! You are subscribed.');
    }

    /**
     * One-click unsubscribe target linked from the welcome email and from
     * the RFC 2369/8058 `List-Unsubscribe` header on every newsletter
     * issue. The link is a Laravel signed URL bound to the subscriber id,
     * so possession of the email is enough to opt out — no login required.
     * Idempotent: a second click on the same link just reflects the
     * already-unsubscribed state.
     *
     * Accepts both GET (recipient clicks the footer link in their browser)
     * and POST (inbox provider performs a one-click opt-out per RFC 8058
     * after the user taps the native "Unsubscribe" chip). For POST we
     * return a bare 200 with no HTML body — that is what Gmail/Apple Mail
     * expect from the one-click endpoint.
     */
    public function unsubscribe(Request $request, NewsletterSubscriber $subscriber)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has expired.');
        }

        if (! $subscriber->unsubscribed_at) {
            $subscriber->unsubscribed_at = now();
            // Distinguish footer-link clicks (GET, opened in a browser) from
            // inbox-provider one-click pings (POST per RFC 8058) so admins
            // can tell at a glance which channel drove the opt-out.
            $subscriber->unsubscribe_source = $request->isMethod('post') ? 'inbox' : 'footer';
            $subscriber->save();

            // Attribute this opt-out to the issue that carried the link, so
            // admins can see which issues drive churn. We only count it on
            // the first valid click (not on idempotent re-clicks) and we
            // ignore unknown/missing issue ids defensively.
            //
            // We also persist the (subscriber, issue) pair in a small audit
            // table so admins can drill into a specific issue and see which
            // subscribers opted out from it — and so the per-issue counter
            // remains recoverable if it ever drifts.
            $issueId = (int) $request->query('issue', 0);
            if ($issueId > 0 && NewsletterIssue::where('id', $issueId)->exists()) {
                NewsletterIssue::where('id', $issueId)->increment('unsubscribed_count');
                NewsletterIssueUnsubscribe::firstOrCreate(
                    ['subscriber_id' => $subscriber->id, 'issue_id' => $issueId],
                    ['unsubscribed_at' => $subscriber->unsubscribed_at],
                );
            }
        }

        if ($request->isMethod('post')) {
            return response('', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Unsubscribed</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head><body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;margin:0;padding:40px 16px;">'
            . '<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">'
            . '<h1 style="font-size:20px;color:#1e293b;margin:0 0 12px 0;">You\'ve been unsubscribed</h1>'
            . '<p style="font-size:14px;color:#475569;line-height:1.6;margin:0 0 20px 0;">'
            . e($subscriber->email) . ' will no longer receive our newsletter. Changed your mind? You can resubscribe any time from the footer of <a href="' . e(url('/')) . '" style="color:#2563eb;text-decoration:none;">our site</a>.'
            . '</p></div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
