<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('feature_notify_interests')) {
            return;
        }

        if (!Schema::hasColumn('feature_notify_interests', 'notified_at')) {
            Schema::table('feature_notify_interests', function (Blueprint $table) {
                $table->timestamp('notified_at')->nullable()->after('feature_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('feature_notify_interests')
            && Schema::hasColumn('feature_notify_interests', 'notified_at')) {
            Schema::table('feature_notify_interests', function (Blueprint $table) {
                $table->dropColumn('notified_at');
            });
        }
    }
};
