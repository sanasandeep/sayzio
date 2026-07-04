<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency for client-invoice refunds (task-3558).
 *
 * A double-click, impatient retry, or webhook re-delivery could create two
 * Refund rows + two credit notes for the same intended refund. This adds an
 * optional `idempotency_key` plus a UNIQUE (invoice_id, idempotency_key) index
 * so a repeated keyed refund is a hard no-op at the DB level. Postgres treats
 * NULL keys as distinct, so legacy/unkeyed refunds are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('refunds')) {
            return;
        }

        if (!Schema::hasColumn('refunds', 'idempotency_key')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->string('idempotency_key', 80)->nullable()->after('gateway_ref');
            });
        }

        $hasIndex = collect(DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'refunds' AND indexname = 'refunds_invoice_idem_unique'"
        ))->isNotEmpty();

        if (!$hasIndex) {
            DB::statement('CREATE UNIQUE INDEX refunds_invoice_idem_unique ON refunds (invoice_id, idempotency_key)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS refunds_invoice_idem_unique');
        if (Schema::hasTable('refunds') && Schema::hasColumn('refunds', 'idempotency_key')) {
            Schema::table('refunds', function (Blueprint $table) {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
