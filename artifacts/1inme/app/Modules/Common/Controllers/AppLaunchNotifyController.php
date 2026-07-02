<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\AppLaunchSignup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public "Notify me when the app launches" signups from the mobile-app
 * coming-soon modal (store-buttons partial). No login required — the modal
 * submits via fetch and renders the response inline, so this endpoint always
 * answers in JSON.
 */
class AppLaunchNotifyController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot tripped — silently pretend success so bots learn nothing.
        if (!empty($request->input('website'))) {
            return response()->json([
                'ok'      => true,
                'message' => "You're on the list — we'll email you at launch!",
            ]);
        }

        $key = 'app-launch-notify:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Too many attempts — please try again in a few minutes.',
            ], 429);
        }
        RateLimiter::hit($key, 600);

        $validator = validator($request->all(), [
            'email' => 'required|email:rfc,filter|max:190',
            'store' => 'nullable|string|in:play,app',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Please enter a valid email address.',
            ], 422);
        }
        $data  = $validator->validated();
        $email = strtolower(trim($data['email']));

        $existing = AppLaunchSignup::where('email', $email)->first();
        if ($existing) {
            // Duplicate — nothing to mutate; keep the response friendly and
            // indistinguishable from a fresh signup so the endpoint doesn't
            // leak which emails are already on the list.
            return response()->json([
                'ok'      => true,
                'message' => "You're on the list — we'll email you at launch!",
            ]);
        }

        try {
            AppLaunchSignup::create([
                'email'      => $email,
                'store'      => $data['store'] ?? null,
                'ip'         => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent duplicate submission raced past the existence check —
            // the row already exists, which is exactly the outcome we wanted.
        }

        return response()->json([
            'ok'      => true,
            'message' => "You're on the list — we'll email you at launch!",
        ]);
    }

    /**
     * One-click unsubscribe target linked from the launch announcement email
     * and from the RFC 2369/8058 `List-Unsubscribe` header. The link is a
     * Laravel signed URL bound to the signup id, so possession of the email is
     * enough to opt out — no login required. Idempotent: a second click just
     * reflects the already-unsubscribed state.
     *
     * Accepts both GET (recipient clicks the footer link in their browser) and
     * POST (inbox provider performs a one-click opt-out per RFC 8058 after the
     * user taps the native "Unsubscribe" chip). For POST we return a bare 200
     * with no HTML body — that is what Gmail/Apple Mail expect.
     */
    public function unsubscribe(Request $request, AppLaunchSignup $signup)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has expired.');
        }

        if (! $signup->unsubscribed_at) {
            $signup->forceFill(['unsubscribed_at' => now()])->save();
        }

        if ($request->isMethod('post')) {
            return response('', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $appName = (string) config('app.name', 'Sayzio');

        return response(
            '<!doctype html><html><head><meta charset="utf-8"><title>Unsubscribed</title>'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '</head><body style="font-family:Arial,Helvetica,sans-serif;background:#f8fafc;margin:0;padding:40px 16px;">'
            . '<div style="max-width:480px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;box-shadow:0 1px 3px rgba(0,0,0,0.08);text-align:center;">'
            . '<h1 style="font-size:20px;color:#1e293b;margin:0 0 12px 0;">You\'ve been unsubscribed</h1>'
            . '<p style="font-size:14px;color:#475569;line-height:1.6;margin:0 0 20px 0;">'
            . e($signup->email) . ' will no longer receive ' . e($appName) . ' app-launch emails.'
            . '</p></div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
