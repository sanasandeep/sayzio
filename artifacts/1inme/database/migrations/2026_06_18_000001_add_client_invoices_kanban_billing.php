<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema for "invoices generated from kanban cards":
 *  - Adds billable / rate / paid-link fields to task_cards.
 *  - Adds billed_column_id to task_boards (auto-move target on payment).
 *  - Creates task_time_entries to log start/stop or manual minutes per card.
 *  - Extends invoices with a kind discriminator + workspace/client/recipient
 *    columns + discount + due_date + notes_md so the existing model & PDF
 *    pipeline serve both subscription invoices AND client invoices.
 *  - Creates client_invoice_cards pivot so cards know which invoice they
 *    were billed on (and an invoice knows its source cards).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_cards', function (Blueprint $table) {
            $table->boolean('billable')->default(false)->after('priority');
            // 'hourly' or 'flat'.
            $table->string('rate_type', 8)->nullable()->after('billable');
            // Stored in minor units (cents/paise) to match the rest of billing.
            $table->unsignedBigInteger('rate_amount_minor')->nullable()->after('rate_type');
            $table->unsignedBigInteger('client_invoice_id')->nullable()->after('rate_amount_minor');
            $table->index(['workspace_id', 'billable']);
            $table->index('client_invoice_id');
        });

        Schema::table('task_boards', function (Blueprint $table) {
            $table->unsignedBigInteger('billed_column_id')->nullable()->after('description');
        });

        Schema::create('task_time_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('card_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('started_at')->nullable();
            // Null while the timer is still running.
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('minutes')->default(0);
            $table->string('note', 240)->nullable();
            // 'timer' = clocked via start/stop, 'manual' = entered by hand.
            $table->string('source', 8)->default('timer');
            $table->unsignedBigInteger('client_invoice_id')->nullable();
            $table->timestamps();

            $table->index(['card_id', 'started_at']);
            $table->index(['workspace_id', 'card_id']);
            $table->index(['card_id', 'ended_at']);
            $table->index('client_invoice_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // 'subscription' (existing rows) or 'client' (new client invoices).
            $table->string('kind', 16)->default('subscription')->after('user_id');
            $table->unsignedBigInteger('workspace_id')->nullable()->after('kind');
            $table->unsignedBigInteger('vault_client_id')->nullable()->after('workspace_id');
            $table->string('recipient_email', 190)->nullable()->after('vault_client_id');
            $table->unsignedBigInteger('discount_minor')->default(0)->after('grand_total_minor');
            $table->date('due_date')->nullable()->after('discount_minor');
            $table->text('notes_md')->nullable()->after('due_date');
            $table->timestamp('sent_at')->nullable()->after('notes_md');

            $table->index(['kind', 'status']);
            $table->index(['workspace_id', 'kind']);
        });

        Schema::create('client_invoice_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('card_id');
            $table->timestamps();

            $table->unique(['invoice_id', 'card_id']);
            $table->index('card_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_invoice_cards');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'kind', 'workspace_id', 'vault_client_id', 'recipient_email',
                'discount_minor', 'due_date', 'notes_md', 'sent_at',
            ]);
        });
        Schema::dropIfExists('task_time_entries');
        Schema::table('task_boards', function (Blueprint $table) {
            $table->dropColumn('billed_column_id');
        });
        Schema::table('task_cards', function (Blueprint $table) {
            $table->dropColumn(['billable', 'rate_type', 'rate_amount_minor', 'client_invoice_id']);
        });
    }
};
