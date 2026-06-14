<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BiolinkReport;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class BiolinkReportController extends Controller
{
    /**
     * Public submit endpoint for visitor-filed reports on a biolink.
     *
     * Defends against drive-by abuse via:
     *  - honeypot field (`website` must be blank)
     *  - lightweight server-evaluated math CAPTCHA seeded into the form
     *  - per-IP RateLimiter (3 distinct submissions / 10 min)
     *  - coalesces a repeat report from the same IP+link inside the
     *    coalesce window into the existing pending row
     */
    public function store(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->first();
        if (!$link) {
            return response()->json(['ok' => false, 'error' => 'not_found'], 404);
        }

        // Honeypot — bots love filling every input.
        if ((string) $request->input('website', '') !== '') {
            return response()->json(['ok' => true]); // pretend success
        }

        $request->validate([
            'reason'      => 'required|string|in:' . implode(',', array_keys(BiolinkReport::REASONS)),
            'comment'     => 'nullable|string|max:1000',
            'captcha_a'   => 'required|integer',
            'captcha_b'   => 'required|integer',
            'captcha'     => 'required|integer',
        ]);

        // Math CAPTCHA — server re-derives the expected sum from the
        // hidden a/b values. Form re-renders fresh values on failure.
        $a = (int) $request->input('captcha_a');
        $b = (int) $request->input('captcha_b');
        if ((int) $request->input('captcha') !== $a + $b) {
            return response()->json(['ok' => false, 'error' => 'captcha'], 422);
        }

        $ip = (string) $request->ip();
        $rlKey = 'biolink-report:' . sha1($ip);
        if (RateLimiter::tooManyAttempts($rlKey, 3)) {
            $retry = RateLimiter::availableIn($rlKey);
            return response()->json([
                'ok' => false, 'error' => 'rate_limited', 'retry_after' => $retry,
            ], 429);
        }
        RateLimiter::hit($rlKey, 600); // 10 min window

        // Coalesce: same IP reporting the same link inside the window
        // bumps the existing pending row instead of creating a new one.
        $existing = BiolinkReport::where('link_id', $link->id)
            ->where('reporter_ip', $ip)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subHours(BiolinkReport::COALESCE_WINDOW_HOURS))
            ->first();

        if ($existing) {
            $existing->coalesced_count = $existing->coalesced_count + 1;
            // Append the latest comment if the visitor added more context.
            if ($request->filled('comment')) {
                $existing->comment = trim(($existing->comment ? $existing->comment . "\n---\n" : '') . $request->input('comment'));
            }
            $existing->save();
            return response()->json(['ok' => true, 'coalesced' => true]);
        }

        BiolinkReport::create([
            'link_id'     => $link->id,
            'reason'      => $request->input('reason'),
            'comment'     => $request->input('comment'),
            'reporter_ip' => $ip,
            'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            'status'      => 'pending',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Owner-side appeal for a warned/hidden biolink. Captures one
     * appeal message; admins read it from the moderation queue.
     */
    public function appeal(Request $request, Link $link)
    {
        if ((int) $link->user_id !== (int) optional($request->user())->id) {
            abort(403);
        }
        if (!in_array($link->moderation_state, ['warned', 'hidden'], true)) {
            return back()->with('error', 'This biolink is not under moderation.');
        }
        $request->validate(['message' => 'required|string|max:2000']);

        $link->moderation_appealed_at = now();
        $link->moderation_appeal_message = $request->input('message');
        $link->save();

        return back()->with('success', 'Appeal submitted. Our team will review it.');
    }
}
