<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\NotificationPreference;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\BacklinkDigestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = UserNotification::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(30);
        return view('user.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request)
    {
        UserNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return back()->with('success', 'Notifications marked read.');
    }

    public function preferences(BacklinkDigestService $backlinkDigest)
    {
        $catalog = NotificationService::catalog();
        $prefs   = NotificationPreference::where('user_id', auth()->id())
            ->get()
            ->keyBy('type')
            ->map(fn ($r) => [
                'in_app' => (bool) $r->in_app,
                'email'  => (bool) $r->email,
                'push'   => (bool) $r->push,
            ])
            ->all();

        // Surface backlink-digest cadence next to its toggle so creators
        // can see when the next email is due and when the last one went
        // out — mirrors the follower-digest preferred-hour metadata.
        // The view also reads `$user->backlink_digest_preferred_*` for
        // the weekday/hour pickers, so we expose the user too.
        $user = auth()->user();
        $backlinkDigestMeta = [
            'last_sent_at' => $user?->last_backlink_digest_sent_at,
            'next_run_at'  => $backlinkDigest->nextScheduledRun(),
            'sample_route' => route('user.notifications.backlink-digest.sample'),
        ];

        return view('user.notifications.preferences', compact('catalog', 'prefs', 'user', 'backlinkDigestMeta'));
    }

    /**
     * Send the signed-in user a one-off preview of the weekly backlink
     * digest using the last 7 days of backlinks. Mirrors the follower
     * digest "send sample" flow: rate-limited, never stamps
     * `last_backlink_digest_sent_at` (so the next real send is unaffected),
     * and always emails the signed-in user — never an arbitrary recipient.
     */
    public function sendBacklinkDigestSample(BacklinkDigestService $digest)
    {
        $user = auth()->user();
        if (! $user || ! $user->email || ! $user->email_verified_at) {
            return back()->with('error', "We need a verified email address on your account to send a sample digest.");
        }

        $rateKey = 'backlink-digest-sample:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            return back()->with('error', "You've sent a few sample digests recently — please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
        }
        RateLimiter::hit($rateKey, 3600);

        $built = $digest->buildDigest($user, true);
        if ($built === null) {
            return back()->with('error', "You don't have any new backlinks in the last 7 days yet, so there's nothing to preview. Once the radar finds new mentions they'll show up here.");
        }

        if (! $digest->dispatchEmail($user, $built)) {
            return back()->with('error', "Couldn't send the sample right now. Please try again in a moment.");
        }

        return back()->with('success', "Sample backlink digest sent to {$user->email} ({$built['total']} mention" . ($built['total'] === 1 ? '' : 's') . ").");
    }

    public function updatePreferences(Request $request)
    {
        $catalog = NotificationService::catalog();
        $input   = (array) $request->input('prefs', []);

        foreach ($catalog as $type => $meta) {
            $row = $input[$type] ?? [];
            NotificationPreference::updateOrCreate(
                ['user_id' => auth()->id(), 'type' => $type],
                [
                    'in_app' => (bool) (int) ($row['in_app'] ?? 0),
                    'email'  => (bool) (int) ($row['email']  ?? 0),
                    'push'   => (bool) (int) ($row['push']   ?? 0),
                ],
            );
        }

        // Backlink digest scheduling: preferred weekday (1=Mon..7=Sun)
        // and local hour (0..23) in the user's timezone. Clamped to safe
        // ranges so a malicious form post can't escape the scheduler's
        // matching window.
        $user = auth()->user();
        $weekday = (int) $request->input('backlink_digest_preferred_weekday', $user->backlink_digest_preferred_weekday ?? 1);
        $hour    = (int) $request->input('backlink_digest_preferred_hour', $user->backlink_digest_preferred_hour ?? 9);
        if ($weekday < 1 || $weekday > 7) $weekday = 1;
        if ($hour < 0 || $hour > 23) $hour = 9;
        $user->forceFill([
            'backlink_digest_preferred_weekday' => $weekday,
            'backlink_digest_preferred_hour'    => $hour,
        ])->save();

        return back()->with('success', 'Preferences saved.');
    }

    /**
     * Public, signed one-click unsubscribe target linked from the weekly
     * backlink-digest email. Does not require an authenticated session
     * so creators can opt out from any inbox client (RFC 8058 one-click
     * POST or a regular GET click both land here). The signed URL is
     * unguessable and bound to a specific user id, and flips only the
     * `backlink_digest` email channel — other notification preferences
     * are untouched.
     */
    public function unsubscribeBacklinkDigest(Request $request, User $user)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has been tampered with.');
        }

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'type' => 'backlink_digest'],
            [
                'in_app' => false,
                'email'  => false,
                'push'   => false,
            ],
        );

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
            . e($user->email) . ' will no longer receive the weekly backlink digest. '
            . 'You can re-enable it any time from your notification settings.'
            . '</p></div></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
