<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the wizard's AI auto-draft selections on the draft so they survive
 * a resume: which AI Brains (Minds) the user picked to ground the generation,
 * whether to fold in the platform Mind, and which vault files they chose to
 * feed in / attach to the page.
 *
 * Additive-only (shared RDS): each column is guarded so re-running is safe and
 * the migration never drops or rewrites existing data.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('biolink_wizard_drafts')) {
            return;
        }

        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('biolink_wizard_drafts', 'ai_mind_ids')) {
                $table->json('ai_mind_ids')->nullable()->after('answers');
            }
            if (!Schema::hasColumn('biolink_wizard_drafts', 'include_platform_mind')) {
                $table->boolean('include_platform_mind')->default(false)->after('ai_mind_ids');
            }
            if (!Schema::hasColumn('biolink_wizard_drafts', 'file_ids')) {
                $table->json('file_ids')->nullable()->after('include_platform_mind');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('biolink_wizard_drafts')) {
            return;
        }

        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            foreach (['ai_mind_ids', 'include_platform_mind', 'file_ids'] as $col) {
                if (Schema::hasColumn('biolink_wizard_drafts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
