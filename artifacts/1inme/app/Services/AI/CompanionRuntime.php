<?php

namespace App\Services\AI;

use App\Modules\Common\Models\AiCompanionConversation;
use App\Modules\Common\Models\AiCompanionMessage;
use App\Modules\User\Models\AiCompanion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Runs one Companion turn end-to-end:
 *   1. Resolves / creates the visitor conversation (race-safe).
 *   2. Enforces visitor-side rate limit + monthly hard cap.
 *   3. Replays the last N messages as PersonaRuntime history.
 *   4. Calls PersonaRuntime::turn() (which itself meters credits via
 *      OpenAiService against the *companion owner's* balance).
 *   5. Persists user + assistant rows and updates conversation
 *      counters atomically.
 *
 * Returns a serializable payload the public POST endpoint can echo
 * straight to the embed bundle.
 */
class CompanionRuntime
{
    /** History window we replay back to the model (excluding the new turn). */
    public const HISTORY_TURNS = 14;

    public function __construct(
        protected PersonaRuntime $persona,
        protected AiUsageCharger $credits,
    ) {}

    /**
     * @return array{
     *   ok:bool,
     *   conversation_id?:int,
     *   message_id?:int,
     *   answer?:string,
     *   citations?:array,
     *   credits_spent?:int,
     *   error?:string,
     *   retry_after?:int,
     * }
     */
    public function turn(
        AiCompanion $companion,
        string $visitorToken,
        string $message,
        array $visitorMeta = [],
    ): array {
        $caps = CompanionSettings::caps();

        if ($companion->is_disabled) {
            return ['ok' => false, 'error' => 'This chatbot is disabled.'];
        }
        if ($companion->persona->is_disabled) {
            return ['ok' => false, 'error' => 'This chatbot is unavailable right now.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'Message is required.'];
        }
        $maxLen = max(1, (int) $caps['max_visitor_message_chars']);
        if (mb_strlen($message) > $maxLen) {
            $message = mb_substr($message, 0, $maxLen) . '…';
        }

        // Visitor-side rate limit (per companion + visitor token, per
        // minute). Cache backed so it survives across requests without
        // a DB write per check.
        $rl = max(1, (int) $caps['visitor_rate_per_minute']);
        $rlKey = "companion-rl:{$companion->id}:" . sha1($visitorToken . '|' . ($visitorMeta['ip'] ?? ''));
        $hits = (int) Cache::get($rlKey, 0);
        if ($hits >= $rl) {
            return ['ok' => false, 'error' => 'You\'re sending messages too fast — please wait a moment.', 'retry_after' => 60];
        }
        Cache::put($rlKey, $hits + 1, now()->addMinute());

        // Resolve conversation (race-safe: the visitor token may not be
        // unique by index because it's only globally unique within a
        // companion — keeping this DB-level cheap).
        $conv = AiCompanionConversation::firstOrCreate(
            ['companion_id' => $companion->id, 'visitor_token' => $visitorToken],
            [
                'visitor_name'  => Str::limit((string) ($visitorMeta['name'] ?? ''), 120, ''),
                'visitor_email' => Str::limit((string) ($visitorMeta['email'] ?? ''), 200, ''),
                'visitor_ip'    => Str::limit((string) ($visitorMeta['ip'] ?? ''), 64, ''),
                'visitor_ua'    => Str::limit((string) ($visitorMeta['ua'] ?? ''), 255, ''),
                'source_origin' => Str::limit((string) ($visitorMeta['origin'] ?? ''), 200, ''),
            ]
        );

        // Hard monthly cap (per companion). Cheap COUNT bounded by
        // index — we don't track this in a counter so a backfill from
        // raw rows is always authoritative.
        $monthStart = now()->startOfMonth();
        $monthlyTurns = (int) AiCompanionMessage::where('role', 'user')
            ->whereIn('conversation_id', AiCompanionConversation::where('companion_id', $companion->id)->pluck('id'))
            ->where('created_at', '>=', $monthStart)
            ->count();
        $perCompanionCap = (int) $companion->hard_cap_per_month;
        $platformCap     = (int) $caps['platform_hard_cap_per_month'];
        $effectiveCap = $perCompanionCap > 0 ? $perCompanionCap : $platformCap;
        if ($platformCap > 0) $effectiveCap = min($effectiveCap, $platformCap);
        if ($effectiveCap > 0 && $monthlyTurns >= $effectiveCap) {
            return ['ok' => false, 'error' => 'This chatbot reached its monthly limit. Please try again later.'];
        }

        // Replay last HISTORY_TURNS as PersonaRuntime expects. We pass
        // role/content pairs only — citations are output, not input.
        $historyRows = $conv->messages()
            ->orderByDesc('id')
            ->limit(self::HISTORY_TURNS)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $history = $historyRows
            ->map(fn ($m) => [
                'role'    => $m->role === 'assistant' ? 'assistant' : 'user',
                'content' => (string) $m->content,
            ])
            ->all();

        try {
            $result = $this->persona->turn(
                $companion->user,
                $companion->persona,
                $history,
                $message,
            );
        } catch (InsufficientCoinsForAiException $e) {
            return ['ok' => false, 'error' => 'This chatbot is out of coins. The owner has been notified.'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'error' => 'The assistant could not respond right now. Please try again.'];
        }

        // ── Free monthly turns ──────────────────────────────────────────
        // PersonaRuntime always charges credits via OpenAiService. To
        // honour the per-companion `free_turns_per_month` quota we
        // refund the spend back to the owner when the current turn
        // falls within the free window. The persisted message records
        // 0 credits so analytics reflect the user-visible cost.
        $rawSpend  = (int) ($result['credits_spent'] ?? 0);
        $freeQuota = (int) $companion->free_turns_per_month;
        // $monthlyTurns counted *before* this turn was inserted, so
        // the current turn is index $monthlyTurns (zero-based).
        $isFree    = $rawSpend > 0 && $monthlyTurns < $freeQuota;
        $effectiveSpend = $isFree ? 0 : $rawSpend;
        if ($isFree) {
            try {
                $this->credits->refund($companion->user, $rawSpend, [
                    'reason'  => 'companion_free_turn',
                    'meta'    => [
                        'companion_id'   => $companion->id,
                        'conversation_id'=> $conv->id,
                    ],
                ]);
            } catch (\Throwable $e) {
                report($e);
                // If the refund fails, fall back to the real spend so
                // the user is at least billed correctly — better than
                // double-counting.
                $effectiveSpend = $rawSpend;
            }
        }

        $userMsg = null;
        $aiMsg   = null;
        DB::transaction(function () use ($conv, $companion, $message, $result, $effectiveSpend, &$userMsg, &$aiMsg) {
            $userMsg = AiCompanionMessage::create([
                'conversation_id' => $conv->id,
                'role'            => 'user',
                'content'         => $message,
                'credits_spent'   => 0,
            ]);
            $aiMsg = AiCompanionMessage::create([
                'conversation_id' => $conv->id,
                'role'            => 'assistant',
                'content'         => $result['answer'],
                'citations'       => $result['citations'] ?? [],
                'credits_spent'   => $effectiveSpend,
            ]);
            $conv->turns_count     = (int) $conv->turns_count + 1;
            $conv->credits_spent   = (int) $conv->credits_spent + $effectiveSpend;
            $conv->last_message_at = now();
            $conv->save();

            $companion->forceFill(['last_used_at' => now()])->save();
        });

        return [
            'ok'              => true,
            'conversation_id' => (int) $conv->id,
            'message_id'      => (int) ($aiMsg->id ?? 0),
            'answer'          => (string) $result['answer'],
            'citations'       => array_map(
                fn ($c) => ['title' => $c['title'] ?? '', 'type' => $c['type'] ?? ''],
                (array) ($result['citations'] ?? []),
            ),
            'credits_spent'   => $effectiveSpend,
            'free_turn'       => $isFree,
        ];
    }

    /**
     * HMAC-signed iframe handshake token. Issued by the iframe
     * controller after it has validated `Referer` against the
     * companion's allow-list, then echoed back by the iframe's JS on
     * each /message POST so the endpoint can trust the request even
     * though the browser-supplied Origin is the Sayzio server itself.
     *
     * Token shape: base64url("{exp}.{originHost}.{publicId}") + "." + sig
     * where sig = HMAC-SHA256(payload, app.key).
     */
    public static function issueIframeToken(AiCompanion $companion, string $originHost, int $ttl = 1800): string
    {
        $exp = time() + max(60, $ttl);
        $payload = base64_encode($exp . '.' . $originHost . '.' . $companion->public_id);
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, (string) config('app.key'));
        return $payload . '.' . $sig;
    }

    /**
     * Verifies a token issued by issueIframeToken(). Returns the
     * embedding origin host on success, null on tampering / expiry /
     * mismatch with the companion's allow-list.
     */
    public static function verifyIframeToken(AiCompanion $companion, string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) return null;
        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, (string) config('app.key'));
        if (!hash_equals($expected, $sig)) return null;
        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        if (!$decoded) return null;
        $bits = explode('.', $decoded, 3);
        if (count($bits) !== 3) return null;
        [$exp, $originHost, $publicId] = $bits;
        if ((int) $exp < time()) return null;
        if ($publicId !== $companion->public_id) return null;
        if (!$companion->originAllowed('https://' . $originHost)) return null;
        return $originHost;
    }

    /** Returns the per-month usage row for the admin / owner stats panels. */
    public static function monthlyUsage(AiCompanion $companion): array
    {
        $monthStart = now()->startOfMonth();
        $convIds = AiCompanionConversation::where('companion_id', $companion->id)->pluck('id');
        $turns = (int) AiCompanionMessage::where('role', 'user')
            ->whereIn('conversation_id', $convIds)
            ->where('created_at', '>=', $monthStart)
            ->count();
        $credits = (int) AiCompanionMessage::whereIn('conversation_id', $convIds)
            ->where('created_at', '>=', $monthStart)
            ->sum('credits_spent');
        return ['turns' => $turns, 'credits' => $credits];
    }
}
