<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('forms')) {
            Schema::create('forms', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
                $t->string('slug', 80)->unique();
                $t->string('title');
                $t->text('description')->nullable();
                $t->jsonb('fields')->nullable();        // ordered array of field definitions
                $t->jsonb('design')->nullable();        // theme / colors / layout
                $t->jsonb('settings')->nullable();      // multi-step, redirect, success, captcha…
                $t->jsonb('notifications')->nullable(); // email, sms, webhooks
                $t->boolean('is_active')->default(true);
                $t->boolean('is_multi_step')->default(false);
                $t->unsignedBigInteger('total_views')->default(0);
                $t->unsignedBigInteger('total_submissions')->default(0);
                $t->timestamps();
                $t->index(['user_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('form_submissions')) {
            Schema::create('form_submissions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('form_id')->constrained()->cascadeOnDelete();
                $t->jsonb('data');
                $t->jsonb('files')->nullable();
                $t->string('ip', 64)->nullable();
                $t->string('user_agent', 512)->nullable();
                $t->string('referrer', 512)->nullable();
                $t->string('country', 2)->nullable();
                $t->boolean('is_spam')->default(false);
                $t->boolean('is_read')->default(false);
                $t->boolean('is_starred')->default(false);
                $t->timestamps();
                $t->index(['form_id', 'created_at']);
                $t->index(['form_id', 'is_read']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
