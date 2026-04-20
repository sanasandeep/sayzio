<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'follower_updates_mode')) {
                // 'instant' | 'digest' | 'off'
                $table->string('follower_updates_mode', 16)->default('digest')->after('notify_follower_updates');
            }
            if (!Schema::hasColumn('users', 'follower_digest_last_sent_at')) {
                $table->timestamp('follower_digest_last_sent_at')->nullable()->after('follower_updates_mode');
            }
        });

        // Backfill from the existing boolean: opted-in users default to the
        // new "digest" mode (the new sane default), opted-out users to "off".
        DB::table('users')->where('notify_follower_updates', true)->update(['follower_updates_mode' => 'digest']);
        DB::table('users')->where('notify_follower_updates', false)->update(['follower_updates_mode' => 'off']);

        Schema::table('user_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notifications', 'emailed_at')) {
                // For follower_update rows: null means "still pending in the
                // digest queue"; non-null means an email has already been
                // sent (instant or as part of a previous digest).
                $table->timestamp('emailed_at')->nullable()->after('read_at');
                $table->index(['type', 'emailed_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('user_notifications', 'emailed_at')) {
                $table->dropIndex(['type', 'emailed_at']);
                $table->dropColumn('emailed_at');
            }
        });
        Schema::table('users', function (Blueprint $table) {
            foreach (['follower_updates_mode', 'follower_digest_last_sent_at'] as $c) {
                if (Schema::hasColumn('users', $c)) $table->dropColumn($c);
            }
        });
    }
};
