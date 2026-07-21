<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dialer notes/reminders with server sync + phone-based sharing.
 * Additive-only; hasTable-guarded for the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dialer_notes')) {
            Schema::create('dialer_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('title', 255)->nullable();
                $table->text('body')->nullable();
                // Optional phone this note is about (E.164), so it can be
                // surfaced from the dialer for that number.
                $table->string('number_e164', 32)->nullable();
                $table->timestamp('remind_at')->nullable();
                $table->boolean('done')->default(false);
                $table->string('color', 16)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'remind_at']);
                $table->index(['user_id', 'number_e164']);
            });
        }

        if (!Schema::hasTable('dialer_note_shares')) {
            Schema::create('dialer_note_shares', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dialer_note_id')->constrained('dialer_notes')->cascadeOnDelete();
                // Phone the owner shared with (E.164); resolved to a user via
                // linked_identifiers when possible so it shows in their list.
                $table->string('phone_e164', 32);
                $table->foreignId('shared_with_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['dialer_note_id', 'phone_e164']);
                $table->index('shared_with_user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dialer_note_shares');
        Schema::dropIfExists('dialer_notes');
    }
};
