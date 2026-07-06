<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * State table for the Leads review queue (Task #3728). Rows are only
     * ever inserted when a captured person is acted on (approved into a
     * Contact, or dismissed) — a "pending" lead has no row here and is
     * derived live by LeadAggregator scanning the 8 source tables and
     * excluding anything already present below. Keeping this table
     * write-only-on-action avoids a backfill job for years of existing
     * RSVPs/orders/etc.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('status', 20)->default('approved');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'leads_source_unique');
            $table->index(['user_id', 'status']);
            $table->index('workspace_id');
            $table->index('contact_id');

            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
