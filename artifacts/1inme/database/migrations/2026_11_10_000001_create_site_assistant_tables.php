<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_assistant_page_hints', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('route_pattern', 200);
            $table->string('surface', 16)->default('any')->index(); // marketing|app|any
            $table->text('description')->nullable();
            $table->jsonb('suggested_actions')->nullable();
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_assistant_response_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('kind', 16); // buttons|list|form|image
            $table->jsonb('payload');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token', 64)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('surface', 16)->default('marketing'); // marketing|app
            $table->string('visitor_name', 120)->nullable();
            $table->string('visitor_email', 200)->nullable();
            $table->string('visitor_ip', 64)->nullable();
            $table->string('visitor_ua', 255)->nullable();
            $table->string('last_route', 250)->nullable();
            $table->string('last_page_title', 250)->nullable();
            $table->boolean('is_disabled')->default(false);
            $table->boolean('handed_off')->default(false);
            $table->unsignedBigInteger('contact_message_id')->nullable()->index();
            $table->integer('turns_count')->default(0);
            $table->integer('credits_spent')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('site_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('role', 16); // user|assistant|system
            $table->text('content')->nullable();
            $table->jsonb('blocks')->nullable();   // rich response blocks
            $table->jsonb('citations')->nullable();
            $table->jsonb('meta')->nullable();     // page url, choice payload, form values
            $table->integer('credits_spent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assistant_messages');
        Schema::dropIfExists('site_assistant_conversations');
        Schema::dropIfExists('site_assistant_response_templates');
        Schema::dropIfExists('site_assistant_page_hints');
    }
};
