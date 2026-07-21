<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #5508: upgrade dialer notes into a productivity surface.
 * Additive-only; hasColumn-guarded for the shared RDS.
 *
 * - kind: 'note' (plain) vs 'checklist' (to-do with checkbox items).
 * - checklist: JSON array of {text, done} items.
 * - source_type/source_id: set on auto-created tasks (event / callback) so
 *   the sync job can upsert without duplicating.
 * - reminder_sent_at: idempotency stamp for the due-reminder alert job.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dialer_notes')) return;

        Schema::table('dialer_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('dialer_notes', 'kind')) {
                $table->string('kind', 16)->default('note');
            }
            if (!Schema::hasColumn('dialer_notes', 'checklist')) {
                $table->json('checklist')->nullable();
            }
            if (!Schema::hasColumn('dialer_notes', 'source_type')) {
                $table->string('source_type', 32)->nullable();
            }
            if (!Schema::hasColumn('dialer_notes', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable();
            }
            if (!Schema::hasColumn('dialer_notes', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable();
            }
        });

        // One auto task per (user, source); partial so manual notes are free.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS dialer_notes_user_source_unique
             ON dialer_notes (user_id, source_type, source_id)
             WHERE source_type IS NOT NULL AND source_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('dialer_notes')) return;
        DB::statement('DROP INDEX IF EXISTS dialer_notes_user_source_unique');
        Schema::table('dialer_notes', function (Blueprint $table) {
            foreach (['kind', 'checklist', 'source_type', 'source_id', 'reminder_sent_at'] as $col) {
                if (Schema::hasColumn('dialer_notes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
