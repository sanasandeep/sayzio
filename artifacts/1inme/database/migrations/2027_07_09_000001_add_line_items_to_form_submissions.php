<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-field / variable form pricing (Task #2321). Stores the priced
 * line-item breakdown that produced a paid submission's amount_cents so
 * the owner can see exactly what was charged (add-ons, tiers, quantity).
 * Additive + hasColumn-guarded for idempotent re-runs over the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('form_submissions')) {
            return;
        }

        Schema::table('form_submissions', function (Blueprint $t) {
            if (!Schema::hasColumn('form_submissions', 'line_items')) {
                // [{field, label, detail, amount_cents}] — null for fixed-price
                // or free submissions.
                $t->json('line_items')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive, shared-DB-safe — intentionally no destructive rollback.
    }
};
