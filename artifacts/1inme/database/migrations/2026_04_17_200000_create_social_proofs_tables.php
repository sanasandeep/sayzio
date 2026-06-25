<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('social_proofs')) {
            Schema::create('social_proofs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('type', 40); // recent_activity | visitor_count | conversion_count | email_signup | countdown | review | custom_html
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();   // type-specific (text templates, dynamic-vs-simulated mode, fields…)
                $table->json('design')->nullable();     // colors, position, animation, shape, font
                $table->json('targeting')->nullable();  // pages include/exclude, devices, delay, interval, duration
                $table->json('schedule')->nullable();   // start_at, end_at, time_slots
                $table->unsignedBigInteger('impressions')->default(0);
                $table->unsignedBigInteger('clicks')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->timestamps();

                $table->index(['user_id', 'is_active']);
                $table->index('type');
            });
        }

        if (!Schema::hasTable('social_proof_items')) {
            Schema::create('social_proof_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_proof_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();        // e.g. "Sarah from London"
                $table->string('location')->nullable();
                $table->string('action')->nullable();      // e.g. "purchased Premium Plan"
                $table->string('image_url', 500)->nullable();
                $table->string('link_url', 1000)->nullable();
                $table->string('time_label')->nullable();  // e.g. "2 minutes ago" (override or auto)
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['social_proof_id', 'sort_order']);
            });
        }

        if (!Schema::hasTable('social_proof_events')) {
            Schema::create('social_proof_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_proof_id')->constrained()->cascadeOnDelete();
                $table->string('kind', 20); // impression | click | conversion
                $table->string('page_url', 1000)->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['social_proof_id', 'kind', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_proof_events');
        Schema::dropIfExists('social_proof_items');
        Schema::dropIfExists('social_proofs');
    }
};
