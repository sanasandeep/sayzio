<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_launch_signups') && !Schema::hasColumn('app_launch_signups', 'unsubscribed_at')) {
            Schema::table('app_launch_signups', function (Blueprint $table) {
                // Set when the recipient opts out via the signed one-click
                // unsubscribe link in the launch email. The notifier skips any
                // row with this stamped so an unsubscribed address is never
                // emailed — even if it somehow never got `notified_at`.
                $table->timestamp('unsubscribed_at')->nullable()->after('notified_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_launch_signups') && Schema::hasColumn('app_launch_signups', 'unsubscribed_at')) {
            Schema::table('app_launch_signups', function (Blueprint $table) {
                $table->dropColumn('unsubscribed_at');
            });
        }
    }
};
