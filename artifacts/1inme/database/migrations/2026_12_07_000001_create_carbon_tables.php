<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Workspace-wide carbon settings live in a generic JSON column
        // so future workspace-level toggles (carbon, branding, etc.) can
        // share the same field without another migration.
        if (!Schema::hasColumn('workspaces', 'settings')) {
            Schema::table('workspaces', function (Blueprint $t) {
                $t->json('settings')->nullable();
            });
        }

        // One snapshot row per (link, calendar month). Holds the inputs
        // we used (page views + bytes + device mix) so the methodology
        // popover can show the math, plus the resulting grams CO2 and
        // an optional pointer to the offset purchase that funded it.
        if (!Schema::hasTable('biolink_carbon_snapshots')) {
            Schema::create('biolink_carbon_snapshots', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->unsignedBigInteger('link_id')->index();
                $t->date('period_start');                      // first day of the month
                $t->date('period_end');                        // last day of the month
                $t->unsignedInteger('page_views');
                $t->unsignedInteger('avg_bytes_per_view');     // estimated avg page weight
                $t->json('device_mix');                        // {desktop:%, mobile:%, tablet:%}
                $t->json('country_mix');                       // {US:%, IN:%, ...} top-N
                $t->decimal('grid_intensity_g_per_kwh', 8, 2); // weighted avg g CO2 / kWh
                $t->decimal('grams_co2', 14, 2);               // estimated for the period
                $t->decimal('grams_offset', 14, 2)->default(0);
                $t->string('offset_status', 24)->default('none');  // none|pending|purchased|failed|capped
                $t->unsignedBigInteger('offset_purchase_id')->nullable()->index();
                $t->json('model_breakdown')->nullable();       // raw inputs for the popover
                $t->string('model_version', 16)->default('swd-v4');
                $t->timestamps();
                $t->unique(['link_id', 'period_start'], 'carbon_snap_link_period');
            });
        }

        // Each successful (or attempted) offset purchase. Cost is tracked
        // in workspace billing currency minor units so this row can act
        // as the audit/source for invoice line items.
        if (!Schema::hasTable('carbon_offset_purchases')) {
            Schema::create('carbon_offset_purchases', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('workspace_id')->index();
                $t->unsignedBigInteger('link_id')->nullable()->index();
                $t->string('provider', 32);                    // cloverly|patch|null
                $t->string('provider_ref', 128)->nullable();   // provider-side id
                $t->decimal('grams_offset', 14, 2);
                $t->string('currency', 3)->default('USD');
                $t->unsignedInteger('cost_minor');             // in `currency` minor units
                $t->string('status', 24)->default('pending');  // pending|succeeded|failed|sandbox
                $t->string('certificate_url', 512)->nullable();
                $t->string('project_name', 160)->nullable();
                $t->json('raw')->nullable();                   // provider response snapshot
                $t->unsignedBigInteger('invoice_id')->nullable()->index();
                $t->timestamp('purchased_at')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_offset_purchases');
        Schema::dropIfExists('biolink_carbon_snapshots');
        if (Schema::hasColumn('workspaces', 'settings')) {
            Schema::table('workspaces', function (Blueprint $t) {
                $t->dropColumn('settings');
            });
        }
    }
};
