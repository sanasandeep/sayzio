<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid forms (Task #2319). Additive, shared-DB-safe columns so a paid
 * form submission can be held pending, sent to the creator's gateway,
 * and reconciled on return. hasColumn-guarded for idempotent re-runs
 * over the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('form_submissions')) {
            return;
        }

        Schema::table('form_submissions', function (Blueprint $t) {
            if (!Schema::hasColumn('form_submissions', 'payment_status')) {
                // none | pending | paid | refunded
                $t->string('payment_status', 16)->default('none')->index();
            }
            if (!Schema::hasColumn('form_submissions', 'amount_cents')) {
                $t->unsignedBigInteger('amount_cents')->nullable();
            }
            if (!Schema::hasColumn('form_submissions', 'currency')) {
                $t->string('currency', 8)->nullable();
            }
            if (!Schema::hasColumn('form_submissions', 'gateway')) {
                $t->string('gateway', 32)->nullable();
            }
            if (!Schema::hasColumn('form_submissions', 'gateway_charge_id')) {
                $t->string('gateway_charge_id', 191)->nullable();
            }
            if (!Schema::hasColumn('form_submissions', 'paid_at')) {
                $t->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive, shared-DB-safe — intentionally no destructive rollback.
    }
};
