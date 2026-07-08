<?php

namespace App\Modules\Common\Services;

use App\Mail\SuspiciousLoginMail;
use App\Modules\User\Models\LoginEvent;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Suspicious-login email + one-click revoke pipeline.
 *
 * Hooked from every successful login site (web OTP, email+password,
 * mobile OTP, native social, web social OAuth, demo). Each call
 * records one `login_events` row and — when the country / OS family
 * / browser family is new vs. the user's recent login history —
 * fires the {@see SuspiciousLoginMail} alert.
 *
 * The "This wasn't me" button hits {@see revokeFromEmail()} which
 * (a) revokes the offending session, (b) signs every other session
 * out, and (c) clears the user's password so the forced reset flow
 * is the only way back in.
 */
class LoginAlertService
{
    /** Recent rows to compare against when deciding "is this new?" */
    public const HISTORY_WINDOW = 10;

    /**
     * Convenience wrapper: extracts IP and user-agent from a live Request
     * then delegates to {@see recordRaw}. Kept for backward compatibility
     * (tests, one-off calls outside the hot login path).
     *
     * For new login surfaces, prefer dispatching {@see \App\Jobs\RecordLoginEventJob}
     * instead — that moves the blocking GeoIP lookup fully off-request.
     */
    public function record(User $user, Request $request, string $channel, array $opts = []): ?LoginEvent
    {
        return $this->recordRaw(
            $user,
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            $channel,
            $opts,
        );
    }

