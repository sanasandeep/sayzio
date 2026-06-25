<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_assistant_low_balance_clicks')) {
            Schema::create('site_assistant_low_balance_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->nullable()
                    ->constrained('site_assistant_conversations')->nullOnDelete();
                $table->foreignId('user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->string('surface', 16)->index();
                $table->string('target_url', 500);
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['surface', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assistant_low_balance_clicks');
    }
};
