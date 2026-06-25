<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('site_assistant_cutoff_alerts')) {
            Schema::create('site_assistant_cutoff_alerts', function (Blueprint $table) {
                $table->id();
                $table->timestamp('dispatched_at')->index();
                $table->unsignedSmallInteger('abandon_rate');
                $table->unsignedSmallInteger('threshold');
                $table->unsignedInteger('total');
                $table->unsignedInteger('retried');
                $table->unsignedSmallInteger('window_hours')->default(24);
                $table->unsignedInteger('in_app_delivered')->default(0);
                $table->unsignedInteger('emails_sent')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_assistant_cutoff_alerts');
    }
};
