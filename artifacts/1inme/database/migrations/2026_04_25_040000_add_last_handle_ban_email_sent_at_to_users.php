<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the last time we emailed a user that their handle is on the
 * banned list. Used by the admin "Notify user" action on the banned-name
 * conflicts page to rate-limit how often the same user can be emailed,
 * so an admin clicking the button repeatedly can't spam the inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_handle_ban_email_sent_at')->nullable()->after('social_connection_broken_emails');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_handle_ban_email_sent_at');
        });
    }
};
