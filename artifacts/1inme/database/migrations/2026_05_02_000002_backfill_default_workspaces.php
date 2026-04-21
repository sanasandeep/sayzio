<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Create one default workspace per existing user (idempotent: skip
        //    users that already own one). Owner becomes a member with the
        //    'owner' role implicitly — owner membership is represented on
        //    the workspaces.owner_user_id column, not a member row.
        $users = DB::table('users')->select('id', 'name')->orderBy('id')->get();
        foreach ($users as $u) {
            $exists = DB::table('workspaces')->where('owner_user_id', $u->id)->exists();
            if ($exists) continue;
            DB::table('workspaces')->insert([
                'owner_user_id' => $u->id,
                'name'          => ($u->name ?: ('User ' . $u->id)) . "'s workspace",
                'slug'          => 'ws-' . $u->id,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // 2. Build owner_user_id -> workspace_id map.
        $ownerToWs = DB::table('workspaces')->pluck('id', 'owner_user_id')->all();

        // 3. Backfill workspace_id (and created_by_user_id) on every table.
        $tableSpecs = [
            // [tableName, ownerColumn]
            ['links', 'user_id'],
            ['projects', 'user_id'],
            ['creator_posts', 'user_id'],
            ['forms', 'user_id'],
            ['subscribers', 'user_id'],
            ['pixels', 'user_id'],
            ['qr_codes', 'user_id'],
            ['splash_pages', 'user_id'],
            ['user_files', 'user_id'],
            ['contacts', 'user_id'],
            ['referrals', 'referrer_id'],
            ['referral_rewards', 'user_id'],
            ['social_proofs', 'user_id'],
            ['calendar_accounts', 'user_id'],
            ['integration_configs', 'user_id'],
            ['inbox_replies', 'user_id'],
            ['inbox_forward_destinations', 'user_id'],
            ['follows', 'creator_id'],
            ['domains', 'user_id'],
        ];

        foreach ($tableSpecs as [$tableName, $ownerColumn]) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'workspace_id')) continue;
            if (!Schema::hasColumn($tableName, $ownerColumn)) continue;

            DB::table($tableName)->whereNull('workspace_id')->orderBy('id')->chunkById(500, function ($rows) use ($tableName, $ownerColumn, $ownerToWs) {
                foreach ($rows as $row) {
                    $oid = $row->{$ownerColumn} ?? null;
                    if (!$oid) continue;
                    $wsId = $ownerToWs[$oid] ?? null;
                    if (!$wsId) continue;
                    $patch = ['workspace_id' => $wsId];
                    if (Schema::hasColumn($tableName, 'created_by_user_id') && empty($row->created_by_user_id)) {
                        $patch['created_by_user_id'] = $oid;
                    }
                    DB::table($tableName)->where('id', $row->id)->update($patch);
                }
            });
        }

        // form_submissions has no direct user_id — derive workspace from form.
        if (Schema::hasTable('form_submissions') && Schema::hasColumn('form_submissions', 'workspace_id')) {
            DB::statement('UPDATE form_submissions
                           SET workspace_id = (SELECT workspace_id FROM forms WHERE forms.id = form_submissions.form_id)
                           WHERE workspace_id IS NULL');
        }

        // link_performance_snapshots has no user_id — derive workspace from
        // its parent link. Without this, snapshots remain orphaned (NULL
        // workspace_id) after backfill and disappear behind the global scope.
        if (Schema::hasTable('link_performance_snapshots') && Schema::hasColumn('link_performance_snapshots', 'workspace_id')) {
            DB::statement('UPDATE link_performance_snapshots
                           SET workspace_id = (SELECT workspace_id FROM links WHERE links.id = link_performance_snapshots.link_id)
                           WHERE workspace_id IS NULL');
        }
    }

    public function down(): void
    {
        // No-op: workspace removal is handled by the previous migration's down().
    }
};
