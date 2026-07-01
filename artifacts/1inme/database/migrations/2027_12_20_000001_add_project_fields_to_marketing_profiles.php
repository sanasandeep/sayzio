<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3302 — turn the single per-(user,workspace) Marketing Profile into
 * MULTIPLE named "project" profiles. Adds the durable project fields a plan
 * is built around (name, business, industry, brand kit link, main offer,
 * budget, currency) and back-fills any existing row to a named default so
 * nothing is lost.
 *
 * Additive / guarded / idempotent (shared-RDS merge-safe): every column is
 * added under a hasColumn guard, the back-fill only touches un-named rows,
 * and no FKs are introduced (ownership stays enforced in the controller).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketing_profiles')) {
            return;
        }

        Schema::table('marketing_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('marketing_profiles', 'name')) {
                $table->string('name')->nullable()->after('workspace_id');
            }
            if (!Schema::hasColumn('marketing_profiles', 'business_name')) {
                $table->string('business_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('marketing_profiles', 'industry')) {
                $table->string('industry')->nullable()->after('business_name');
            }
            if (!Schema::hasColumn('marketing_profiles', 'brand_kit_id')) {
                $table->unsignedBigInteger('brand_kit_id')->nullable()->after('industry');
            }
            if (!Schema::hasColumn('marketing_profiles', 'main_offer')) {
                $table->text('main_offer')->nullable()->after('brand_kit_id');
            }
            if (!Schema::hasColumn('marketing_profiles', 'budget')) {
                $table->string('budget')->nullable()->after('constraints');
            }
            if (!Schema::hasColumn('marketing_profiles', 'currency')) {
                $table->string('currency', 40)->nullable()->after('budget');
            }
        });

        // Back-fill: any pre-existing profile becomes a named "Default project"
        // so it surfaces in the new multi-project picker. Only touches rows that
        // have not yet been named (idempotent on re-run).
        if (Schema::hasColumn('marketing_profiles', 'name')) {
            DB::table('marketing_profiles')
                ->whereNull('name')
                ->orWhere('name', '')
                ->update(['name' => 'Default project']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketing_profiles')) {
            return;
        }

        Schema::table('marketing_profiles', function (Blueprint $table) {
            foreach (['name', 'business_name', 'industry', 'brand_kit_id', 'main_offer', 'budget', 'currency'] as $col) {
                if (Schema::hasColumn('marketing_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
