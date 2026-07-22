<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #5612 — system-generated vault files (e.g. Brand Kit visual assets)
 * are tagged with a `context` so they can be excluded from the per-plan
 * `max_files` count while still counting toward the storage-byte quota.
 * NULL = a regular user upload (counts everywhere, unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_files', 'context')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->string('context', 40)->nullable()->index()->after('type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_files', 'context')) {
            Schema::table('user_files', function (Blueprint $table) {
                $table->dropColumn('context');
            });
        }
    }
};
