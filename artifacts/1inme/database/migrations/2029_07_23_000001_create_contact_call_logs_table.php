<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured call history for contacts (Dialer caller-ID drains). Replaces
 * the notes-append v1 with a real table the contact profile can render as a
 * timeline. Additive-only; hasTable-guarded for the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contact_call_logs')) {
            Schema::create('contact_call_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
                // Raw caller number as the screening service saw it.
                $table->string('number', 64);
                // incoming today; room for outgoing/missed later.
                $table->string('direction', 16)->default('incoming');
                $table->timestamp('occurred_at');
                $table->timestamps();

                // Idempotency: re-drained native queue events upsert cleanly.
                $table->unique(['contact_id', 'number', 'occurred_at'], 'contact_call_logs_dedupe');
                $table->index(['contact_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_call_logs');
    }
};
