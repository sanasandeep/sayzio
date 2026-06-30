<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #2909 — let creators share an AI Mind (knowledge base) or AI
 * Persona agent with a team workspace or an account-badge group.
 *
 * Polymorphic on BOTH ends:
 *   resource_type  = mind | persona      (what is shared)
 *   audience_type  = workspace | badge   (who it is shared with)
 *
 * Ownership stays on the resource's own `user_id` — there is no
 * `workspace_id` on minds/personas. Access is resolved LIVE against
 * the recipient's current memberships/badges, so removing a member or
 * detaching a badge revokes access immediately without touching this
 * table. The per-resource model `deleting` hooks purge orphan rows.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): no FKs
 * (the audience/resource are polymorphic), cleanup via model hooks.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ai_resource_shares')) {
            return;
        }

        Schema::create('ai_resource_shares', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 16);   // mind | persona
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('owner_user_id');
            $table->string('audience_type', 16);    // workspace | badge
            $table->unsignedBigInteger('audience_id');
            $table->string('access', 8)->default('use'); // use | edit
            $table->timestamps();

            // One share row per (resource, audience). Re-sharing updates it.
            $table->unique(
                ['resource_type', 'resource_id', 'audience_type', 'audience_id'],
                'ai_resource_shares_unique'
            );
            // Resolution lookups: "what is shared with these audiences".
            $table->index(['audience_type', 'audience_id'], 'ai_resource_shares_audience_idx');
            // Owner manage / per-resource listing.
            $table->index(['resource_type', 'resource_id'], 'ai_resource_shares_resource_idx');
            $table->index('owner_user_id', 'ai_resource_shares_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_resource_shares');
    }
};
