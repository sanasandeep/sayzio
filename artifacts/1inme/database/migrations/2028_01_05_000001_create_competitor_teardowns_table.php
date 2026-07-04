<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task #3532 — Competitor Biolink Teardown. Paste a competitor's public
 * link-in-bio (or any page) URL; we fetch + extract the page server-side
 * (no AI spend on a fetch failure), then ask OpenAI to score it and
 * surface strengths/weaknesses/missing elements/CTA quality. Charged
 * through the standard `competitor_teardown` AI feature via
 * OpenAiService/AiUsageCharger, with the usual refund-on-failure
 * behaviour. A completed teardown can be turned into a new biolink via
 * the existing AiBiolinkBuilderService ("Build me a better version"),
 * recorded on `built_link_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competitor_teardowns')) {
            return;
        }

        Schema::create('competitor_teardowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('competitor_url', 2048);
            // pending | processing | completed | failed
            $table->string('status', 20)->default('pending');
            $table->json('extracted')->nullable();
            $table->json('analysis')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->string('error', 500)->nullable();
            $table->foreignId('built_link_id')->nullable()->constrained('links')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_teardowns');
    }
};
