<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notifications', 'dismissed_at')) {
                // Soft-delete column. Dismissing a notification stamps this
                // instead of hard-deleting the row, so a mistaken tap can be
                // undone (toast on web/mobile) or restored from the
                // "Recently dismissed" list within the retention window.
                $table->timestamp('dismissed_at')->nullable()->after('read_at');
                $table->index(['user_id', 'dismissed_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('user_notifications', 'dismissed_at')) {
                $table->dropIndex(['user_id', 'dismissed_at']);
                $table->dropColumn('dismissed_at');
            }
        });
    }
};
