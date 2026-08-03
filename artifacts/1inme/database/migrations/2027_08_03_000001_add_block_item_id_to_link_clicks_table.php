<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #6576 — per-item click attribution for multi-link blocks
 * (link_tree_group). Stores the stable item id carried by the block
 * redirect's `item` query param / the mobile tap payload so analytics
 * can distinguish items even when they share a destination URL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('link_clicks', 'block_item_id')) return;
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->string('block_item_id', 32)->nullable()->after('block_type');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('link_clicks', 'block_item_id')) return;
        Schema::table('link_clicks', function (Blueprint $table) {
            $table->dropColumn('block_item_id');
        });
    }
};
