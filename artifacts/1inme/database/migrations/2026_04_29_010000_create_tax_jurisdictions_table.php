<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax jurisdictions: country / region / kind / rate.
 *
 * Indian states need TWO rows per state — the intra-state CGST+SGST
 * combined view (kind = 'GST_INTRA') and the inter-state IGST view
 * (kind = 'GST_INTER'). The TaxCalculator picks one based on
 * place-of-supply vs the merchant's GST state.
 *
 * EU member states have one row each (kind = 'VAT'). UK is one row.
 * "Rest of world" is implicit — TaxCalculator returns 0% when no
 * matching active row exists for the buyer's country.
 *
 * `b2b_reverse_charge` flags rows where a valid B2B tax-id from a
 * different jurisdiction zeroes the tax (EU intra-community supplies).
 *
 * `effective_from` / `effective_to` allow rate changes without losing
 * historical correctness on past invoices.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2)->index();
            $table->string('region', 8)->nullable()->index();
            $table->string('kind', 16); // GST_INTRA | GST_INTER | VAT | SALES | NONE
            $table->string('label')->nullable();
            $table->decimal('rate_percent', 6, 3); // e.g. 18.000, 9.000, 20.000
            $table->boolean('b2b_reverse_charge')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country', 'region', 'kind', 'is_active'], 'tax_juris_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_jurisdictions');
    }
};
