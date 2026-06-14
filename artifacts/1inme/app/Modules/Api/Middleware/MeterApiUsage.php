<?php

namespace App\Modules\Api\Middleware;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\ApiUsageCounter;
use App\Modules\User\Models\User;
use App\Services\Billing\InsufficientCoinsException;
use App\Services\Billing\WalletService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Monthly API-call metering for developer API keys (task #1393).
 *
 * Runs inside the authenticated `/api/v1` group, AFTER auth:sanctum.
 *
 * IMPORTANT: this only meters calls made with a developer **API key**
 * (personal access token stamped `client_kind = 'api_key'`). First-party
 * session tokens minted for the web/mobile apps authenticate through the
 * very same `auth:sanctum` guard, so metering every authenticated request
 * would wrongly burn ordinary app users' coins. Those are skipped.
 *
 * Each metered call:
 *   - is counted against the plan's monthly included allowance
 *     (`api_calls_monthly`; -1 / bypass = unlimited);
 *   - once the allowance is used up, is paid for with coins at the
 *     admin-set overage rate (1 coin buys N calls; the unused remainder
 *     of a purchased block is carried forward in the counter row);
 *   - is rejected with HTTP 402 + the unified {error} envelope when the
 *     allowance is exhausted and the wallet can't cover the overage.
 *
 * The counter row is locked first, then WalletService takes the wallet
 * lock — a consistent lock order across requests so concurrent calls
 * can't deadlock.
 */
class MeterApiUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // Only meter developer API-key tokens, never first-party app sessions.
        try {
            $token = $user->currentAccessToken();
        } catch (\Throwable) {
            $token = null;
        }
        if (!$token instanceof Model || ($token->client_kind ?? null) !== 'api_key') {
            return $next($request);
        }

        // Enforce plan-gated API access at the request path, not just at key
        // creation time. A user who downgrades to a plan without `api_access`
        // keeps any previously-minted keys, so we must reject their calls here
        // (otherwise they retain effective API access — and could even spend
        // coins on overage — after losing the feature).
        if (!$user->planFeatureEnabled('api_access')) {
            return response()->json([
                'error' => [
                    'message' => 'API access is not included in your current plan. Upgrade to a plan with API access to use API keys.',
                    'code'    => 'api_access_disabled',
                ],
            ], 403);
        }

        // Allowance: -1 (or bypass → PHP_INT_MAX) means unlimited.
        $allowance = (int) $user->getPlanFeature('api_calls_monthly', 0);
        $unlimited = $allowance < 0 || $allowance === PHP_INT_MAX;

        // Per-user near-limit warning threshold (percent). Developers can
        // pick an earlier heads-up (e.g. 50%) or a later/quieter one from
        // their notification preferences. Falls back to 80% when unset, and
        // is clamped to a sane range so the 100% alert stays unconditional.
        $warnThresholdPct = (int) ($user->api_usage_warning_threshold ?? 80);
        if ($warnThresholdPct < 1 || $warnThresholdPct > 99) {
            $warnThresholdPct = 80;
        }

        $callsPerCoin = WalletService::apiOverageCallsPerCoin();
        $wallet = app(WalletService::class);

        // Notification intents collected under the row lock (so dedup
        // flags are stamped atomically) but delivered AFTER the
        // transaction commits — emails must never run inside the lock.
        $notes = [];

        try {
            $denied = DB::transaction(function () use ($user, $allowance, $unlimited, $callsPerCoin, $wallet, &$notes) {
                $period = ApiUsageCounter::currentPeriod();

                // Lock (or create) the period counter row first.
                $counter = ApiUsageCounter::where('user_id', $user->id)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();

                if (!$counter) {
                    ApiUsageCounter::create([
                        'user_id'                   => $user->id,
                        'period'                    => $period,
                        'calls_used'                => 0,
                        'overage_calls'             => 0,
                        'coins_spent'               => 0,
                        'prepaid_overage_remaining' => 0,
                    ]);
                    $counter = ApiUsageCounter::where('user_id', $user->id)
                        ->where('period', $period)
                        ->lockForUpdate()
                        ->first();
                }

                // Within the included allowance (or unlimited) — free call.
                if ($unlimited || ($counter->calls_used + 1) <= $allowance) {
                    $counter->forceFill(['calls_used' => $counter->calls_used + 1])->save();
                    if (!$unlimited) {
                        $this->stampThresholdWarnings($counter, $allowance, $callsPerCoin, $warnThresholdPct, $notes);
                    }
                    return null;
                }

                // Overage. Consume a prepaid call if one is banked.
                if ($counter->prepaid_overage_remaining > 0) {
                    $counter->forceFill([
                        'calls_used'                => $counter->calls_used + 1,
                        'overage_calls'             => $counter->overage_calls + 1,
                        'prepaid_overage_remaining' => $counter->prepaid_overage_remaining - 1,
                    ])->save();
                    return null;
                }

                // Need to buy a fresh overage block with 1 coin.
                if (!WalletService::isEnabled()) {
                    $this->stampOverageBlocked($counter, $allowance, 'wallet_disabled', $notes);
                    return $this->limitDenial($allowance);
                }

                try {
                    $wallet->debit($user, 1, [
                        'reason' => 'API overage — ' . $callsPerCoin . ' calls',
                        'meta'   => ['kind' => 'api_overage', 'period' => $period, 'calls_per_coin' => $callsPerCoin],
                    ]);
                } catch (InsufficientCoinsException) {
                    $this->stampOverageBlocked($counter, $allowance, 'insufficient_coins', $notes);
                    return $this->coinDenial($allowance, $callsPerCoin);
                }

                // One coin bought `callsPerCoin` calls; this request uses one,
                // the rest is banked for subsequent calls this period.
                $counter->forceFill([
                    'calls_used'                => $counter->calls_used + 1,
                    'overage_calls'             => $counter->overage_calls + 1,
                    'coins_spent'               => $counter->coins_spent + 1,
                    'prepaid_overage_remaining' => max(0, $callsPerCoin - 1),
                ])->save();
                return null;
            });
        } catch (\Throwable $e) {
            // Never let a metering fault take down the API; fail open.
            report($e);
            return $next($request);
        }

        // Deliver any queued warnings outside the transaction/lock. Best
        // effort — a delivery failure must never break the API call.
        if (!empty($notes)) {
            $this->deliverWarnings($user, $notes);
        }

        if (is_array($denied)) {
            return response()->json(
                ['error' => $denied['error']],
                $denied['status']
            );
        }

        return $next($request);
    }

    /**
     * Queue (at most one of) the near-limit / 100% allowance warnings,
     * stamping the matching dedup column on the counter under the row lock
     * so each warning fires only once per period.
     *
     * The near-limit warning fires at the user's chosen percentage of the
     * allowance ($warnThresholdPct, default 80). The 100% warning is
     * unconditional.
     *
     * @param int $warnThresholdPct Near-limit warning percentage (1–99).
     * @param array<int, array{type:string, subject:string, body:string, data:array<string,mixed>}> $notes
     */
    private function stampThresholdWarnings(ApiUsageCounter $counter, int $allowance, int $callsPerCoin, int $warnThresholdPct, array &$notes): void
    {
        if ($allowance <= 0) {
            return; // No included allowance → percentage warnings are meaningless.
        }

        $used = (int) $counter->calls_used;

        // 100% — allowance fully consumed; subsequent calls draw on coin overage.
        if ($used >= $allowance && !$counter->warned_100_at) {
            $notes[] = [
                'type'    => 'api.usage_warning',
                'subject' => "You've used your full monthly API allowance",
                'body'    => "You've now used all " . number_format($allowance) . " API calls included in your plan this period."
                    . " Further calls are billed as overage from your coin balance (1 coin = " . number_format($callsPerCoin) . " calls)."
                    . " Top up coins or upgrade your plan to avoid rejected calls.",
                'data'    => [
                    'threshold'  => 100,
                    'period'     => $counter->period,
                    'allowance'  => $allowance,
                    'calls_used' => $used,
                ],
            ];
            // Stamp 100% and also disarm 80% (it's redundant once exhausted).
            $stamp = ['warned_100_at' => now()];
            if (!$counter->warned_80_at) {
                $stamp['warned_80_at'] = now();
            }
            $counter->forceFill($stamp)->save();
            return;
        }

        // Near-limit — crossed the user's chosen warning threshold but
        // still within allowance.
        $thresholdCalls = (int) ceil($allowance * ($warnThresholdPct / 100));
        if ($used >= $thresholdCalls && $used < $allowance && !$counter->warned_80_at) {
            $pct       = (int) round($used / $allowance * 100);
            $remaining = max(0, $allowance - $used);
            $notes[] = [
                'type'    => 'api.usage_warning',
                'subject' => "You're nearing your monthly API limit",
                'body'    => "You've used " . number_format($used) . " of your " . number_format($allowance)
                    . " included API calls this period ({$pct}%). " . number_format($remaining)
                    . " calls remain before overage billing kicks in. Consider upgrading your plan or topping up coins.",
                'data'    => [
                    'threshold'  => $warnThresholdPct,
                    'period'     => $counter->period,
                    'allowance'  => $allowance,
                    'calls_used' => $used,
                ],
            ];
            $counter->forceFill(['warned_80_at' => now()])->save();
        }
    }

    /**
     * Queue the "overage can no longer be covered → calls being rejected"
     * warning, once per period.
     *
     * @param 'wallet_disabled'|'insufficient_coins' $reason
     * @param array<int, array{type:string, subject:string, body:string, data:array<string,mixed>}> $notes
     */
    private function stampOverageBlocked(ApiUsageCounter $counter, int $allowance, string $reason, array &$notes): void
    {
        if ($counter->overage_unavailable_notified_at) {
            return;
        }

        $body = $reason === 'wallet_disabled'
            ? "You've exhausted your monthly API allowance and overage billing is currently unavailable, so additional API calls are being rejected. Upgrade your plan for a higher allowance."
            : "You've exhausted your monthly API allowance and your coin balance can no longer cover overage, so additional API calls are being rejected. Top up coins or upgrade your plan to restore access.";

        $notes[] = [
            'type'    => 'api.usage_warning',
            'subject' => 'Your API calls are being rejected',
            'body'    => $body,
            'data'    => [
                'threshold' => 'overage_blocked',
                'reason'    => $reason,
                'period'    => $counter->period,
                'allowance' => $allowance,
            ],
        ];
        $counter->forceFill(['overage_unavailable_notified_at' => now()])->save();
    }

    /**
     * Deliver queued warnings: an in-app row (preference-aware) plus an
     * email when the recipient hasn't muted the email channel. Wholly
     * best-effort.
     *
     * @param array<int, array{type:string, subject:string, body:string, data:array<string,mixed>}> $notes
     */
    private function deliverWarnings(User $user, array $notes): void
    {
        $notifications = app(NotificationService::class);

        foreach ($notes as $note) {
            try {
                $notifications->notify($user, $note['type'], array_merge($note['data'], [
                    'subject' => $note['subject'],
                    'body'    => $note['body'],
                    'message' => $note['body'],
                ]));

                if ($user->email && $notifications->prefersChannel($user->id, $note['type'], 'email')) {
                    $subject = $note['subject'];
                    $body    = $note['body'];
                    Mail::raw($body, function ($m) use ($user, $subject) {
                        $m->to($user->email)->subject($subject);
                    });
                }

                // Push to the 1inme-mobile app (task #1403). Preference-aware
                // and best-effort: pushToUser swallows transport failures so a
                // dead token can't break the metered call we're finishing.
                $notifications->pushToUser(
                    $user,
                    $note['type'],
                    $note['subject'],
                    $note['body'],
                    $note['data'],
                );
            } catch (\Throwable $e) {
                Log::warning('API usage warning delivery failed: ' . $e->getMessage(), ['user_id' => $user->id]);
            }
        }
    }

    /**
     * Allowance exhausted and overage purchasing is unavailable (wallet off).
     *
     * @return array{status:int, error:array<string,mixed>}
     */
    private function limitDenial(int $allowance): array
    {
        return [
            'status' => 402,
            'error'  => [
                'message' => 'Monthly API-call allowance exceeded. Upgrade your plan for a higher allowance.',
                'code'    => 'api_quota_exceeded',
                'details' => ['allowance' => $allowance],
            ],
        ];
    }

    /**
     * Allowance exhausted and the wallet can't cover the overage.
     *
     * @return array{status:int, error:array<string,mixed>}
     */
    private function coinDenial(int $allowance, int $callsPerCoin): array
    {
        return [
            'status' => 402,
            'error'  => [
                'message' => 'Monthly API-call allowance exceeded and not enough coins to cover overage. Top up coins or upgrade your plan.',
                'code'    => 'api_quota_exceeded',
                'details' => ['allowance' => $allowance, 'calls_per_coin' => $callsPerCoin],
            ],
        ];
    }
}
