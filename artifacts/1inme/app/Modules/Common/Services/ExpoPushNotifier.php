<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\DevicePushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin transport over the Expo Push API (task #1403).
 *
 * Expo exposes a single fan-out endpoint that accepts a batch of up to
 * 100 messages and proxies them on to APNs / FCM. We only need the
 * happy path plus pruning of tokens Expo reports as permanently dead
 * (`DeviceNotRegistered`) so a user who reinstalls or revokes
 * notifications doesn't accumulate stale rows forever.
 *
 * Delivery is wholly best-effort: a network hiccup or malformed token
 * must never bubble up into the request that triggered the notification
 * (e.g. a metered API call). Callers should treat a thrown exception as
 * "push didn't go out" and carry on.
 */
class ExpoPushNotifier
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    /** Expo rejects anything that isn't an ExponentPushToken / ExpoPushToken. */
    public static function looksLikeExpoToken(string $token): bool
    {
        return (bool) preg_match('/^Expo(nent)?PushToken\[.+\]$/', trim($token));
    }

    /**
     * Push a single notification to every registered device of $userId,
     * honoring Expo's 100-message batch ceiling and pruning dead tokens.
     *
     * @param array<string, mixed> $data Arbitrary JSON payload delivered with the push.
     * @return int Number of messages accepted by Expo (best-effort estimate).
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): int
    {
        $rows = DevicePushToken::where('user_id', $userId)->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $tokens = $rows
            ->pluck('token')
            ->filter(fn ($t) => is_string($t) && self::looksLikeExpoToken($t))
            ->values();

        if ($tokens->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach ($tokens->chunk(100) as $chunk) {
            $messages = $chunk->map(fn ($token) => [
                'to'       => $token,
                'title'    => $title,
                'body'     => $body,
                'sound'    => 'default',
                'priority' => 'high',
                'data'     => $data,
            ])->values()->all();

            try {
                $resp = Http::asJson()
                    ->acceptJson()
                    ->timeout(8)
                    ->post(self::ENDPOINT, $messages);

                if (!$resp->successful()) {
                    Log::warning('Expo push request failed', [
                        'status' => $resp->status(),
                        'body'   => $resp->body(),
                    ]);
                    continue;
                }

                $tickets = (array) ($resp->json('data') ?? []);
                $this->pruneDeadTokens($chunk->values()->all(), $tickets);
                $sent += count(array_filter(
                    $tickets,
                    fn ($t) => ($t['status'] ?? null) === 'ok',
                ));
            } catch (\Throwable $e) {
                Log::warning('Expo push delivery threw: ' . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Delete tokens Expo reports as permanently unreachable. Tickets line
     * up positionally with the tokens we submitted in this chunk.
     *
     * @param array<int, string>               $tokens
     * @param array<int, array<string, mixed>> $tickets
     */
    private function pruneDeadTokens(array $tokens, array $tickets): void
    {
        $dead = [];
        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }
            $reason = $ticket['details']['error'] ?? null;
            if ($reason === 'DeviceNotRegistered' && isset($tokens[$i])) {
                $dead[] = $tokens[$i];
            }
        }

        if ($dead) {
            DevicePushToken::whereIn('token', $dead)->delete();
        }
    }
}
