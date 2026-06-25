<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('link_clicks')) {
            Schema::create('link_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('link_id')->constrained()->cascadeOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->string('country_code', 2)->nullable();
                $table->string('city')->nullable();
                $table->string('browser')->nullable();
                $table->string('os')->nullable();
                $table->string('device_type')->nullable();
                $table->string('referrer')->nullable();
                $table->string('language', 10)->nullable();
                $table->jsonb('utm_params')->nullable();
                $table->timestamp('clicked_at');

                $table->index(['link_id', 'clicked_at']);
                $table->index(['link_id', 'country_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
    }
};
