<?php

namespace App\Modules\Admin\Controllers;

use App\Console\Commands\SendStarterFreeWindowReminders;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\User;
use App\Services\Integrations\MailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Admin tool for the yearly free-Starter renewal reminder
 * (`starter:send-free-window-reminders`). Lets an admin:
 *   - preview the exact reminder email (rendered in an iframe) and a faithful
 *     mock of the in-app notification a user receives,
 *   - send a test reminder (email + in-app) to their own account before it
 *     ever reaches real users, so copy/branding changes can be checked safely,
 *   - see how many free-Starter users are due for renewal in the next 30 days.
 *
 * The reminder is purely a once-a-year re-confirmation nudge — lapsing never
 * locks an account or downgrades anything — so this tool is read-only plus a
 * rate-limited self-send; it changes nothing about real users.
 */
class StarterRenewalReminderController extends Controller
{
    public function index(Request $request)
    {
        $search   = trim((string) $request->query('q', ''));
        $selected = $search !== '' ? $this->findUser($search) : null;

        return view('admin.starter-renewals.index', [
            'stats'   => $this->stats(),
            'inApp'   => SendStarterFreeWindowReminders::inAppCopy(),
            'adminEmail' => optional(Auth::guard('admin')->user())->email ?? Auth::user()?->email,
            // User-targeted preview: when an admin searches a real user we
            // preview that user's exact reminder; otherwise we fall back to the
            // sample built from the admin's own account / a placeholder.
            'search'         => $search,
            'selectedUser'   => $selected,
            'selectedEndsAt' => $selected ? ($selected->starter_free_window_ends_at ?: now()->addDays(7)) : null,
            'searchNotFound' => $search !== '' && ! $selected,
        ]);
    }

    /**
     * Render the live reminder email with sample data so an admin can preview
     * exactly what users receive. Returned raw (not inside the admin layout)
     * so it can be embedded in a preview iframe.
     */
    public function previewEmail(Request $request)
    {
        [$user, $renewUrl, $endsAt] = $this->sampleContext($request);

        return view('emails.starter-free-window-reminder', [
            'user'     => $user,
            'renewUrl' => $renewUrl,
            'endsAt'   => $endsAt,
        ]);
    }

