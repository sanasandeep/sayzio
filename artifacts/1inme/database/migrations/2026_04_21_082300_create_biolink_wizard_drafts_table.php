<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-(user, workspace) saved drafts for the guided biolink creation wizard.
 *
 * One draft per (user_id, workspace_id) — when a user hits the wizard again
 * they're offered to resume the in-progress draft (or start over). On finish
 * we delete the row; on "save and exit" we keep it. We deliberately key on
 * the workspace_owner_id so a team member's draft sits in the workspace they
 * were working in, never mixed with their personal workspace.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('biolink_wizard_drafts')) {
            Schema::create('biolink_wizard_drafts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');           // workspace owner (workspace_owner_id())
                $table->unsignedBigInteger('actor_user_id');     // who actually filled in the wizard (audit)
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('category', 64)->nullable();
                $table->string('page_type', 64)->nullable();
                $table->string('industry', 64)->nullable();
                $table->unsignedSmallInteger('step')->default(0);
                $table->json('answers')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index(['user_id', 'workspace_id']);
                $table->index('actor_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biolink_wizard_drafts');
    }
};
