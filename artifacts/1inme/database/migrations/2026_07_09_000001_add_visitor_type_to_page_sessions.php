<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('page_sessions') && !Schema::hasColumn('page_sessions', 'visitor_type')) {
            Schema::table('page_sessions', function (Blueprint $t) {
                $t->string('visitor_type', 40)->nullable()->after('source');
                $t->index(['link_id', 'visitor_type'], 'page_sessions_link_visitor_type_idx');
            });
        }

        if (Schema::hasTable('subscribers') && !Schema::hasColumn('subscribers', 'visitor_type')) {
            Schema::table('subscribers', function (Blueprint $t) {
                $t->string('visitor_type', 40)->nullable()->after('metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscribers') && Schema::hasColumn('subscribers', 'visitor_type')) {
            Schema::table('subscribers', function (Blueprint $t) {
                $t->dropColumn('visitor_type');
            });
        }
        if (Schema::hasTable('page_sessions') && Schema::hasColumn('page_sessions', 'visitor_type')) {
            Schema::table('page_sessions', function (Blueprint $t) {
                $t->dropIndex('page_sessions_link_visitor_type_idx');
                $t->dropColumn('visitor_type');
            });
        }
    }
};
