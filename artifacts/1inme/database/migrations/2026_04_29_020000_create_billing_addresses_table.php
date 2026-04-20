<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One billing address per user. Holds the legal-entity-side info
 * needed for tax-correct invoicing: country, state/region, postal
 * code, optional business name, optional GSTIN/VATIN tax-id with
 * its kind tag.
 *
 * Kept separate from `users` because (a) it's a logically different
 * concept (billing != contact), (b) we'll later support multiple
 * billing addresses per org, and (c) we snapshot it onto each
 * invoice so future address edits never alter past invoices.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('country', 2);
            $table->string('region', 8)->nullable();   // IN state code, EU not needed
            $table->string('postal_code', 16)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('business_name')->nullable();
            $table->string('tax_id', 32)->nullable();
            $table->string('tax_id_kind', 16)->nullable(); // GSTIN | VATIN | NONE
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_addresses');
    }
};
