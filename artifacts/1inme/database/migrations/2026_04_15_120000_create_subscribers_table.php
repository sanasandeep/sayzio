<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscribers')) {
            Schema::create('subscribers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('link_id')->nullable()->constrained()->onDelete('set null');
                $table->unsignedBigInteger('block_id')->nullable();
                $table->string('type', 30)->default('email');
                $table->string('email')->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('name')->nullable();
                $table->string('channel_url', 500)->nullable();
                $table->string('status', 20)->default('active');
                $table->string('source', 50)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('subscribed_at')->useCurrent();
                $table->timestamp('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'type', 'status']);
                $table->index(['user_id', 'email']);
                $table->unique(['user_id', 'type', 'email']);
            });
        }

        if (!Schema::hasTable('subscriber_messages')) {
            Schema::create('subscriber_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('channel', 30)->default('email');
                $table->string('subject')->nullable();
                $table->text('body');
                $table->string('status', 20)->default('draft');
                $table->integer('recipients_count')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->json('filters')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_messages');
        Schema::dropIfExists('subscribers');
    }
};
