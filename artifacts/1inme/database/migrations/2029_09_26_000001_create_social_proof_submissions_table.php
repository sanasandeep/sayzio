<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared submission store for collector/feedback Buzz templates (task #6179).
 * Every data-collecting notification type (email/SMS/request/webinar/survey/
 * feedback/etc.) lands here; owners view + export per campaign.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_proof_submissions')) return;

        Schema::create('social_proof_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_proof_id')->constrained('social_proofs')->cascadeOnDelete();
            $table->string('notification_id', 64)->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('name', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('message')->nullable();
            $table->string('answer', 300)->nullable();
            $table->smallInteger('rating')->nullable();
            $table->string('page_url', 1000)->nullable();
            $table->string('ip', 64)->nullable();
            $table->boolean('is_spam')->default(false);
            $table->timestamps();

            $table->index(['social_proof_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_proof_submissions');
    }
};
