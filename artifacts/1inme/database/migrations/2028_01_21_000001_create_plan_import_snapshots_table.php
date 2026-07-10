<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undo history for the admin pricing-plans CSV import (Task #4359).
 *
 * The CSV import applies pricing/feature changes to many plans in one confirm
 * step, so a single mis-typed cell can silently corrupt the live lineup with no
 * one-click way back. Each committed import records a before-snapshot of every
 * plan (core columns + features blob + the polymorphic price rows) here, so an
 * admin can review recent imports on /admin/plans and revert the most recent one
 * to exactly the state that existed just before it ran.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('plan_import_snapshots')) {
            return;
        }

        Schema::create('plan_import_snapshots', function (Blueprint $table) {
            $table->id();
            // Who ran the import (admin guard). Nullable + set-null on delete so
            // the audit row survives if the admin account is later removed.
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            // Human-readable summary of what happened.
            $table->unsignedInteger('plans_updated')->default(0);
            $table->unsignedInteger('rows_skipped')->default(0);
            // Slugs/names of the plans that actually changed (for the list UI).
            $table->json('changed')->nullable();
            // Full before-state of every plan at commit time (the undo payload).
            $table->json('snapshot');
            // Revert bookkeeping so an import can only be undone once.
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();
            $table->string('reverted_by_name')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_import_snapshots');
    }
};
