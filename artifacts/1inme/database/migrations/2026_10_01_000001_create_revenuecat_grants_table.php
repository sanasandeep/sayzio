<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for RevenueCat-backed subscription activations.
 *
 * Every time the mobile client tells the API "RevenueCat says this user
 * just bought / now holds entitlement X", the controller verifies the
 * claim against RevenueCat's REST API and then records a row here keyed
 * by the RC original_transaction_id. The unique constraint stops a
 * re-delivered client call from invoicing twice for the same purchase.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('revenuecat_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('cycle', 16);
            $table->string('app_user_id', 190);
            $table->string('entitlement', 190);
            $table->string('product_identifier', 190)->nullable();
            $table->string('original_transaction_id', 190);
            $table->string('store', 32)->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique('original_transaction_id');
            $table->index(['user_id', 'entitlement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenuecat_grants');
    }
};
