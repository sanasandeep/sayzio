<?php

namespace App\Providers;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\GoogleCalendarProvider;
use App\Services\PlanRecommender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // `php artisan serve` spawns a child `php -S` process to handle requests
        // and, by default, only forwards a small allowlist of env vars to it
        // (ServeCommand::$passthroughVariables) — every other $_ENV var is
        // explicitly unset in the child. Our DB credentials (notably the secret
        // DB_PASSWORD, which is injected via the Replit secrets manager rather
        // than .env) would therefore be missing in the child, causing
        // "fe_sendauth: no password supplied". Forward the DB_* variables so the
        // request-handling process can connect to the (external AWS RDS) Postgres.
        if (class_exists(\Illuminate\Foundation\Console\ServeCommand::class)) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
                \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables,
                // DB credentials (DB_PASSWORD etc.) are injected via the Replit
                // secrets manager, not .env, so the child must inherit them.
                ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SSLMODE'],
                // WhatsApp Cloud API delivery credentials are likewise Replit
                // secrets. Without forwarding them the child process reads them
                // as absent and OtpService::sendWhatsApp() silently stays in
                // "preview mode" (logs the code) even after they're configured.
                ['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_ACCESS_TOKEN', 'WHATSAPP_TEMPLATE_NAME', 'WHATSAPP_TEMPLATE_LANG', 'WHATSAPP_GRAPH_VERSION'],
                // S3/CloudFront user-content storage. The AWS keys are Replit
                // secrets and the rest is shared config; without forwarding them
                // the child serves with the user-content disks falling back to
                // local (USER_CONTENT_DISK absent) and no S3 credentials.
                ['USER_CONTENT_DISK', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT', 'AWS_USE_PATH_STYLE_ENDPOINT'],
                // SMTP mail transport. MAIL_PASSWORD (and often the rest) is a
                // Replit secret; without forwarding it the child sends mail with
                // an empty password and SMTP auth fails. Admin-configured values
                // (app_settings) override these at runtime, but the env values
                // remain the fallback.
                ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_SCHEME', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_EHLO_DOMAIN'],
            )));
        }

        // Override the mail transport with the admin-configured SMTP settings
        // (Settings → Email / SMTP) so notifications, newsletters and email
        // OTP all use them without a redeploy. Falls back to env/config when
        // unset. Best-effort: never let a settings read break the whole boot.
        try {
            \App\Services\Integrations\MailSettings::applyRuntimeConfig();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MailSettings runtime override failed: ' . $e->getMessage());
        }

        \Illuminate\Support\Facades\View::composer(
            array_keys(\App\Modules\Common\Services\BlogCtaComposer::VIEW_TO_SLUG),
            \App\Modules\Common\Services\BlogCtaComposer::class
        );

        // Share the live branded global-domain list with the marketing home
        // section and /domains page so the showcase stays in sync with the
        // admin-managed domains automatically.
        \Illuminate\Support\Facades\View::composer(
            \App\Modules\Common\Services\GlobalDomainsComposer::VIEWS,
            \App\Modules\Common\Services\GlobalDomainsComposer::class
        );

        // Share the login-method policy (mobile/WhatsApp login enabled +
        // allowed country codes) with the public auth modal so it can hide
        // the Mobile tab when admins have turned mobile login off, matching
        // the dedicated login page.
        \Illuminate\Support\Facades\View::composer(
            \App\Modules\Common\Services\AuthModalComposer::VIEW,
            \App\Modules\Common\Services\AuthModalComposer::class
        );

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
        $this->bustPlanRecommenderCacheOnUsageChange();
        $this->alertOnFailedBackgroundJobs();
    }

    /**
     * Page the team when a queued background job exhausts its retries and
     * lands in the failed_jobs table. Many of our scheduled tasks fan work
     * out onto the queue (contact imports, AI ingestion, newsletters, push
     * delivery, …); a permanent failure there is silent otherwise. One
     * central hook covers every job, fires only on terminal failure (not
     * each retry), and is wholly best-effort so it can never break the
     * worker or mask the original job error.
     */
    protected function alertOnFailedBackgroundJobs(): void
    {
        \Illuminate\Support\Facades\Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event) {
            try {
                app(\App\Modules\Common\Services\NotificationService::class)->systemAlert(
                    'Background job failed',
                    'A queued background job exhausted its retries and was moved to the failed jobs table.',
                    'error',
                    [
                        'job'        => $event->job->resolveName(),
                        'connection' => $event->connectionName,
                        'queue'      => $event->job->getQueue() ?: 'default',
                        'error'      => \Illuminate\Support\Str::limit($event->exception->getMessage(), 300),
                    ],
                    \App\Services\Integrations\IntegrationKeySettings::ALERT_CATEGORY_JOB,
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to dispatch job-failure alert: ' . $e->getMessage());
            }
        });
    }

    /**
     * Keep the per-user usage cache backing PlanRecommender (used on
     * /pricing and /user/upgrade) in sync with reality. The cache has a
     * short TTL as a safety net, but for the user who *just* created a
     * link / file / etc. we want the gauges in the upgrade banner to
     * reflect the new value on the very next request — not 90s later.
     *
     * We watch created/updated/deleted: created/deleted are the obvious
     * cases, and `updated` matters because Link.type can flip between
     * "short" and "biolink" (which changes max_biolinks even though the
     * row count is unchanged) and UserFile.size_bytes can change the
     * storage_limit_mb gauge. Mass-update / DB::table writes bypass
     * model events; the TTL covers those cases.
     */
    protected function bustPlanRecommenderCacheOnUsageChange(): void
    {
        $forget = function (Model $row) {
            // After an update of user_id (rare) we'd want to bust both
            // sides; getOriginal() gives us the pre-update value.
            $current  = (int) ($row->getAttribute('user_id') ?? 0);
            $previous = (int) ($row->getOriginal('user_id') ?? $current);
            foreach (array_unique([$current, $previous]) as $id) {
                if ($id > 0) {
                    PlanRecommender::forgetUsage($id);
                }
            }
        };

        foreach ([Link::class, Contact::class, UserFile::class, Project::class] as $modelClass) {
            $modelClass::created($forget);
            $modelClass::updated($forget);
            $modelClass::deleted($forget);
        }
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
