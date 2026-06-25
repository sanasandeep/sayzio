<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit ledger for the one-click schema column repair surfaced on the
 * admin dashboard ("Fix now" → {@see \App\Modules\Common\Support\ExpectedSchemaHealth::repair()}).
 *
 * That action alters the live database in place (adding + backfilling
 * columns the app depends on) but is destructive-adjacent ops work, so
 * each run records WHO ran it and WHEN, plus the schema-level outcome:
 * which columns were added per table and which whole-missing tables it
 * could not repair. Only schema metadata is logged — never row data or
 * secrets. The record lives in its own table so it survives the schema
 * health cache flush that follows a repair.
 *
 * The action is gated behind the admin guard, so the actor is normally an
 * Admin; the actor columns mirror the dual-guard shape used by
 * `user_role_audits` for completeness and snapshot name/email so the row
 * stays readable after the actor is renamed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('schema_repair_audits')) {
            Schema::create('schema_repair_audits', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Either actor_admin_id (admin guard) or actor_user_id (web
                // guard) is set; both null means a system / CLI invocation.
                $table->unsignedBigInteger('actor_admin_id')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('actor_guard', 16)->nullable();
                $table->string('actor_name', 191)->nullable();
                $table->string('actor_email', 191)->nullable();

                // Schema-level outcome only. `added` is a JSON map of
                // table => [columns added/backfilled]; `unrepairable` is a
                // JSON list of whole-missing table names the repair could not
                // recreate (they still need `migrate --force`).
                $table->json('added')->nullable();
                $table->json('unrepairable')->nullable();

                // Denormalised counts so the index/list view doesn't have to
                // decode the JSON to show a summary.
                $table->unsignedInteger('added_columns_count')->default(0);
                $table->unsignedInteger('added_tables_count')->default(0);
                $table->unsignedInteger('unrepairable_count')->default(0);

                $table->string('ip', 64)->nullable();

                $table->timestamp('created_at')->useCurrent();

                $table->index(['created_at'], 'schema_repair_audits_created_idx');
                $table->index(['actor_admin_id', 'created_at'], 'schema_repair_audits_actor_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_repair_audits');
    }
};
