<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inbox_forward_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('label', 120);
            $t->string('type', 16); // email | webhook
            $t->string('target', 500); // email address or URL
            $t->string('method', 8)->default('POST'); // webhook method
            $t->json('sources')->nullable(); // null/empty = all
            $t->string('header_key', 120)->nullable();
            $t->string('header_value', 500)->nullable();
            $t->string('secret', 120)->nullable(); // HMAC signing key (webhook)
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_delivered_at')->nullable();
            $t->string('last_status', 32)->nullable();
            $t->timestamps();

            $t->index(['user_id', 'is_active']);
        });

        Schema::create('inbox_forward_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('destination_id')->constrained('inbox_forward_destinations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('source_type', 32);
            $t->unsignedBigInteger('source_id');
            $t->string('status', 16)->default('pending'); // pending | success | failed | dead
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('last_error')->nullable();
            $t->unsignedSmallInteger('last_response_code')->nullable();
            $t->timestamp('last_attempt_at')->nullable();
            $t->timestamp('next_retry_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->json('payload_snapshot')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'status', 'created_at']);
            $t->index(['status', 'next_retry_at']);
            $t->index(['destination_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_forward_deliveries');
        Schema::dropIfExists('inbox_forward_destinations');
    }
};
