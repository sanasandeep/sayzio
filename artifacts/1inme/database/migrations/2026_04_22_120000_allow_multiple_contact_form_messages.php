<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contact-form submissions are messages, not newsletter signups — the same
 * person can legitimately send many. Replace the strict per-(user,type,email)
 * unique with a partial unique that excludes the new 'contact_form' subscriber
 * type so contact submissions can be inserted as separate rows while existing
 * newsletter / collector dedup behaviour is preserved.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function ($t) {
            $t->dropUnique(['user_id', 'type', 'email']);
        });
        DB::statement("
            CREATE UNIQUE INDEX subscribers_user_type_email_unique
            ON subscribers (user_id, type, email)
            WHERE type <> 'contact_form'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS subscribers_user_type_email_unique');
        Schema::table('subscribers', function ($t) {
            $t->unique(['user_id', 'type', 'email']);
        });
    }
};
