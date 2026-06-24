<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive columns for the persona-driven wizard:
 *  - persona / persona_group: the PersonaCatalog selection that now drives the
 *    wizard's first two steps (category/page_type are derived from it).
 *  - template_id: the persona-tagged starting design the user picked (null =
 *    "Start from scratch", i.e. the original recipe/AI-only behaviour).
 *
 * Guarded with hasColumn so re-runs over the shared RDS are idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('biolink_wizard_drafts', 'persona')) {
                $table->string('persona')->nullable()->after('workspace_id');
            }
            if (!Schema::hasColumn('biolink_wizard_drafts', 'persona_group')) {
                $table->string('persona_group')->nullable()->after('persona');
            }
            if (!Schema::hasColumn('biolink_wizard_drafts', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('industry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            foreach (['persona', 'persona_group', 'template_id'] as $col) {
                if (Schema::hasColumn('biolink_wizard_drafts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
