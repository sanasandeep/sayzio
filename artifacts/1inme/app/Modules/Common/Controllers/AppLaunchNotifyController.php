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
}
