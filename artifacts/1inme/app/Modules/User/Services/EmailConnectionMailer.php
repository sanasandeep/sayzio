<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\IntegrationConfig;
use Illuminate\Support\Facades\Mail;

/**
 * Shared "send through a user's saved email connection" helper.
 *
 * A connection is an email-kind {@see IntegrationConfig} row. Any feature that
 * sends email on a user's behalf (form notifications, autoresponders,
 * subscriber broadcasts, …) can route a send through the connection the user
 * picked, with a single consistent safety rule: whenever the chosen connection
 * is missing, inactive, half-configured, or uses a provider whose transport
 * isn't wired yet, the send falls back to the platform mailer instead of being
 * dropped — and the fallback is logged so the owner can diagnose it.
 *
 * Only SMTP-shaped providers (smtp, sendgrid) are wired today; both go out
 * through the Symfony SMTP transport via a runtime-registered mailer that the
 * central Emailer pipeline picks up (see Emailer's `mailer`/`mailer_config`
 * opts). The email_logs row gets meta.transport = "connection:{id}" so tests
 * and the email-history UI can prove which transport actually carried a send.
 */
class EmailConnectionMailer
{
    /** Providers whose transport is wired end-to-end today. */
    public const WIRED_PROVIDERS = ['smtp', 'sendgrid'];

    /**
     * Resolve a user's connection by id: owned, email kind, active. The
     * workspace global scope is bypassed on purpose — sends triggered by
     * public visitors (form submissions) have no workspace bound, and a
     * connection is account-level, not workspace-level.
     */
    public static function resolve(int $userId, ?int $configId): ?IntegrationConfig
    {
        if (!$configId) return null;

        return IntegrationConfig::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $userId)
            ->where('id', $configId)
            ->kind('email')
            ->active()
            ->first();
    }

    /**
     * Runtime mail.mailers.* config for a connection, or null when the
     * provider isn't wired or the connection is half-configured (e.g. an SMTP
     * connection without a host / password).
     */
    public static function mailerConfig(IntegrationConfig $config): ?array
    {
        $cred = (array) $config->credentials;
        $meta = (array) $config->meta;

        switch ($config->provider) {
            case 'smtp':
                $host     = trim((string) ($meta['host'] ?? ''));
                $password = (string) ($cred['password'] ?? '');
                if ($host === '' || $password === '') return null;

                return [
                    'transport'  => 'smtp',
                    'host'       => $host,
                    'port'       => (int) ($meta['port'] ?? 587),
                    'encryption' => $meta['encryption'] ?? null,
                    'username'   => $meta['username'] ?? null,
                    'password'   => $password,
                    'timeout'    => 10,
                ];
            case 'sendgrid':
                $apiKey = (string) ($cred['api_key'] ?? '');
                if ($apiKey === '') return null;

                return [
                    'transport'  => 'smtp',
                    'host'       => 'smtp.sendgrid.net',
                    'port'       => 587,
                    'encryption' => 'tls',
                    'username'   => 'apikey',
                    'password'   => $apiKey,
                    'timeout'    => 10,
                ];
            default:
                return null;
        }
    }

    /** A request-unique mailer name so each connection's config can't collide. */
    public static function mailerName(IntegrationConfig $config): string
    {
        return 'integ_' . $config->id;
    }

    /**
     * Options the Emailer pipeline merges into a send so the message goes out
     * through the given user's chosen connection. Empty array = platform
     * fallback (no connection chosen, or the chosen one is unusable — the
     * latter is logged so the send never silently drops).
     *
     * @return array<string,mixed>
     */
    public static function emailOpts(int $userId, ?int $configId): array
    {
        if (!$configId) return [];

        $config = self::resolve($userId, $configId);
        if (!$config) {
            logger()->warning("Email connection #{$configId} not found / inactive for user #{$userId}; falling back to platform mailer.");
            return [];
        }

        $mailerConfig = self::mailerConfig($config);
        if (!$mailerConfig) {
            logger()->warning("Email connection #{$config->id} (provider '{$config->provider}') is not usable (unwired provider or incomplete settings); falling back to platform mailer.");
            return [];
        }

        $meta = (array) $config->meta;
        $opts = [
            'mailer'          => self::mailerName($config),
            'mailer_config'   => $mailerConfig,
            'transport_label' => 'connection:' . $config->id,
        ];

        $fromEmail = $meta['from_email'] ?? null;
        if ($fromEmail) {
            $opts['from'] = ['address' => $fromEmail, 'name' => $meta['from_name'] ?? ''];
        }

        return $opts;
    }

    /**
     * Send a registry email through the user's chosen connection (platform
     * fallback when none / unusable). $opts are the usual Emailer opts
     * (subject, body, format, reply_to, user, throw_on_failure, …).
     *
     * @param array<int,string> $to
     */
    public static function send(string $emailKey, int $userId, ?int $configId, array $to, array $opts = []): void
    {
        $to = array_values(array_filter($to, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if (empty($to)) return;

        $connOpts = self::emailOpts($userId, $configId);
        $opts = array_merge($opts, $connOpts);

        try {
            foreach ($to as $recipient) {
                \App\Modules\Common\Services\Emailer::send($emailKey, $recipient, [], $opts);
            }
        } finally {
            if (!empty($connOpts['mailer'])) {
                self::purge($connOpts['mailer']);
            }
        }
    }

    /**
     * Send a sample message through a connection and report the result. Used
     * by the SMTP Connections page's "send test email" action.
     *
     * @return array{ok:bool,error:?string}
     */
    public static function sendTest(IntegrationConfig $config, string $to): array
    {
        $mailerConfig = self::mailerConfig($config);
        if (!$mailerConfig) {
            return ['ok' => false, 'error' => "This connection's provider isn't wired for sending yet, or the connection is missing its host / password. Edit it and fill in the missing fields."];
        }

        $name = self::mailerName($config);
        config(["mail.mailers.{$name}" => $mailerConfig]);

        $meta      = (array) $config->meta;
        $fromEmail = $meta['from_email'] ?? null;
        $fromName  = $meta['from_name'] ?? null;
        $label     = $config->name;

        try {
            Mail::purge($name);
            Mail::mailer($name)->raw(
                "This is a test message confirming your email connection \"{$label}\" is working.\n\n"
                . 'Emails you route through this connection will be delivered from this server.',
                function ($m) use ($to, $fromEmail, $fromName, $label) {
                    $m->to($to)->subject("Test email — {$label}");
                    if ($fromEmail) {
                        $m->from($fromEmail, $fromName ?: null);
                    }
                }
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } finally {
            self::purge($name);
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Drop a runtime-registered mailer config so the next request / queue job
     * in a long-running worker never sees leaked credentials.
     */
    public static function purge(string $mailerName): void
    {
        $mailers = (array) config('mail.mailers');
        unset($mailers[$mailerName]);
        config(['mail.mailers' => $mailers]);
        try { app('mail.manager')->forgetMailers(); } catch (\Throwable $e) { /* older Laravel */ }
    }
}
