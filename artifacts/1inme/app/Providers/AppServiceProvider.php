<?php

namespace App\Providers;

use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\GoogleCalendarProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CalendarProviderRegistry::class, function () {
            $r = new CalendarProviderRegistry();
            $r->register('google', fn () => new GoogleCalendarProvider());
            // Microsoft + CalDAV drivers will register here once implemented.
            return $r;
        });
    }

    public function boot(): void
    {
        // Note: App\Listeners\IssueInvoiceOnSubscriptionActivated is wired to
        // App\Events\SubscriptionActivated by Laravel's event auto-discovery
        // (typed handle() method on a class under app/Listeners). An explicit
        // Event::listen here would register a second subscription and cause
        // double-invoicing.

        // Blade directive: @canInWorkspace('posts.create') ... @endcanInWorkspace
        // Honors super-admin/owner bypass via User::canInWorkspace().
        \Illuminate\Support\Facades\Blade::if('canInWorkspace', function (string $permission) {
            $user = auth()->user();
            if (!$user) return false;
            $ws = app()->bound('current_workspace') ? app('current_workspace') : null;
            if (!$ws) return false;
            return $user->canInWorkspace($ws, $permission);
        });

        $this->configureAuthRateLimiters();
    }

    /**
     * Named rate limiters for the authentication surface.
     *
     * The default `throttle:N,M` middleware only keys on the requesting
     * IP, which means a single attacker behind one IP can lock out a
     * whole CGNAT-shared mobile carrier — and an attacker on a botnet
     * can fan out across many IPs to bypass the limit entirely.
     *
     * These limiters key on (identifier + IP) so the limit follows both
     * the targeted account AND the source, and they layer a generous
     * per-IP ceiling on top to catch the distributed-spray case.
     */
    protected function configureAuthRateLimiters(): void
    {
        // OTP issuance — expensive (sends email/SMS, costs money).
        // Tightest of the three.
        RateLimiter::for('otp-send', function (Request $request) {
            $identifier = strtolower((string) $request->input('identifier', ''));
            $ip         = (string) $request->ip();
            return [
                Limit::perMinute(3)->by('otp-send:id:' . $identifier),
                Limit::perHour(10)->by('otp-send:id:' . $identifier),
                Limit::perMinute(20)->by('otp-send:ip:' . $ip),
            ];
        });

        // OTP verification — cheap to call but the actual brute-force
        // cap lives on the otps row (MAX_ATTEMPTS). This limiter is
        // there to stop attackers from cycling identifiers.
        RateLimiter::for('otp-verify', function (Request $request) {
            $identifier = strtolower((string) $request->input('identifier', session('otp_identifier', '')));
            $ip         = (string) $request->ip();
            return [
                Limit::perMinute(8)->by('otp-verify:id:' . $identifier),
                Limit::perMinute(30)->by('otp-verify:ip:' . $ip),
            ];
        });

        // Password-credential login (mobile API).
        RateLimiter::for('auth-credentials', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));
            $ip    = (string) $request->ip();
            return [
                Limit::perMinute(5)->by('auth-cred:id:' . $email),
                Limit::perMinute(20)->by('auth-cred:ip:' . $ip),
            ];
        });

        // Account creation — rare per-person, common from spam farms.
        RateLimiter::for('auth-register', function (Request $request) {
            $ip = (string) $request->ip();
            return [
                Limit::perMinute(3)->by('auth-register:ip:' . $ip),
                Limit::perHour(20)->by('auth-register:ip:' . $ip),
            ];
        });
    }
}
