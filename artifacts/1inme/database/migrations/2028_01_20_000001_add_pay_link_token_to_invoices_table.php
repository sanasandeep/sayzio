<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revocable client-invoice pay links (Task #4337, security hardening).
 *
 * Client-invoice pay URLs are public, temporary-signed links. Expiry alone does
 * not let an owner INVALIDATE a mis-sent link before it naturally expires. This
 * column carries a per-invoice secret that is embedded in the signed pay URL and
 * checked server-side on every hit, so rotating it (on recipient change or a
 * fresh send) immediately kills every previously-issued link for that invoice.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'pay_link_token')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('pay_link_token', 64)->nullable()->after('recipient_email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'pay_link_token')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('pay_link_token');
            });
        }
    }
};
