<?php

namespace App\Providers;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Domain;
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

        // One click-write buffer per request: every track()/trackBlockClick()
        // call in a request shares it and a single PersistLinkClicksJob is
        // dispatched at request termination, keeping persistence off the hot path.
        $this->app->singleton(\App\Modules\Common\Services\ClickWriteBuffer::class);
    }

    public function boot(): void
    {
        // Behind Replit's TLS-terminating proxy the app receives plain HTTP
        // internally (the public request is HTTPS). Without this, Laravel's
        // url()/asset()/@vite generate absolute `http://` URLs on an `https://`
        // page, so browsers (Safari especially) block every asset as mixed
        // content — the live site loses its CSS, images and mascot. trustProxies
        // already yields the correct host; we only need to pin the scheme.
        // Production-only so local http dev is unaffected.
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

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
                // the child serves with no S3 credentials, so uploads fail
                // loudly (user-content disks are S3-only — there is no local
                // fallback to silently degrade to).
                ['USER_CONTENT_DISK', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'AWS_DEFAULT_REGION', 'AWS_BUCKET', 'AWS_URL', 'AWS_ENDPOINT', 'AWS_USE_PATH_STYLE_ENDPOINT'],
                // SMTP mail transport. MAIL_PASSWORD (and often the rest) is a
                // Replit secret; without forwarding it the child sends mail with
                // an empty password and SMTP auth fails. Admin-configured values
                // (app_settings) override these at runtime, but the env values
                // remain the fallback.
                ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_SCHEME', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_EHLO_DOMAIN'],
                // Env-only platform services now editable from the admin
                // Integrations hub. These remain the fallback when no admin
                // value is stored, so the child must still inherit them.
                ['GOOGLE_PLACES_API_KEY', 'TRUSTPILOT_API_KEY', 'GOOGLE_CONTACTS_CLIENT_ID', 'GOOGLE_CONTACTS_CLIENT_SECRET'],
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

        // Override the env-only platform services (Google Places / Trustpilot
        // reviews keys, Google Contacts OAuth client, and the S3 user-content
        // storage backend) with their admin-configured values from the
        // Integrations hub, falling back to env/config when unset. Best-effort
        // so a settings read can never break the whole boot.
        try {
            \App\Services\Integrations\PlatformServiceSettings::applyRuntimeConfig();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PlatformServiceSettings runtime override failed: ' . $e->getMessage());
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

        // Share the authenticated user's overdue follow-ups count with the
        // sidebar Contacts nav entry and the Contacts "Quick add" card so a
        // red badge proactively pulls people into reminders that are due.
        \Illuminate\Support\Facades\View::composer(
            \App\Modules\Common\Services\ContactsFollowUpsBadgeComposer::VIEWS,
            \App\Modules\Common\Services\ContactsFollowUpsBadgeComposer::class
        );

        // Share the pending-lead count with the sidebar Leads nav entry so
        // the badge reflects the review queue without every controller
        // wiring it up (Task #3728).
        \Illuminate\Support\Facades\View::composer(
            \App\Modules\Common\Services\LeadsBadgeComposer::VIEWS,
            \App\Modules\Common\Services\LeadsBadgeComposer::class
        );

        // Feed 2-3 featured upcoming events to the reusable events-hero promo
        // band included from the marketing site layout, so pages showing the
        // band always have fresh data without each controller wiring it up.
        \Illuminate\Support\Facades\View::composer(
            \App\Modules\Common\Services\EventsHeroBandComposer::VIEW,
            \App\Modules\Common\Services\EventsHeroBandComposer::class
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
        $this->guardDestructiveSchemaCommands();
        $this->recordScheduledTaskRuns();
        $this->forwardClicksToConnectedAnalytics();
    }

    /**
     * Forward real-human click / block-click events to a creator's connected
     * Google Analytics 4 properties via the Measurement Protocol.
     *
     * Runs entirely off the click hot path: the listener only queues a job
     * (and only when the owner actually has a GA property connected), so the
     * click write itself is never blocked by GA availability or latency.
     */
    protected function forwardClicksToConnectedAnalytics(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\LinkClicked::class,
            function (\App\Events\LinkClicked $event): void {
                $userId = (int) ($event->link->user_id ?? 0);
                if ($userId <= 0) {
                    return;
                }
                \App\Jobs\ForwardAnalyticsEventJob::forUser($userId, [
                    'name'      => 'link_click',
                    'client_id' => $this->gaClientId($event->click),
                    'params'    => array_filter([
                        'link_alias'  => $event->click->alias,
                        'source'      => $event->click->source,
                        'country'     => $event->click->country_code,
                        'device_type' => $event->click->device_type,
                        'engagement_time_msec' => 1,
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\BlockClicked::class,
            function (\App\Events\BlockClicked $event): void {
                $userId = (int) ($event->link->user_id ?? 0);
                if ($userId <= 0) {
                    return;
                }
                \App\Jobs\ForwardAnalyticsEventJob::forUser($userId, [
                    'name'      => 'block_click',
                    'client_id' => $this->gaClientId($event->click),
                    'params'    => array_filter([
                        'link_alias'  => $event->click->alias,
                        'block_type'  => $event->block->type ?? null,
                        'destination' => $event->destinationUrl,
                        'country'     => $event->click->country_code,
                        'device_type' => $event->click->device_type,
                        'engagement_time_msec' => 1,
                    ], fn ($v) => $v !== null && $v !== ''),
                ]);
            }
        );
    }

    /** Stable-ish GA client id derived from the click's visitor fingerprint. */
    protected function gaClientId(\App\Modules\User\Models\LinkClick $click): string
    {
        $seed = ($click->ip_address ?? '') . '|' . ($click->user_agent ?? '');
        if (trim($seed) === '|') {
            return 'sayzio.' . \Illuminate\Support\Str::random(16);
        }
        return 'sayzio.' . substr(hash('sha256', $seed), 0, 20);
    }

    /**
     * Persist a "last actually ran" timestamp (and success/failure) for every
     * scheduled job as the scheduler runs it. Laravel keeps no built-in record
     * of when a scheduled event last *finished*, so the admin Cron Jobs page
     * could only ever show each job's *next* due time — which can't tell an
     * operator whether the server crontab is actually firing. Recording this
     * lets the page surface a "Last ran" column and flag a silently-dead
     * scheduler.
     *
     * One central pair of listeners covers every event keyed by its mutex name,
     * so jobs added to routes/console.php are picked up automatically with no
     * per-job wiring. Wholly best-effort (CronRunLog swallows write errors) so it
     * can never break a scheduled run.
     */
    protected function recordScheduledTaskRuns(): void
    {
        // Singleton so the DB recorder's open-run id survives from the
        // Starting listener to the Finished listener within one schedule:run.
        $this->app->singleton(\App\Modules\Admin\Support\ScheduledJobRunRecorder::class);

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskStarting::class,
            function (\Illuminate\Console\Events\ScheduledTaskStarting $event): void {
                // Durable per-run DB row (insert-on-start), completing on
                // Finished/Failed below. Best-effort like the cache log.
                app(\App\Modules\Admin\Support\ScheduledJobRunRecorder::class)->starting($event->task);
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskFinished::class,
            function (\Illuminate\Console\Events\ScheduledTaskFinished $event): void {
                $exit = $event->task->exitCode ?? null;
                // A command can report failure via a non-zero exit code without
                // throwing (Command::FAILURE); treat that as a failed run.
                $ok = $exit === null || (int) $exit === 0;

                app(\App\Modules\Admin\Support\CronRunLog::class)->record(
                    $event->task,
                    $ok,
                    $event->runtime ?? null,
                    $ok ? null : 'Exited with code ' . $exit,
                );

                app(\App\Modules\Admin\Support\ScheduledJobRunRecorder::class)->finished(
                    $event->task,
                    $ok,
                    $event->runtime ?? null,
                    $ok ? null : 'Exited with code ' . $exit,
                    $exit !== null ? (int) $exit : null,
                );
            }
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\ScheduledTaskFailed::class,
            function (\Illuminate\Console\Events\ScheduledTaskFailed $event): void {
                $message = \Illuminate\Support\Str::limit($event->exception->getMessage(), 300);

                app(\App\Modules\Admin\Support\CronRunLog::class)->record(
                    $event->task,
                    false,
                    null,
                    $message,
                );

                app(\App\Modules\Admin\Support\ScheduledJobRunRecorder::class)->finished(
                    $event->task,
                    false,
                    null,
                    $message,
                    null,
                );
            }
        );
    }

    /**
     * Block table-dropping / schema-wiping artisan commands from running against
     * the shared, live AWS RDS database.
     *
     * Every isolated dev/test environment and the deployed app all point their
     * `DB_*` credentials at the same distant RDS `postgres` database. A stray
     * `migrate:fresh` / `migrate:reset` / `db:wipe` from any of them would drop
     * every table and wipe production data for everyone. Schema-resetting work
     * belongs on the local Replit Postgres instead.
     *
     * The guard fires only in the console, only for the destructive commands,
     * and only when the active connection targets the shared RDS host. A
     * deliberate operator escape hatch (`ALLOW_DESTRUCTIVE_DB_COMMANDS=1`) keeps
     * the rare legitimate case possible. Plain `migrate` (additive) is never
     * affected.
     */
    protected function guardDestructiveSchemaCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Console\Events\CommandStarting::class,
            function (\Illuminate\Console\Events\CommandStarting $event): void {
                $destructive = [
                    'migrate:fresh',
                    'migrate:refresh',
                    'migrate:reset',
                    'migrate:rollback',
                    'db:wipe',
                    'schema:dump', // --prune drops tables; block the whole command to be safe
                ];

                if (! in_array($event->command, $destructive, true)) {
                    return;
                }

                if (filter_var(env('ALLOW_DESTRUCTIVE_DB_COMMANDS', false), FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }

                // Resolve the connection the command actually targets: a
                // destructive command can be aimed at a non-default connection
                // via `--database=`, and several of ours (the test connection
                // included) point at the same shared RDS host. Falling back to
                // the default connection when no option is given.
                $connection = config('database.default');
                try {
                    $optionConnection = $event->input?->getOption('database');
                    if (is_string($optionConnection) && $optionConnection !== '') {
                        $connection = $optionConnection;
                    }
                } catch (\Throwable $e) {
                    // Command does not define a --database option; keep default.
                }

                $host       = (string) config("database.connections.{$connection}.host");
                $database   = (string) config("database.connections.{$connection}.database");

                // The shared/live database is the AWS RDS instance. Local dev/test
                // schema resets against the Replit Postgres are unaffected.
                if (! str_contains($host, 'rds.amazonaws.com')) {
                    return;
                }

                $message = "Refusing to run `{$event->command}` against the shared live database "
                    . "({$database}@{$host}). This command DROPS tables/data and would wipe the "
                    . "production database that every environment shares. Point DB_* at the local "
                    . "Postgres for schema resets, or set ALLOW_DESTRUCTIVE_DB_COMMANDS=1 to override.";

                \Illuminate\Support\Facades\Log::error("::1inme:: BLOCKED destructive DB command — {$message}");

                throw new \RuntimeException($message);
            }
        );
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

        foreach ([Link::class, Contact::class, UserFile::class, Project::class, Domain::class] as $modelClass) {
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
