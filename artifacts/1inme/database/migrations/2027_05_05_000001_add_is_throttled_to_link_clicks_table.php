<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('link_clicks')) {
            return;
        }
        if (!Schema::hasColumn('link_clicks', 'is_throttled')) {
            Schema::table('link_clicks', function (Blueprint $table) {
                $table->boolean('is_throttled')->default(false)->after('is_bot');
                $table->index(['link_id', 'is_throttled']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('link_clicks')) {
            return;
        }
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            Schema::table('link_clicks', function (Blueprint $table) {
                $table->dropIndex(['link_id', 'is_throttled']);
                $table->dropColumn('is_throttled');
            });
        }
    }
};
