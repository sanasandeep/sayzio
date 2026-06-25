<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketing_events')) {
            Schema::create('marketing_events', function (Blueprint $table) {
                $table->id();
                $table->string('source', 64);
                $table->string('target', 64);
                $table->string('ip_address', 45)->nullable();
                $table->string('referrer', 1024)->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['source', 'target', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_events');
    }
};
