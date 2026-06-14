<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts internal system/team alerts to the admin-configured Slack and/or
 * Discord incoming webhooks. Wholly best-effort: every send is wrapped so
 * a dead webhook can never throw into a request that triggered the alert.
 *
 * This is intentionally separate from the Monolog "slack" logging channel
 * (config/logging.php) — that channel keeps handling framework log records
 * unchanged. This dispatcher is for application-level alerts (health/infra
 * notices, admin announcements) that admins opt into from the API Keys hub.
 */
class InternalAlertDispatcher
{
    /**
     * Send an alert to every configured + enabled webhook.
     *
     * @param array<string,scalar|null> $context optional key/value detail lines.
     * @return array{enabled:bool,channels:array<int,array{channel:string,ok:bool,status:?int,error:?string}>}
     */
    public static function send(string $title, string $message, string $level = 'info', array $context = []): array
    {
        $result = ['enabled' => IntegrationKeySettings::alertsEnabled(), 'channels' => []];

        if (!$result['enabled']) {
            return $result;
        }

        $slack = IntegrationKeySettings::slackWebhookUrl();
        if ($slack) {
            $result['channels'][] = self::postSlack($slack, $title, $message, $level, $context);
        }

        $discord = IntegrationKeySettings::discordWebhookUrl();
        if ($discord) {
            $result['channels'][] = self::postDiscord($discord, $title, $message, $level, $context);
        }

        return $result;
    }

    /**
     * Force-send to a specific webhook URL ignoring the enable toggle.
     * Used by the admin "Send test alert" action so an admin can verify a
     * freshly-pasted hook before enabling fan-out.
     *
     * @param array<string,scalar|null> $context
     * @return array{channel:string,ok:bool,status:?int,error:?string}
     */
    public static function sendTest(string $channel, string $url, string $title, string $message, array $context = []): array
    {
        return $channel === 'discord'
            ? self::postDiscord($url, $title, $message, 'info', $context)
            : self::postSlack($url, $title, $message, 'info', $context);
    }

    /**
     * @param array<string,scalar|null> $context
     * @return array{channel:string,ok:bool,status:?int,error:?string}
     */
    private static function postSlack(string $url, string $title, string $message, string $level, array $context): array
    {
        $lines = ["*{$title}*", $message];
        foreach ($context as $k => $v) {
            $lines[] = "• *{$k}:* {$v}";
        }
        $emoji = self::levelEmoji($level);
        $text = ($emoji ? $emoji . ' ' : '') . implode("\n", array_filter($lines, fn ($l) => $l !== ''));

        return self::post('slack', $url, ['text' => $text]);
    }

    /**
     * @param array<string,scalar|null> $context
     * @return array{channel:string,ok:bool,status:?int,error:?string}
     */
    private static function postDiscord(string $url, string $title, string $message, string $level, array $context): array
    {
        $lines = ["**{$title}**", $message];
        foreach ($context as $k => $v) {
            $lines[] = "• **{$k}:** {$v}";
        }
        $emoji = self::levelEmoji($level);
        $content = ($emoji ? $emoji . ' ' : '') . implode("\n", array_filter($lines, fn ($l) => $l !== ''));

        return self::post('discord', $url, ['content' => $content]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{channel:string,ok:bool,status:?int,error:?string}
     */
    private static function post(string $channel, string $url, array $payload): array
    {
        try {
            $resp = Http::asJson()->timeout(8)->post($url, $payload);
            if ($resp->failed()) {
                Log::warning("Internal alert {$channel} webhook failed: HTTP " . $resp->status() . ' ' . $resp->body());
                return ['channel' => $channel, 'ok' => false, 'status' => $resp->status(), 'error' => 'HTTP ' . $resp->status()];
            }
            return ['channel' => $channel, 'ok' => true, 'status' => $resp->status(), 'error' => null];
        } catch (\Throwable $e) {
            Log::warning("Internal alert {$channel} webhook threw: " . $e->getMessage());
            return ['channel' => $channel, 'ok' => false, 'status' => null, 'error' => $e->getMessage()];
        }
    }

    private static function levelEmoji(string $level): string
    {
        return match ($level) {
            'critical', 'error' => '🚨',
            'warning'           => '⚠️',
            'success'           => '✅',
            default             => 'ℹ️',
        };
    }
}
