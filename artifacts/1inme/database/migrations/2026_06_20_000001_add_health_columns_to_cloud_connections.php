<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_connections', function (Blueprint $table) {
            // When the daily sweep last attempted to validate this connection.
            // Distinct from last_synced_at, which only updates on successful
            // refreshes/listing through the picker.
            $table->timestamp('last_checked_at')->nullable()->after('last_synced_at');
            // When last_error was most recently set. Used to decide whether
            // a previously-dismissed banner should re-appear after a new
            // breakage (broke -> dismissed -> recovered -> broke again).
            $table->timestamp('last_error_at')->nullable()->after('last_error');
            // Dedup column: timestamp of the last "your connection broke" email
            // we sent to the connection owner. Cooldown enforced in the mailer.
            $table->timestamp('last_broken_email_sent_at')->nullable()->after('last_error_at');
            // When the user dismissed the in-app banner for this connection.
            // Re-arms automatically when last_error_at advances past it.
            $table->timestamp('banner_dismissed_at')->nullable()->after('last_broken_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('cloud_connections', function (Blueprint $table) {
            $table->dropColumn([
                'last_checked_at',
                'last_error_at',
                'last_broken_email_sent_at',
                'banner_dismissed_at',
            ]);
        });
    }
};
