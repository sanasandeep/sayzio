<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dialer_call_events')) {
            Schema::create('dialer_call_events', function (Blueprint $table) {
                $table->id();
                // The account whose phone reported the event.
                $table->unsignedBigInteger('user_id')->index();
                // 'ringing' | 'answered' | 'ended'
                $table->string('status', 16);
                // Raw caller number as the phone saw it (may not be E.164).
                $table->string('number', 32);
                // Directory name the caller resolved to at ring time (if any).
                $table->string('caller_name', 191)->nullable();
                // When the event happened on the phone (epoch supplied by app).
                $table->timestamp('occurred_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dialer_call_events');
    }
};