    /**
     * Send a real test reminder (email + in-app notification) to the logged-in
     * admin's own account through the same mailer/notification path the live
     * job uses, so delivery and rendering can be confirmed before going live.
     * Rate-limited so the button can't be spammed.
     */
    public function sendSample(Request $request, NotificationService $prefs)
    {
        $admin = Auth::guard('admin')->user() ?: $request->user();
        if (! $admin || empty($admin->email)) {
            return back()->with('error', 'We could not find an email address on your admin account to send the test reminder to.');
        }

        $rateKey = 'starter-renewal-sample:' . $admin->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            return back()->with('error', "You've sent a few test reminders recently — please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
        }
        RateLimiter::hit($rateKey, 600);

        // Apply the saved SMTP settings to the current process before sending,
        // so the test reflects exactly what's configured.
        MailSettings::applyRuntimeConfig();

        // The test send always targets the admin's own account — never a
        // searched user — so we deliberately ignore any search term here and
        // never drop a real notification into someone else's feed.
        [$previewUser, $renewUrl, $endsAt, $isRealUser] = $this->sampleContext($request, withFlags: true, allowSearch: false);

        // In-app notification — only drop a real feed row when the admin has a
        // matching User account; otherwise the email alone is the test.
        $inAppDelivered = false;
        if ($isRealUser) {
            $copy = SendStarterFreeWindowReminders::inAppCopy();
            $created = $prefs->notify($previewUser, 'starter.free_window_renewal', array_merge($copy, [
                'url'     => route('user.dashboard'),
                'ends_at' => $endsAt?->toIso8601String(),
            ]));
            $inAppDelivered = $created !== null;
        }

        try {
            Mail::send(
                'emails.starter-free-window-reminder',
                ['user' => $previewUser, 'renewUrl' => $renewUrl, 'endsAt' => $endsAt],
                function ($message) use ($admin) {
                    $message->to($admin->email);
                    $message->subject('[Test] Keep your free 1INME Starter plan — renew for another year');
                }
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Test reminder email failed: ' . $e->getMessage());
        }

        if (MailSettings::mailer() === 'log') {
            return back()->with('info', 'The mailer is set to "log" — the test email was written to the log, not delivered. Choose the SMTP mailer to send live.' . ($inAppDelivered ? ' An in-app notification was added to your account.' : ''));
        }

        $msg = 'Test reminder email dispatched to ' . $admin->email . '.';
        if ($inAppDelivered) {
            $msg .= ' An in-app notification was also added to your account.';
        } elseif (! $isRealUser) {
            $msg .= ' (No in-app notification was added because your admin login has no matching user account.)';
        }

        return back()->with('success', $msg);
    }

    /**
     * Find a real user to preview by id or email. Tries an exact id match
     * first (when the term is all digits), then an exact email match.
     */
    private function findUser(string $search): ?User
    {
        $search = trim($search);
        if ($search === '') {
            return null;
        }

        if (ctype_digit($search)) {
            $byId = User::find((int) $search);
            if ($byId) {
                return $byId;
            }
        }

        return User::where('email', $search)->first();
    }

    /**
     * Build a (user, renewUrl, endsAt) tuple for previews/tests.
     *
     * When $allowSearch is on and the admin supplied a `q` term that resolves
     * to a real user, that user's exact reminder is built — their real
     * free-window end date and a real signed renew link. Otherwise it uses the
     * admin's matching User account when one exists (a real signed "self"
     * link), and finally falls back to a display-only placeholder recipient
     * with a sample (non-resolving) link.
     *
     * @return array{0: User, 1: string, 2: ?Carbon, 3?: bool}
     */
    private function sampleContext(Request $request, bool $withFlags = false, bool $allowSearch = true): array
    {
        // A specific real user chosen by the admin takes precedence.
        if ($allowSearch) {
            $search   = trim((string) $request->input('q', ''));
            $selected = $search !== '' ? $this->findUser($search) : null;
            if ($selected) {
                $endsAt = $selected->starter_free_window_ends_at ?: now()->addDays(7);
                $renewUrl = URL::temporarySignedRoute(
                    'user.starter.renew-free-window.link',
                    now()->addDays(60),
                    ['user' => $selected->id]
                );

                return $withFlags ? [$selected, $renewUrl, $endsAt, true] : [$selected, $renewUrl, $endsAt];
            }
        }

        $admin = Auth::guard('admin')->user() ?: $request->user();
        $realUser = $admin && ! empty($admin->email)
            ? User::where('email', $admin->email)->first()
            : null;

        if ($realUser) {
            $endsAt = $realUser->starter_free_window_ends_at ?: now()->addDays(7);
            $renewUrl = URL::temporarySignedRoute(
                'user.starter.renew-free-window.link',
                now()->addDays(60),
                ['user' => $realUser->id]
            );
            $user = $realUser;
            $isReal = true;
        } else {
            $user = new User();
            $user->name  = ($admin && ! empty($admin->name)) ? $admin->name : 'there';
            $user->email = $admin->email ?? 'preview@example.com';
            $endsAt = now()->addDays(7);
            // Placeholder link so the template renders fully; it points at a
            // non-existent account and simply won't resolve if clicked.
            $renewUrl = url('/starter/renew-free-window/0?sample=1');
            $isReal = false;
        }

        return $withFlags ? [$user, $renewUrl, $endsAt, $isReal] : [$user, $renewUrl, $endsAt];
    }

    /**
     * Counts that give admins a feel for the reminder's reach — mirrors the
     * command's targeting (active, has email, on the lineup default plan, has a
     * free window). No new schema; all derived from existing columns.
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $now = Carbon::now();
        $default = Plan::defaultPlan();

        // Base population the command can ever target: active free-Starter users
        // with an email and a tracked free window.
        $base = function () use ($default) {
            $q = User::query()
                ->where('status', 'active')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->whereNotNull('starter_free_window_ends_at');
            if ($default) {
                $q->where(function ($w) use ($default) {
                    $w->where('plan_id', $default->id)->orWhereNull('plan_id');
                });
            }
            return $q;
        };

        $onStarter = $base()->count();

        // Windows lapsing in the next 30 days (upcoming, not already past) — the
        // headline "due for renewal soon" figure.
        $dueNext30Days = $base()
            ->whereBetween('starter_free_window_ends_at', [$now, $now->copy()->addDays(30)])
            ->count();

        // Already lapsed windows still awaiting a re-confirmation.
        $lapsed = $base()
            ->where('starter_free_window_ends_at', '<', $now)
            ->count();

        // Distinct users reminded in the last 30 days (by the per-window stamp).
        $remindedLast30Days = $base()
            ->whereNotNull('starter_renewal_reminder_sent_at')
            ->where('starter_renewal_reminder_sent_at', '>=', $now->copy()->subDays(30))
            ->count();

        // Anchor the last run from the most recent reminder stamp; count users
        // stamped within 12 hours of it to capture that single daily run.
        $lastRunAt = $base()->max('starter_renewal_reminder_sent_at');
        $lastRunAt = $lastRunAt ? Carbon::parse($lastRunAt) : null;
        $lastRunCount = 0;
        if ($lastRunAt) {
            $lastRunCount = $base()
                ->where('starter_renewal_reminder_sent_at', '>=', $lastRunAt->copy()->subHours(12))
                ->count();
        }

        return [
            'onStarter'          => $onStarter,
            'dueNext30Days'      => $dueNext30Days,
            'lapsed'             => $lapsed,
            'remindedLast30Days' => $remindedLast30Days,
            'lastRunAt'          => $lastRunAt,
            'lastRunCount'       => $lastRunCount,
            'defaultPlanName'    => $default?->name,
        ];
    }
}
