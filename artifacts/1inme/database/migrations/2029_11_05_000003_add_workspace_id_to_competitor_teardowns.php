<?php

use App\Modules\User\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give `competitor_teardowns` the `workspace_id` column its model has been
 * writing to since the day it was created.
 *
 * App\Modules\User\Models\CompetitorTeardown uses BelongsToWorkspace, whose
 * creating() hook fills workspace_id on every insert while a current_workspace
 * is bound -- which it is for any signed-in request. The table never had the
 * column, so every "Run teardown" died on:
 *
 *   SQLSTATE[42703]: Undefined column: column "workspace_id" of relation
 *   "competitor_teardowns" does not exist
 *
 * The table was created in its own migration after the workspace sweep had
 * already run, so it missed it while the model picked the trait up. Of the 67
 * models using BelongsToWorkspace, this was the only table no migration ever
 * gave the column.
 *
 * The read half had already been worked around: the controller queried with
 * withoutGlobalScope('workspace'), because the scope's WHERE would have failed
 * the same way. That workaround goes with this migration -- teardowns now scope
 * like every other workspace-owned record.
 *
 * The backfill mirrors 2029_11_04_000005_scope_brand_kits_to_workspaces. It is
 * expected to be a no-op in practice (inserts have always failed, so there
 * should be no rows), but a row could exist from a CLI path where no workspace
 * was bound, and leaving one with a NULL workspace_id would hide it from its
 * owner the moment the scope starts applying.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('competitor_teardowns', 'workspace_id')) {
            return;
        }

        Schema::table('competitor_teardowns', fn (Blueprint $table) => $table->foreignId('workspace_id')->nullable()->index()->constrained('workspaces')->nullOnDelete());

        DB::table('competitor_teardowns')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $id = DB::table('workspaces')->where('owner_user_id', $row->user_id)->where('is_personal', true)->value('id');
                if (! $id) {
                    $id = User::find($row->user_id)?->ensureDefaultWorkspace()->id;
                }
                if ($id) {
                    DB::table('competitor_teardowns')->where('id', $row->id)->update(['workspace_id' => $id]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('competitor_teardowns', fn (Blueprint $table) => $table->dropConstrainedForeignId('workspace_id'));
    }
};
