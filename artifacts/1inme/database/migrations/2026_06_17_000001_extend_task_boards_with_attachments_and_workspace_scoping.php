<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Round-2 v1 hardening for the kanban tasks feature:
 *  - Adds `workspace_id` to subtasks, comments and activities so the
 *    BelongsToWorkspace global scope blocks cross-workspace access at the
 *    Eloquent layer instead of relying on controller-level checks.
 *  - Adds `progress` (0..100 int) and `description_html` to task_cards so
 *    cards can carry a percent-done value plus rich-text description.
 *  - Creates `task_attachments` for file uploads (max 10MB enforced by the
 *    controller; metadata + storage path live here).
 *  - Backfills personal "My Tasks" boards for every existing user so the
 *    new auto-create-on-user-creation hook in User::booted() doesn't leave
 *    pre-existing accounts without a starter board.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_subtasks', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('id');
            $table->index('workspace_id');
        });
        Schema::table('task_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('id');
            $table->index('workspace_id');
        });
        Schema::table('task_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('id');
            $table->index('workspace_id');
        });

        Schema::table('task_cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('priority');
            $table->longText('description_html')->nullable()->after('description');
        });

        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('uploaded_by_user_id')->nullable();
            $table->string('original_name');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('disk', 32)->default('public');
            $table->string('path', 1024);
            $table->timestamps();

            $table->index(['card_id', 'created_at']);
            $table->index('workspace_id');
        });

        // Backfill workspace_id for the new columns by joining through
        // task_cards (the source of truth for workspace ownership). Written
        // without table aliases so SQLite (used in tests) accepts it.
        DB::statement("UPDATE task_subtasks
            SET workspace_id = (SELECT workspace_id FROM task_cards WHERE task_cards.id = task_subtasks.card_id)
            WHERE workspace_id IS NULL");
        DB::statement("UPDATE task_comments
            SET workspace_id = (SELECT workspace_id FROM task_cards WHERE task_cards.id = task_comments.card_id)
            WHERE workspace_id IS NULL");
        DB::statement("UPDATE task_activities
            SET workspace_id = (SELECT workspace_id FROM task_cards WHERE task_cards.id = task_activities.card_id)
            WHERE workspace_id IS NULL");

        // Backfill: every existing user gets a "My Tasks" personal board on
        // their default (personal) workspace if they don't already have one.
        $users = DB::table('users')->select('id')->get();
        foreach ($users as $u) {
            $ws = DB::table('workspaces')
                ->where('owner_user_id', $u->id)
                ->where(function ($q) { $q->where('is_personal', true)->orWhereNull('is_personal'); })
                ->orderBy('id')
                ->first();
            if (!$ws) continue;
            $exists = DB::table('task_boards')
                ->where('owner_user_id', $u->id)
                ->where('scope', 'personal')
                ->where('workspace_id', $ws->id)
                ->exists();
            if ($exists) continue;
            $now = now();
            $boardId = DB::table('task_boards')->insertGetId([
                'workspace_id'  => $ws->id,
                'scope'         => 'personal',
                'owner_user_id' => $u->id,
                'name'          => 'My Tasks',
                'color'         => '#8b5cf6',
                'position'      => 0,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $cols = [
                ['name' => 'Backlog',     'color' => '#64748b', 'is_done' => false],
                ['name' => 'In Progress', 'color' => '#3b82f6', 'is_done' => false],
                ['name' => 'Review',      'color' => '#a855f7', 'is_done' => false],
                ['name' => 'Done',        'color' => '#10b981', 'is_done' => true ],
            ];
            foreach ($cols as $i => $col) {
                DB::table('task_columns')->insert([
                    'workspace_id' => $ws->id,
                    'board_id'     => $boardId,
                    'name'         => $col['name'],
                    'color'        => $col['color'],
                    'is_done'      => $col['is_done'],
                    'position'     => $i + 1,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_attachments');
        Schema::table('task_cards', function (Blueprint $table) {
            $table->dropColumn(['progress', 'description_html']);
        });
        Schema::table('task_activities', function (Blueprint $table) {
            $table->dropColumn('workspace_id');
        });
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn('workspace_id');
        });
        Schema::table('task_subtasks', function (Blueprint $table) {
            $table->dropColumn('workspace_id');
        });
    }
};
