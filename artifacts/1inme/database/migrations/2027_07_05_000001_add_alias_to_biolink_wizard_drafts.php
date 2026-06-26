<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry a user-typed custom alias (Custom URL) into the guided wizard. When a
 * user types an alias on the Create Link page and clicks the guided wizard
 * hero, the chosen alias is stashed on the draft so the eventual biolink is
 * created with it instead of an auto-generated one.
 *
 * Additive-only (shared RDS): the column is guarded so re-running is safe and
 * the migration never drops or rewrites existing data.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('biolink_wizard_drafts')) {
            return;
        }

        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            if (!Schema::hasColumn('biolink_wizard_drafts', 'alias')) {
                $table->string('alias')->nullable()->after('workspace_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('biolink_wizard_drafts')) {
            return;
        }

        Schema::table('biolink_wizard_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('biolink_wizard_drafts', 'alias')) {
                $table->dropColumn('alias');
            }
        });
    }
};
