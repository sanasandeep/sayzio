<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_columns', fn (Blueprint $t) => $t->json('transitions')->nullable());
        Schema::table('task_time_entries', fn (Blueprint $t) => $t->unsignedBigInteger('attendance_participant_id')->nullable()->index());
        Schema::create('task_tracking_preferences', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('card_id');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_tracking_preferences');
        Schema::table('task_time_entries', fn (Blueprint $t) => $t->dropColumn('attendance_participant_id'));
        Schema::table('task_columns', fn (Blueprint $t) => $t->dropColumn('transitions'));
    }
};
