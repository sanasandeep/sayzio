<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the dead `ai_credits_monthly` plan/add-on feature.
 *
 * AI usage is now billed straight from the coin wallet (see
 * 2027_06_19_000001_migrate_ai_credits_to_coins). The separate AI-credit
 * balance no longer exists, so the `ai_credits_monthly` feature key that
 * lingered in stored plan/add-on `features` JSON drives no billing and only
 * confuses admins. This one-time cleanup:
 *
 *   1. Strips the `ai_credits_monthly` key from every stored plan and
 *      add-on `features` JSON payload.
 *   2. Archives + deactivates the seeded "AI Assistant Credits (10k)"
 *      add-on (slug `ai-credits-10k`), whose sole feature was the dead key,
 *      so it can no longer be purchased for a capability that no longer
 *      exists.
 *
 * Idempotent: re-running simply finds nothing left to strip.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['plans', 'addons'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull('features')
                ->orderBy('id')
                ->each(function ($row) use ($table) {
                    $features = json_decode($row->features ?? '[]', true);
                    if (!is_array($features) || !array_key_exists('ai_credits_monthly', $features)) {
                        return;
                    }

                    unset($features['ai_credits_monthly']);

                    DB::table($table)->where('id', $row->id)->update([
                        'features' => json_encode($features),
                    ]);
                });
        }

        if (Schema::hasTable('addons')) {
            $update = ['status' => 'inactive'];
            if (Schema::hasColumn('addons', 'is_archived')) {
                $update['is_archived'] = true;
            }

            DB::table('addons')->where('slug', 'ai-credits-10k')->update($update);
        }
    }

    public function down(): void
    {
        // One-way data cleanup: the dead feature key carried no billing
        // behaviour, so there is nothing meaningful to restore.
    }
};
