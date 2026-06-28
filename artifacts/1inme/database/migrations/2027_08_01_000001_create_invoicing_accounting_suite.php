<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoicing & Accounting Suite (Task #2703).
 *
 * Generalizes the existing client-invoice foundation into a full
 * business-billing toolkit: per-user billing companies, an item & tax
 * catalog, a tax-rule engine, expenses, recurring invoices, receipts,
 * plus generalization columns on the existing `invoices` table and a
 * CRM/inbox link on `inbox_thread_conversions`.
 *
 * Everything is ADDITIVE and guarded (shared RDS — additive only).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('billing_companies')) {
            Schema::create('billing_companies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->string('name', 190);
                $table->string('legal_name', 190)->nullable();
                $table->string('logo_path', 255)->nullable();
                $table->string('email', 190)->nullable();
                $table->string('phone', 64)->nullable();
                $table->string('website', 190)->nullable();
                $table->string('address_line1', 190)->nullable();
                $table->string('address_line2', 190)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('state', 120)->nullable();
                $table->string('postal_code', 32)->nullable();
                $table->string('country', 2)->nullable();
                $table->string('tax_id_label', 64)->nullable();   // e.g. "GSTIN", "VAT"
                $table->string('tax_id_value', 64)->nullable();
                $table->string('secondary_tax_label', 64)->nullable();
                $table->string('secondary_tax_value', 64)->nullable();
                $table->string('default_currency', 3)->default('USD');
                $table->string('invoice_prefix', 16)->nullable();
                $table->unsignedBigInteger('default_tax_rule_id')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['user_id', 'is_default']);
                $table->index(['workspace_id']);
            });
        }

        if (!Schema::hasTable('tax_rules')) {
            Schema::create('tax_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->string('name', 120);                  // "VAT 20%", "GST 18%"
                $table->unsignedInteger('rate_bps')->default(0); // basis points (2000 = 20%)
                $table->boolean('inclusive')->default(false); // price includes tax
                $table->boolean('is_compound')->default(false);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index(['billing_company_id']);
            });
        }

        if (!Schema::hasTable('catalog_categories')) {
            Schema::create('catalog_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->string('name', 120);
                $table->string('kind', 12)->default('item'); // item | expense | both
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'kind']);
            });
        }

        if (!Schema::hasTable('catalog_items')) {
            Schema::create('catalog_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('name', 190);
                $table->text('description')->nullable();
                $table->unsignedBigInteger('unit_price_minor')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('tax_rule_id')->nullable();
                $table->string('sku', 64)->nullable();
                $table->string('unit_label', 32)->nullable(); // "hour", "unit"
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index(['category_id']);
                $table->index(['billing_company_id']);
            });
        }

        if (!Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('vendor', 190)->nullable();
                $table->string('description', 240)->nullable();
                $table->date('spent_at');
                $table->unsignedBigInteger('amount_minor')->default(0);
                $table->unsignedBigInteger('tax_minor')->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('attachment_path', 255)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'spent_at']);
                $table->index(['billing_company_id', 'spent_at']);
                $table->index(['category_id']);
            });
        }

        if (!Schema::hasTable('recurring_invoices')) {
            Schema::create('recurring_invoices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('workspace_id')->nullable();
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->unsignedBigInteger('vault_client_id')->nullable();
                $table->string('title', 190)->nullable();
                $table->string('recipient_email', 190)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->json('line_items')->nullable();
                $table->unsignedBigInteger('discount_minor')->default(0);
                $table->unsignedBigInteger('tax_rule_id')->nullable();
                $table->text('notes_md')->nullable();
                $table->string('interval', 12)->default('monthly'); // weekly|monthly|quarterly|yearly
                $table->unsignedInteger('interval_count')->default(1);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->unsignedInteger('max_occurrences')->nullable();
                $table->unsignedInteger('occurrences_count')->default(0);
                $table->date('next_run_date')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->string('status', 12)->default('active'); // active|paused|cancelled|completed
                $table->boolean('auto_send')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['status', 'next_run_date']);
            });
        }

        if (!Schema::hasTable('receipts')) {
            Schema::create('receipts', function (Blueprint $table) {
                $table->id();
                $table->string('number', 48);
                $table->string('financial_year', 16)->nullable();
                $table->unsignedInteger('seq')->nullable();
                $table->unsignedBigInteger('invoice_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('billing_company_id')->nullable();
                $table->string('currency', 3)->default('USD');
                $table->unsignedBigInteger('amount_minor')->default(0);
                $table->string('method', 12)->default('online'); // online | manual
                $table->string('gateway', 32)->nullable();
                $table->string('gateway_ref', 190)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('snapshot')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->index(['invoice_id']);
                $table->index(['user_id']);
                $table->unique(['number']);
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'billing_company_id')) {
                $table->unsignedBigInteger('billing_company_id')->nullable()->after('vault_client_id');
            }
            if (!Schema::hasColumn('invoices', 'recurring_invoice_id')) {
                $table->unsignedBigInteger('recurring_invoice_id')->nullable()->after('billing_company_id');
            }
            if (!Schema::hasColumn('invoices', 'inbox_thread_id')) {
                $table->unsignedBigInteger('inbox_thread_id')->nullable()->after('recurring_invoice_id');
            }
            if (!Schema::hasColumn('invoices', 'amount_paid_minor')) {
                $table->unsignedBigInteger('amount_paid_minor')->default(0)->after('grand_total_minor');
            }
            if (!Schema::hasColumn('invoices', 'paid_method')) {
                $table->string('paid_method', 16)->nullable()->after('gateway');
            }
        });

        if (Schema::hasTable('inbox_thread_conversions')
            && !Schema::hasColumn('inbox_thread_conversions', 'invoice_id')) {
            Schema::table('inbox_thread_conversions', function (Blueprint $table) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inbox_thread_conversions')
            && Schema::hasColumn('inbox_thread_conversions', 'invoice_id')) {
            Schema::table('inbox_thread_conversions', function (Blueprint $table) {
                $table->dropColumn('invoice_id');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            foreach (['billing_company_id', 'recurring_invoice_id', 'inbox_thread_id', 'amount_paid_minor', 'paid_method'] as $col) {
                if (Schema::hasColumn('invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('receipts');
        Schema::dropIfExists('recurring_invoices');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('catalog_categories');
        Schema::dropIfExists('tax_rules');
        Schema::dropIfExists('billing_companies');
    }
};
