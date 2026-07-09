<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Double-charge guard for one-tap, coin-charged AI actions.
 *
 * Two protections, both keyed on (feature, user, payload-hash) so different
 * inputs never collide:
 *
 *  1. Cooldown result cache — after a successful run, the result is cached
 *     for {@see FRESH_MINUTES}. A repeat run with the SAME inputs inside the
 *     window is served from cache with `cached: true` and no charge, so an
 *     impatient double-tap (or an accidental re-run minutes later) can't
 *     burn coins on a back-to-back identical generation.
 *
 *  2. In-flight lock — while a run is executing, a concurrent identical
 *     request is rejected with HTTP 429 (`in_progress`) instead of starting
 *     a second, parallel charge. Acquired via an atomic Cache::add and
 *     always released in a `finally`, with a TTL safety net in case the
 *     process dies mid-run.
 *
 * Mirrors the pattern first shipped for the audience-type estimate
 * ({@see AudienceTypeEstimationService::estimateIsFresh()}), generalised so
 * every one-tap paid AI button can reuse it.
 */
class AiActionCooldown
{
    /** Minutes a successful result stays "fresh" (re-served without charging). */
    public const FRESH_MINUTES = 10;

    /** In-flight lock TTL (seconds) — safety net if a run dies without releasing. */
    public const IN_FLIGHT_SECONDS = 180;

    /**
     * Deterministic cache key for one (feature, user, inputs) combination.
     * Callers must build $payload deterministically (fixed key order).
     */
    public static function key(string $feature, int $userId, array $payload): string
    {
        return 'ai_cooldown:' . $feature . ':' . $userId . ':' . sha1(json_encode($payload));
    }

    /**
     * The cached result of a recent identical run, or null when none/stale.
     *
     * @return array{result: array, generated_at: string}|null
     */
    public static function fresh(string $key): ?array
    {
        $hit = Cache::get($key);

        if (!is_array($hit) || !is_array($hit['result'] ?? null) || empty($hit['generated_at'])) {
            return null;
        }

        return $hit;
    }

    /** Cache a successful result for the cooldown window. */
    public static function remember(string $key, array $result, ?int $minutes = null): void
    {
        Cache::put($key, [
            'result'       => $result,
            'generated_at' => now()->toIso8601String(),
        ], now()->addMinutes($minutes ?? self::FRESH_MINUTES));
    }

    /**
     * Try to mark this run as in-flight. False means an identical request is
     * already executing — the caller should return 429 instead of charging.
     */
    public static function begin(string $key): bool
    {
        return Cache::add($key . ':inflight', 1, self::IN_FLIGHT_SECONDS);
    }

    /** Release the in-flight lock (call in `finally`). */
    public static function end(string $key): void
    {
        Cache::forget($key . ':inflight');
    }
}
