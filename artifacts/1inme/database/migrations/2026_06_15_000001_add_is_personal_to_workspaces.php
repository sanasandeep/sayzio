<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('workspaces', 'is_personal')) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->boolean('is_personal')->default(false)->after('slug');
            });
        }

        // Index creation is guarded separately so a re-run after a failed
        // partial deploy (column present, index missing) still backfills it.
        $indexName = 'workspaces_owner_user_id_is_personal_index';
        $exists = collect(DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?",
            [$indexName]
        ))->isNotEmpty();
        if (!$exists) {
            Schema::table('workspaces', function (Blueprint $table) {
                $table->index(['owner_user_id', 'is_personal']);
            });
        }

        // Enforce "at most one personal workspace per owner" at the DB level
        // so concurrent ensureDefaultWorkspace() calls cannot create dupes.
        // Postgres partial unique index — created idempotently.
        $uniqName = 'workspaces_one_personal_per_owner_unique';
        $uniqExists = collect(DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?",
            [$uniqName]
        ))->isNotEmpty();
        if (!$uniqExists) {
            DB::statement("CREATE UNIQUE INDEX {$uniqName} ON workspaces (owner_user_id) WHERE is_personal = true");
        }

        // Mark every user's earliest-owned workspace as their personal one.
        // This matches the original backfill that created exactly one
        // workspace per existing user.
        $rows = DB::table('workspaces')
            ->select('id', 'owner_user_id')
            ->orderBy('owner_user_id')
            ->orderBy('id')
            ->get();

        $seenOwners = [];
        foreach ($rows as $row) {
            if (in_array((int) $row->owner_user_id, $seenOwners, true)) continue;
            DB::table('workspaces')->where('id', $row->id)->update(['is_personal' => true]);
            $seenOwners[] = (int) $row->owner_user_id;
        }

        // Lazy-create a personal workspace for any user who somehow doesn't
        // have one yet (defensive — should be a no-op after the previous
        // backfill migration).
        $now = now();
        $usersWithoutWs = DB::table('users')
            ->leftJoin('workspaces', 'workspaces.owner_user_id', '=', 'users.id')
            ->whereNull('workspaces.id')
            ->select('users.id', 'users.name')
            ->get();

        foreach ($usersWithoutWs as $u) {
            DB::table('workspaces')->insert([
                'owner_user_id' => $u->id,
                'name'          => ($u->name ?: ('User ' . $u->id)) . "'s workspace",
                'slug'          => 'ws-' . $u->id,
                'is_personal'   => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX IF EXISTS workspaces_one_personal_per_owner_unique'); } catch (\Throwable $e) {}
        if (Schema::hasColumn('workspaces', 'is_personal')) {
            Schema::table('workspaces', function (Blueprint $table) {
                try { $table->dropIndex(['owner_user_id', 'is_personal']); } catch (\Throwable $e) {}
                $table->dropColumn('is_personal');
            });
        }
    }
};
