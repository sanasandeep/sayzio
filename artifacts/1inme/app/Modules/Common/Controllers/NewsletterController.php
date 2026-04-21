<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'email'   => 'required|email:rfc,filter|max:190',
            'source'  => 'nullable|string|max:60',
            'website' => 'nullable|max:0', // honeypot
        ]);

        // Honeypot tripped — silently succeed.
        if (!empty($request->input('website'))) {
            return back()->with('newsletter_success', 'Thanks for subscribing!');
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
            // Re-subscribe if previously unsubscribed; otherwise treat as success silently.
            if ($existing->unsubscribed_at) {
                $existing->unsubscribed_at = null;
                $existing->save();
            }
            return back()->with('newsletter_success', "You're already on the list — thanks!");
        }

        NewsletterSubscriber::create([
            'email'      => $email,
            'source'     => $data['source'] ?? 'footer',
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return back()->with('newsletter_success', 'Thanks! You are subscribed.');
    }
}
