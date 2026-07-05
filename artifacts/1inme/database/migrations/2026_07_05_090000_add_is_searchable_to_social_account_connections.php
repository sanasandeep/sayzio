<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3588: per-account "Searchable in public" toggle. Additive-only —
 * never edit the original create migration on a shared RDS
 * (.agents/memory/migration-edit-after-applied-drift.md). Defaults to
 * false/conservative: existing connections stay private (not surfaced in
 * caller-ID, the dialer universal finder, or public search) until the
 * owner opts in.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('social_account_connections') && !Schema::hasColumn('social_account_connections', 'is_searchable')) {
            Schema::table('social_account_connections', function (Blueprint $table) {
                $table->boolean('is_searchable')->default(false)->after('meta');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_account_connections') && Schema::hasColumn('social_account_connections', 'is_searchable')) {
            Schema::table('social_account_connections', function (Blueprint $table) {
                $table->dropColumn('is_searchable');
            });
        }
    }
};
