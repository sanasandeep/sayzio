<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `invoices`: per-FY sequential invoice records. The full address +
 * line items + tax breakdown are JSON-snapshotted at issue time so
 * later edits to the user's profile never mutate past invoices.
 *
 * `invoice_counters`: one row per (financial_year, prefix) holding
 * the last sequence number used. The reservation API takes a row
 * lock (`SELECT ... FOR UPDATE`) before incrementing so concurrent
 * requests can never produce duplicate or skipped numbers.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->id();
            $table->string('financial_year', 9); // e.g. "2025-26"
            $table->string('prefix', 16);
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamps();
            $table->unique(['financial_year', 'prefix']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('financial_year', 9)->index();
            $table->unsignedBigInteger('seq');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3);
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('tax_total_minor');
            $table->unsignedBigInteger('grand_total_minor');
            $table->json('billing_address_snapshot');
            $table->json('merchant_snapshot');
            $table->json('line_items');
            $table->json('tax_breakdown');
            $table->string('reverse_charge_note')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_counters');
    }
};