    /**
     * Record a successful login and (when it looks unusual) send the
     * suspicious-login email. Accepts raw IP and user-agent strings so
     * it can be called from a queued job without a live Request object.
     *
     * Safe to call from any login site — failures are logged but never
     * thrown to the caller, so a mailer outage cannot break sign-in.
     *
     * @param array{
     *   personal_access_token_id?: int|null,
     *   session_id?: string|null,
     *   device_label?: string|null,
     *   force_alert?: bool,
     * } $opts
     * @param \Illuminate\Support\Carbon|null $occurredAt When the login actually
     *        happened (captured at dispatch time). When set, the login_events
     *        row's created_at reflects the real sign-in moment rather than
     *        the (possibly delayed) queue-worker execution time — this is the
     *        timestamp the Recent Logins page and API render.
     */
    public function recordRaw(User $user, string $ip, string $ua, string $channel, array $opts = [], ?\Illuminate\Support\Carbon $occurredAt = null): ?LoginEvent
    {
        try {
            $country = null;
            if ($ip !== '') {
                try {
                    $country = app(GeoIpService::class)->detectCountry($ip);
                } catch (\Throwable $e) {
                    // Don't let a geo lookup outage block an alert row.
                    Log::debug('login_alert_geo_failed: ' . $e->getMessage());
                }
            }

            $platform = $this->parsePlatform($ua);
            $browser  = $this->parseBrowser($ua);

            $deviceLabel = $opts['device_label'] ?? null;
            if (!$deviceLabel) {
                $parts = array_filter([$browser, $platform]);
                $deviceLabel = $parts ? implode(' on ', $parts) : 'Unknown device';
            }

            // Diff against the user's recent history BEFORE inserting
            // the new row so we don't compare a row to itself.
            $history = LoginEvent::where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(self::HISTORY_WINDOW)
                ->get(['country_code', 'platform', 'browser']);

            $reasons = [];
            if ($history->isNotEmpty()) {
                if ($country && !$history->pluck('country_code')->filter()->contains($country)) {
                    $reasons[] = 'country';
                }
                if ($platform && !$history->pluck('platform')->filter()->contains($platform)) {
                    $reasons[] = 'os';
                }
                if ($browser && !$history->pluck('browser')->filter()->contains($browser)) {
                    $reasons[] = 'browser';
                }
            }
            // First-ever login is informational — surface in history but
            // don't email (the user just signed up).
            $isNew = $history->isNotEmpty() && (!empty($reasons) || !empty($opts['force_alert']));

            $event = new LoginEvent([
                'user_id'                  => $user->id,
                'channel'                  => $channel,
                'ip'                       => $ip ?: null,
                'country_code'             => $country,
                'platform'                 => $platform,
                'browser'                  => $browser,
                'device_label'             => $deviceLabel,
                'user_agent'               => $ua ? Str::limit($ua, 500, '') : null,
                'personal_access_token_id' => $opts['personal_access_token_id'] ?? null,
                'session_id'               => $opts['session_id'] ?? null,
                'is_new'                   => $isNew,
                'new_reasons'              => $reasons ?: null,
                'alert_sent'               => false,
                'revoke_token'             => Str::random(48),
            ]);
            if ($occurredAt) {
                // Marking created_at dirty here stops Eloquent's automatic
                // timestamping from overwriting it with the save() moment.
                $event->created_at = $occurredAt;
                $event->updated_at = $occurredAt;
            }
            $event->save();

            if ($isNew && !empty($user->email)) {
                $this->sendAlert($user, $event, $reasons);
            }

            return $event;
        } catch (\Throwable $e) {
            Log::warning('login_alert_record_failed: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'channel' => $channel,
            ]);
            return null;
        }
    }

    private function sendAlert(User $user, LoginEvent $event, array $reasons): void
    {
        try {
            $url = URL::signedRoute('user.security.logins.revoke', ['token' => $event->revoke_token], now()->addDays(30));
            $label = $this->reasonsLabel($reasons);
            \App\Modules\Common\Services\Emailer::sendMailable('security.suspicious_login', $user->email, new SuspiciousLoginMail($user, $event, $url, $label), [], ['user' => $user->id, 'related' => $event]);
            $event->forceFill(['alert_sent' => true])->save();
        } catch (\Throwable $e) {
            Log::warning('login_alert_send_failed: ' . $e->getMessage(), [
                'user_id'  => $user->id,
                'event_id' => $event->id,
            ]);
        }
    }

    private function reasonsLabel(array $reasons): string
    {
        if (empty($reasons)) return 'sign-in';
        $map = ['country' => 'country', 'os' => 'device', 'browser' => 'browser'];
        $words = array_values(array_unique(array_map(fn ($r) => $map[$r] ?? $r, $reasons)));
        if (count($words) === 1) return $words[0];
        if (count($words) === 2) return $words[0] . ' and ' . $words[1];
        return implode(', ', array_slice($words, 0, -1)) . ', and ' . end($words);
    }

    /**
     * "This wasn't me" handler. Revokes the offending session, kills
     * every other session on the account, and clears the password so
     * the forced password reset flow is the only way back in.
     */
    public function revokeFromEmail(LoginEvent $event): User
    {
        /** @var User $user */
        $user = $event->user;

        // 1. Revoke the specific token that minted this session.
        if ($event->personal_access_token_id) {
            try {
                $user->tokens()->where('id', $event->personal_access_token_id)->delete();
            } catch (\Throwable $e) {
                Log::warning('login_alert_token_revoke_failed: ' . $e->getMessage());
            }
        }

        // 2. Kill EVERY other Sanctum token + every web session for the
        //    account so the attacker can't keep using a parallel one.
        try {
            $user->tokens()->delete();
        } catch (\Throwable $e) {
            Log::warning('login_alert_tokens_purge_failed: ' . $e->getMessage());
        }
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {
            Log::debug('login_alert_sessions_purge_failed: ' . $e->getMessage());
        }

        // 3. Force a password reset on next sign-in. We replace the
        //    password hash with an unguessable random string so the
        //    attacker (and the legitimate user) can only get back in
        //    via the password-reset / OTP flow. Remember-me cookie is
        //    invalidated by rotating the remember_token too.
        try {
            $user->forceFill([
                'password'       => Hash::make(Str::random(48)),
                'remember_token' => Str::random(60),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('login_alert_password_reset_failed: ' . $e->getMessage());
        }

        if (!$event->revoked_at) {
            $event->forceFill(['revoked_at' => now()])->save();
        }

        return $user;
    }

    private function parsePlatform(string $ua): ?string
    {
        if ($ua === '') return null;
        if (str_contains($ua, '1INMEMobileApp')) {
            if (str_contains($ua, 'ios'))     return 'iOS';
            if (str_contains($ua, 'android')) return 'Android';
            return 'Mobile App';
        }
        if (preg_match('/\(iPhone|iPad|iPod/', $ua))                 return 'iOS';
        if (str_contains($ua, 'Android'))                            return 'Android';
        if (str_contains($ua, 'Windows'))                            return 'Windows';
        if (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) return 'macOS';
        if (str_contains($ua, 'Linux'))                              return 'Linux';
        if (str_contains($ua, 'CrOS'))                               return 'ChromeOS';
        return 'Unknown';
    }

    private function parseBrowser(string $ua): ?string
    {
        if ($ua === '') return null;
        if (str_contains($ua, '1INMEMobileApp')) return 'Sayzio app';
        // Edge/OPR/Brave must be checked before Chrome since they
        // include "Chrome" in their UA strings.
        if (str_contains($ua, 'Edg/'))                              return 'Edge';
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
        if (str_contains($ua, 'Brave'))                             return 'Brave';
        if (str_contains($ua, 'Firefox/'))                          return 'Firefox';
        if (str_contains($ua, 'Chrome/'))                           return 'Chrome';
        if (str_contains($ua, 'Safari/'))                           return 'Safari';
        return 'Unknown';
    }
}
