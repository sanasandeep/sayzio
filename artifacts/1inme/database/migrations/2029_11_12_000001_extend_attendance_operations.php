<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_participants', function (Blueprint $t) {
            $t->string('employee_code', 80)->nullable();
            $t->string('display_name')->nullable();
            $t->unsignedBigInteger('designation_id')->nullable();
            $t->boolean('attendance_enabled')->default(true);
            $t->json('allowed_ips')->nullable();
            $t->string('auto_out_time', 5)->nullable();
            $t->timestamp('auto_punch_out_at')->nullable();
        });
        Schema::table('attendance_punches', fn (Blueprint $t) => $t->string('ip_address', 45)->nullable());
        Schema::table('attendance_challenges', fn (Blueprint $t) => $t->uuid('qr_session_id')->nullable());
        Schema::table('attendance_requests', function (Blueprint $t) {
            $t->unsignedBigInteger('leave_type_id')->nullable();
            $t->decimal('requested_days', 6, 2)->nullable();
            $t->unsignedInteger('leave_year')->nullable();
        });
        Schema::create('attendance_designations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('name', 120);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('attendance_holidays', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->date('date');
            $t->string('name', 120);
            $t->timestamps();
            $t->unique(['workspace_id', 'date']);
        });
        Schema::create('attendance_leave_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('name', 120);
            $t->unsignedInteger('annual_days');
            $t->boolean('paid')->default(true);
            $t->timestamps();
            $t->unique(['workspace_id', 'name']);
        });
        Schema::create('attendance_notices', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->string('title', 160);
            $t->text('body');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->unsignedBigInteger('author_id');
            $t->string('author_guard');
            $t->timestamps();
            $t->index(['workspace_id', 'starts_on']);
        });
        Schema::create('attendance_qr_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('issuer_id');
            $t->string('issuer_guard');
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->index('expires_at');
        });
        Schema::create('attendance_qr_redemptions', function (Blueprint $t) {
            $t->id();
            $t->uuid('qr_session_id');
            $t->foreignId('participant_id')->constrained('attendance_participants');
            $t->unsignedBigInteger('punch_id');
            $t->timestamps();
            $t->unique(['qr_session_id', 'participant_id']);
        });
    }

    public function down(): void
    {
        foreach (['attendance_qr_redemptions', 'attendance_qr_sessions', 'attendance_notices', 'attendance_leave_types', 'attendance_holidays', 'attendance_designations'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('attendance_requests', fn (Blueprint $t) => $t->dropColumn(['leave_type_id', 'requested_days', 'leave_year']));
        Schema::table('attendance_challenges', fn (Blueprint $t) => $t->dropColumn('qr_session_id'));
        Schema::table('attendance_punches', fn (Blueprint $t) => $t->dropColumn('ip_address'));
        Schema::table('attendance_participants', fn (Blueprint $t) => $t->dropColumn(['employee_code', 'display_name', 'designation_id', 'attendance_enabled', 'allowed_ips', 'auto_out_time', 'auto_punch_out_at']));
    }
};
