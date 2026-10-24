<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('name', 120);
            $t->string('starts_at', 5);
            $t->string('ends_at', 5);
            $t->unsignedInteger('break_minutes')->default(0);
            $t->unsignedInteger('late_grace_minutes')->default(0);
            $t->unsignedInteger('early_grace_minutes')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('attendance_rosters', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->foreignId('participant_id')->constrained('attendance_participants');
            $t->unsignedBigInteger('shift_id')->nullable();
            $t->string('shift_name', 120)->nullable();
            $t->date('work_date');
            $t->string('kind')->default('work');
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('break_minutes')->default(0);
            $t->unsignedInteger('late_grace_minutes')->default(0);
            $t->unsignedInteger('early_grace_minutes')->default(0);
            $t->timestamps();
            $t->unique(['participant_id', 'work_date']);
            $t->index(['workspace_id', 'work_date']);
        });
        Schema::table('attendance_participants', fn (Blueprint $t) => $t->unsignedBigInteger('active_roster_id')->nullable());
        Schema::table('attendance_punches', fn (Blueprint $t) => $t->unsignedBigInteger('roster_id')->nullable()->index());
        Schema::table('attendance_leave_types', function (Blueprint $t) {
            $t->boolean('encashment_enabled')->default(false);
            $t->unsignedInteger('max_encash_days')->default(0);
        });
        Schema::table('attendance_requests', function (Blueprint $t) {
            $t->json('metadata')->nullable();
            $t->unsignedBigInteger('settlement_amount_minor')->nullable();
            $t->string('settlement_currency', 3)->nullable();
            $t->string('settlement_reference', 160)->nullable();
            $t->timestamp('settled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_requests', fn (Blueprint $t) => $t->dropColumn(['metadata', 'settlement_amount_minor', 'settlement_currency', 'settlement_reference', 'settled_at']));
        Schema::table('attendance_leave_types', fn (Blueprint $t) => $t->dropColumn(['encashment_enabled', 'max_encash_days']));
        Schema::table('attendance_punches', fn (Blueprint $t) => $t->dropColumn('roster_id'));
        Schema::table('attendance_participants', fn (Blueprint $t) => $t->dropColumn('active_roster_id'));
        Schema::dropIfExists('attendance_rosters');
        Schema::dropIfExists('attendance_shifts');
    }
};
