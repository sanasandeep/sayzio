<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3158 — Strategist data picker upgrade.
 *
 * Persists the per-source item selection (e.g. specific links / knowledge
 * bases / brand kits chosen for a strategy) so a narrowed strategy remembers
 * exactly which items it was grounded in and the builder can round-trip the
 * choice on re-open. An empty map (or an empty list per source) means
 * "use all" — preserving the original whole-category behaviour.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_strategies')) {
            return;
        }
        if (Schema::hasColumn('marketing_strategies', 'source_items')) {
            return;
        }

        Schema::table('marketing_strategies', function (Blueprint $table) {
            $table->json('source_items')->nullable()->after('sources');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_strategies') && Schema::hasColumn('marketing_strategies', 'source_items')) {
            Schema::table('marketing_strategies', function (Blueprint $table) {
                $table->dropColumn('source_items');
            });
        }
    }
};
