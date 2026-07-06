<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite (scope, created_at) indexes so the Leads review queue
 * (LeadAggregator) can order + paginate each of its 8 source tables in SQL
 * without a per-request table scan. The leads table's own
 * (source_type, source_id) unique index already backs the NOT EXISTS
 * "already handled" exclusion, so nothing is added there.
 *
 * form_submissions already ships a (form_id, created_at) index, so it is
 * intentionally omitted here. Idempotent (CREATE INDEX IF NOT EXISTS on
 * Postgres; existence-checked Schema builder elsewhere) so it is safe to
 * replay against the shared RDS.
 */
return new class extends Migration
{
    /** @var array<int, array{0:string,1:string,2:array<int,string>}> [indexName, table, columns] */
    protected array $indexes = [
        ['leads_scan_rsvps_idx',             'rsvps',                    ['link_id', 'created_at']],
        ['leads_scan_subscribers_idx',       'subscribers',             ['user_id', 'created_at']],
        ['leads_scan_store_orders_idx',      'store_orders',            ['link_id', 'created_at']],
        ['leads_scan_restaurant_orders_idx', 'restaurant_orders',       ['link_id', 'created_at']],
        ['leads_scan_service_bookings_idx',  'service_booking_requests', ['link_id', 'created_at']],
        ['leads_scan_reviews_idx',           'reviews',                 ['user_id', 'created_at']],
        ['leads_scan_event_interests_idx',   'event_interests',         ['link_id', 'status', 'created_at']],
    ];

    public function up(): void
    {
        $isPg = Schema::getConnection()->getDriverName() === 'pgsql';

        foreach ($this->indexes as [$name, $table, $cols]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if ($isPg) {
                $colsSql = implode(', ', $cols);
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$colsSql})");
            } elseif (!$this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
            }
        }
    }

    public function down(): void
    {
        $isPg = Schema::getConnection()->getDriverName() === 'pgsql';

        foreach ($this->indexes as [$name, $table, $cols]) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if ($isPg) {
                DB::statement("DROP INDEX IF EXISTS {$name}");
            } elseif ($this->indexExists($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }

    protected function indexExists(string $table, string $name): bool
    {
        try {
            return array_key_exists($name, Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes($table));
        } catch (\Throwable) {
            return false;
        }
    }
};
