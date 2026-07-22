<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_contacts_accounts')
            && ! Schema::hasColumn('google_contacts_accounts', 'reauth_reminder_sent_at')) {
            Schema::table('google_contacts_accounts', function (Blueprint $table) {
                // Stamped once when the 7-day "still disconnected" follow-up
                // reminder is delivered, so it is never re-sent for the same
                // expiry. Cleared alongside needs_reauth_at on reconnect,
                // re-arming the reminder for a future expiry.
                $table->timestamp('reauth_reminder_sent_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('google_contacts_accounts')
            && Schema::hasColumn('google_contacts_accounts', 'reauth_reminder_sent_at')) {
            Schema::table('google_contacts_accounts', function (Blueprint $table) {
                $table->dropColumn('reauth_reminder_sent_at');
            });
        }
    }
};
