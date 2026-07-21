<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite (menu_id, updated_at) indexes to the polling tables so
 * the incremental-cursor poll queries (restaurant and store orders) run
 * against an index rather than a full table scan.
 *
 * Both RestaurantController::ownerPoll and StoreController::ownerPoll
 * filter `where('menu_id', $menu->id)->where('updated_at', '>', $since)
 * ->latest('updated_at')`, which is exactly the prefix of this index.
 *
 * The single-column `menu_id` index added by the original create
 * migrations remains for other queries; this index is additive.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('restaurant_orders')
            && !$this->indexExists('restaurant_orders', 'restaurant_orders_menu_id_updated_at_index')
        ) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->index(['menu_id', 'updated_at'], 'restaurant_orders_menu_id_updated_at_index');
            });
        }

        if (Schema::hasTable('store_orders')
            && !$this->indexExists('store_orders', 'store_orders_menu_id_updated_at_index')
        ) {
            Schema::table('store_orders', function (Blueprint $table) {
                $table->index(['menu_id', 'updated_at'], 'store_orders_menu_id_updated_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('restaurant_orders')
            && $this->indexExists('restaurant_orders', 'restaurant_orders_menu_id_updated_at_index')
        ) {
            Schema::table('restaurant_orders', function (Blueprint $table) {
                $table->dropIndex('restaurant_orders_menu_id_updated_at_index');
            });
        }
        if (Schema::hasTable('store_orders')
            && $this->indexExists('store_orders', 'store_orders_menu_id_updated_at_index')
        ) {
            Schema::table('store_orders', function (Blueprint $table) {
                $table->dropIndex('store_orders_menu_id_updated_at_index');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $db = \Illuminate\Support\Facades\DB::connection();
            if ($db->getDriverName() === 'mysql') {
                $rows = $db->select(
                    'SELECT index_name FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                    [$table, $indexName]
                );
            } else {
                $rows = $db->select(
                    'SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$table, $indexName]
                );
            }
            return !empty($rows);
        } catch (\Throwable) {
            return false;
        }
    }
};
