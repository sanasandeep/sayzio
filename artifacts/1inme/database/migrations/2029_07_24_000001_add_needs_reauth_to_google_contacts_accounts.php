<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('google_contacts_accounts')
            && ! Schema::hasColumn('google_contacts_accounts', 'needs_reauth_at')) {
            Schema::table('google_contacts_accounts', function (Blueprint $table) {
                // Stamped when Google reports the refresh token as revoked or
                // expired (invalid_grant). While set, all sync paths skip the
                // account instead of retrying, until the user reconnects.
                $table->timestamp('needs_reauth_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('google_contacts_accounts')
            && Schema::hasColumn('google_contacts_accounts', 'needs_reauth_at')) {
            Schema::table('google_contacts_accounts', function (Blueprint $table) {
                $table->dropColumn('needs_reauth_at');
            });
        }
    }
};
