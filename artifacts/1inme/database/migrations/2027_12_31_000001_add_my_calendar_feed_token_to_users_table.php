<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'my_calendar_feed_token')) {
            Schema::table('users', function (Blueprint $table) {
                // Long-lived, per-user token that authenticates the "My Calendar"
                // ICS subscription feed (no session). Rotatable from the My
                // Calendar page so a leaked feed URL can be revoked. Lazily
                // minted on first use, so this column stays null until then.
                $table->string('my_calendar_feed_token', 64)->nullable()->unique()->after('settings');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'my_calendar_feed_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('my_calendar_feed_token');
            });
        }
    }
};
