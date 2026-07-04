<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3523 — "AI staff": user-configurable AI agents that operate over
 * an existing Sayzio domain (billing, contacts, inbox, general). Each
 * staff member is a lightweight identity (name + personality/instructions)
 * that AiStaffRuntime grounds with live domain data via
 * AiMindFeatureAdapter and charges through the existing OpenAiService /
 * AiUsageCharger coin pipeline — no separate agent runtime.
 *
 * `ai_staff_suggestions` mirrors MarketingStrategySuggestion's
 * confirm-before-act pattern (pending -> applied/dismissed/error) for the
 * billing domain's "draft invoice" / "chase unpaid invoice" actions, so
 * nothing is created or sent without an explicit owner confirmation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_staff')) {
            Schema::create('ai_staff', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('domain', 32); // billing | contacts | inbox | general
                $table->text('instructions')->nullable();
                $table->boolean('is_disabled')->default(false);
                $table->json('config')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'domain']);
            });
        }

        if (!Schema::hasTable('ai_staff_suggestions')) {
            Schema::create('ai_staff_suggestions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_staff_id')->constrained('ai_staff')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('kind', 40); // draft_invoice | chase_invoice
                $table->string('status', 20)->default('pending'); // pending|applied|dismissed|error
                $table->json('payload')->nullable();
                $table->string('title', 190)->nullable();
                $table->text('message')->nullable();
                $table->string('applied_ref_type', 190)->nullable();
                $table->unsignedBigInteger('applied_ref_id')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['ai_staff_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_staff_suggestions');
        Schema::dropIfExists('ai_staff');
    }
};
