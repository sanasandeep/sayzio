<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle schema (task-194): proration, grace, refunds, credit notes.
 *
 *  - plans.grace_days / plans.refund_window_days: admin-editable policy
 *    knobs. Default 7 each.
 *  - subscriptions.cancel_at_period_end: user-requested cancellation at
 *    the end of the current paid window (different from cancel_at which
 *    is a hard stop).
 *  - subscriptions.replaced_by_id: set when an upgrade supersedes this
 *    row so the lifecycle timeline can walk the chain.
 *  - refunds: one row per refund action.
 *  - credit_notes: issued alongside each successful refund. Numbering
 *    shares invoice_counters with prefix "CN".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (!Schema::hasColumn('plans', 'grace_days')) {
                $table->unsignedInteger('grace_days')->default(7)->after('trial_days');
            }
            if (!Schema::hasColumn('plans', 'refund_window_days')) {
                $table->unsignedInteger('refund_window_days')->default(7)->after('grace_days');
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('cancel_at');
            }
            if (!Schema::hasColumn('subscriptions', 'replaced_by_id')) {
                $table->foreignId('replaced_by_id')->nullable()->after('cancel_at_period_end')
                    ->constrained('subscriptions')->nullOnDelete();
            }
            if (!Schema::hasColumn('subscriptions', 'grace_until')) {
                $table->timestamp('grace_until')->nullable()->after('replaced_by_id');
                $table->index(['status', 'current_period_end']);
                $table->index(['status', 'grace_until']);
            }
        });

        if (!Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('amount_minor');
                $table->string('currency', 3);
                $table->string('status', 32)->default('pending'); // pending|succeeded|failed
                $table->string('gateway', 32);
                $table->string('gateway_ref', 190)->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('user_initiated')->default(false);
                $table->boolean('downgrade_on_success')->default(true);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->index(['invoice_id', 'status']);
                $table->unique(['gateway', 'gateway_ref']);
            });
        }

        if (!Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->string('number')->unique();
                $table->string('financial_year', 7);
                $table->unsignedInteger('seq');
                $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
                $table->foreignId('invoice_id')->constrained();
                $table->foreignId('user_id')->constrained();
                $table->string('currency', 3);
                $table->unsignedBigInteger('amount_minor');
                $table->json('snapshot')->nullable(); // frozen copy of invoice header + reason
                $table->timestamp('issued_at');
                $table->timestamps();
                $table->index(['user_id', 'issued_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('refunds');
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['replaced_by_id']);
            $table->dropColumn(['cancel_at_period_end', 'replaced_by_id', 'grace_until']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['grace_days', 'refund_window_days']);
        });
    }
};
