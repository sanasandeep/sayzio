<?php

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Services\Billing\WalletService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Retire the separate "AI credits" system: AI usage is now charged
 * directly from the integer coin wallet at call time. This one-time
 * migration:
 *
 *   1. Converts every user's leftover AI-credit balance back into wallet
 *      coins at the old wallet→credits exchange rate so no unspent value
 *      is lost. The conversion ROUNDS UP (a sub-coin remainder is granted
 *      in the user's favour rather than discarded), and the old balance is
 *      only zeroed AFTER the wallet adjustment confirms success, so a
 *      mid-migration failure can never destroy value. A stable per-user
 *      idempotency key makes the whole step re-run safe (no double-credit).
 *   2. Rewrites the stored per-model rates from credits-per-1k to
 *      coins-per-1k (renaming the keys and dividing by the old rate).
 *   3. Rewrites the stored voice prices from credits to coins.
 *   4. Deletes the retired exchange-rate, credit-pack and old voice-price
 *      settings.
 *
 * The ai_credit_balances / ai_credit_transactions tables are intentionally
 * kept (read-only history); only balances are zeroed.
 */
return new class extends Migration {
    public function up(): void
    {
        // Old default: 10 AI credits per 1 wallet coin. New coin rates are
        // ~1/10th of the old credit rates, so this exchange rate is also
        // what converts old per-1k credit rates into per-1k coin rates.
        $rate = 10;
        if (Schema::hasTable('app_settings')) {
            $stored = AppSetting::get('ai.wallet_to_credits_rate');
            if ($stored !== null) {
                $rate = max(1, (int) $stored);
            }
        }

        // 1) Convert leftover credit balances → wallet coins.
        if (Schema::hasTable('ai_credit_balances') && Schema::hasTable('wallets')) {
            $charger = app(WalletService::class);
            DB::table('ai_credit_balances')->where('balance', '>', 0)
                ->orderBy('id')
                ->each(function ($row) use ($rate, $charger) {
                    $user = User::find($row->user_id);
                    if (!$user) {
                        // Orphaned balance row (no matching user): there is
                        // no account to credit, so leave it untouched as
                        // historical data rather than destroying it.
                        return;
                    }

                    // Round UP so no paid value is ever lost: a sub-coin
                    // remainder converts in the user's favour instead of
                    // being discarded. E.g. 3 credits @ rate 10 → 1 coin;
                    // 15 → 2 coins.
                    $coins = (int) ceil((int) $row->balance / max(1, $rate));
                    if ($coins <= 0) {
                        return;
                    }

                    try {
                        $charger->adjust(
                            $user,
                            $coins,
                            'Converted leftover AI credits to coins',
                            null,
                            [
                                'idempotency_key' => 'ai-credit-migration:' . $row->user_id,
                                'meta' => [
                                    'ai_credit_migration' => true,
                                    'credits_converted'   => (int) $row->balance,
                                    'rate'                => $rate,
                                ],
                            ]
                        );
                    } catch (\Throwable $e) {
                        // Conversion failed for this user: do NOT zero the
                        // balance so the row stays intact and a re-run can
                        // retry it. The stable idempotency key above means a
                        // later success can't double-credit. Surface the
                        // failure instead of silently destroying value.
                        Log::warning(
                            '::1inme:: AI-credit→coin conversion failed for user '
                            . $row->user_id . ': ' . $e->getMessage()
                        );
                        return;
                    }

                    // Only after a confirmed-successful adjustment do we zero
                    // the old balance (re-run safety; the idempotency key is
                    // the primary guard against double-crediting).
                    DB::table('ai_credit_balances')->where('id', $row->id)->update(['balance' => 0]);
                });
        }

        if (!Schema::hasTable('app_settings')) {
            return;
        }

        // 2) Rewrite stored per-model rates: credits-per-1k → coins-per-1k.
        $models = AppSetting::get('ai.models');
        if (is_array($models) && $models) {
            $rewritten = [];
            foreach ($models as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $in  = $m['in_coins_per_1k']  ?? (isset($m['in_credits_per_1k'])  ? (float) $m['in_credits_per_1k']  / $rate : 0);
                $out = $m['out_coins_per_1k'] ?? (isset($m['out_credits_per_1k']) ? (float) $m['out_credits_per_1k'] / $rate : 0);
                unset($m['in_credits_per_1k'], $m['out_credits_per_1k']);
                $m['in_coins_per_1k']  = round(max(0, (float) $in), 4);
                $m['out_coins_per_1k'] = round(max(0, (float) $out), 4);
                $rewritten[] = $m;
            }
            AppSetting::put('ai.models', $rewritten);
        }

        // 3) Rewrite stored voice prices: credits → coins.
        $sttOld = AppSetting::get('ai.voice.price.stt_credits_per_minute');
        if ($sttOld !== null) {
            AppSetting::put('ai.voice.price.stt_coins_per_minute', round(max(0, (float) $sttOld) / $rate, 4));
        }
        $ttsOld = AppSetting::get('ai.voice.price.tts_credits_per_1k_chars');
        if ($ttsOld !== null) {
            AppSetting::put('ai.voice.price.tts_coins_per_1k_chars', round(max(0, (float) $ttsOld) / $rate, 4));
        }

        // 4) Delete the retired settings.
        foreach ([
            'ai.wallet_to_credits_rate',
            'ai.credit_packs',
            'ai.voice.price.stt_credits_per_minute',
            'ai.voice.price.tts_credits_per_1k_chars',
        ] as $key) {
            AppSetting::where('key', $key)->delete();
            \Illuminate\Support\Facades\Cache::forget('app_setting:' . $key);
        }
    }

    public function down(): void
    {
        // Irreversible: the old credit balances have been converted to coins
        // and the retired settings dropped. No safe automatic rollback.
    }
};
