<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout / subscriptions / payments schema (task-193).
 *
 * subscriptions: one row per active plan grant. current_period_start/end
 *   describe the currently-paid window; renewal or upgrade creates a new
 *   period. gateway + gateway_subscription_id are nullable so offline &
 *   one-off activations can share the table.
 *
 * subscription_addons: attached addons per subscription with a qty so
 *   metered/seat addons can be represented.
 *
 * payment_attempts: every handoff/webhook touch for an invoice. gateway_ref
 *   is unique per gateway for webhook idempotency.
 *
 * gateway_settings: admin-managed, credentials encrypted on the model.
 *
 * invoices: extended with subscription_id, gateway, status, paid_at so a
 *   single invoice record can carry both issuance (from tax engine) and
 *   payment lifecycle. status defaults to 'paid' so previously-issued
 *   invoices (created when activation already implied payment) are not
 *   retroactively re-queued.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status', 32)->default('pending'); // pending|active|cancelled|expired
            $table->string('billing_cycle', 16)->default('monthly');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->string('gateway', 32)->nullable();
            $table->string('gateway_subscription_id', 190)->nullable();
            $table->string('currency', 3);
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['gateway', 'gateway_subscription_id']);
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained('addons');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
            $table->unique(['subscription_id', 'addon_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('user_id')->constrained('subscriptions')->nullOnDelete();
            $table->string('gateway', 32)->nullable()->after('subscription_id');
            $table->string('status', 32)->default('paid')->after('gateway');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->index(['status']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 32);
            $table->string('gateway_ref', 190)->nullable();
            $table->string('status', 32)->default('initiated'); // initiated|pending|succeeded|failed|requires_review
            $table->json('raw_response')->nullable();
            $table->timestamp('signature_verified_at')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'gateway_ref']);
            $table->index(['status']);
        });

        Schema::create('gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_slug', 32)->unique();
            $table->string('display_name');
            $table->string('mode', 16)->default('test'); // test|live
            $table->text('credentials_encrypted')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_settings');
        Schema::dropIfExists('payment_attempts');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropColumn(['subscription_id', 'gateway', 'status', 'paid_at']);
        });
        Schema::dropIfExists('subscription_addons');
        Schema::dropIfExists('subscriptions');
    }
};
