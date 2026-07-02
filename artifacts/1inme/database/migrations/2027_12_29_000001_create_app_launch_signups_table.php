<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_launch_signups')) {
            Schema::create('app_launch_signups', function (Blueprint $table) {
                $table->id();
                $table->string('email', 190);
                // Which store badge opened the modal ('play' or 'app') — lets
                // the admin see platform interest at a glance.
                $table->string('store', 20)->nullable();
                $table->string('ip', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                // Stamped once the launch announcement email has been sent so
                // a future notifier never double-sends.
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->unique('email');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_launch_signups');
    }
};
