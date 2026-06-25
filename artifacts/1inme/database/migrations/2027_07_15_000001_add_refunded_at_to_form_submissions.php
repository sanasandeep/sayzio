<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refund a paid form submission (Task #2322). Additive, shared-DB-safe
 * timestamp recording when an owner refunded a paid submission. The
 * `payment_status` column already supports the 'refunded' value; this
 * column simply records when it happened. hasColumn-guarded for
 * idempotent re-runs over the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('form_submissions')) {
            return;
        }

        Schema::table('form_submissions', function (Blueprint $t) {
            if (!Schema::hasColumn('form_submissions', 'refunded_at')) {
                $t->timestamp('refunded_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive, shared-DB-safe — intentionally no destructive rollback.
    }
};
