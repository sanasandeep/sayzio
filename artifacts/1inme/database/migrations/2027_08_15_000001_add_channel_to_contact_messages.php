<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-channel quick-contact: let a contact request carry the channel the
 * visitor wants to be reached on (callback / whatsapp / email) and the phone
 * number to use, alongside the existing name/email/message.
 *
 * Additive + hasColumn-guarded so it is safe to re-run against the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_messages', 'contact_channel')) {
                $table->string('contact_channel', 20)->nullable()->after('email');
            }
            if (!Schema::hasColumn('contact_messages', 'contact_phone')) {
                $table->string('contact_phone', 40)->nullable()->after('contact_channel');
            }
        });
    }

    public function down(): void
    {
        // No-op: shared-RDS policy is additive-only. Leaving the columns in
        // place on rollback is harmless (they are nullable).
    }
};
