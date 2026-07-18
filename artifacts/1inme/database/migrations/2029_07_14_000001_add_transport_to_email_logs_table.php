<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the effective mail transport/driver used for each outbound send.
 *
 * With the SMTP override gated to production, non-production sends are
 * black-holed through the "log" (or "array") driver but still recorded as
 * "sent". Stamping the driver lets the admin Email Log flag those rows as
 * "log driver (not delivered)" so an operator debugging a missing email isn't
 * misled by a false success.
 *
 * Additive, shared-DB-safe: nullable column guarded by hasColumn.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('email_logs') && !Schema::hasColumn('email_logs', 'transport')) {
            Schema::table('email_logs', function (Blueprint $table) {
                // Effective mailer transport/driver: smtp | log | array | ...
                // Null for pre-existing rows (transport unknown).
                $table->string('transport', 32)->nullable()->after('format');
                $table->index('transport', 'email_logs_transport_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('email_logs') && Schema::hasColumn('email_logs', 'transport')) {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->dropIndex('email_logs_transport_idx');
                $table->dropColumn('transport');
            });
        }
    }
};
