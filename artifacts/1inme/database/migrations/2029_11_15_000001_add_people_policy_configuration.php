<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_leave_types', function (Blueprint $t) {
            $t->string('accrual_mode', 16)->default('annual');
            $t->decimal('accrual_days', 6, 2)->default(1);
            $t->unsignedInteger('accrual_every_days')->default(15);
            $t->date('accrual_starts_on')->nullable();
            $t->decimal('opening_days', 6, 2)->default(0);
        });
        Schema::create('attendance_ai_drafts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('actor_user_id');
            $t->unsignedBigInteger('ai_staff_id');
            $t->string('kind', 16);
            $t->string('status', 16)->default('pending');
            $t->json('payload');
            $t->timestamps();
            $t->index(['workspace_id', 'actor_user_id', 'status']);
        });
        Schema::table('attendance_participants', fn (Blueprint $t) => $t->date('employment_started_on')->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_ai_drafts');
        Schema::table('attendance_leave_types', fn (Blueprint $t) => $t->dropColumn(['accrual_mode', 'accrual_days', 'accrual_every_days', 'accrual_starts_on', 'opening_days']));
        Schema::table('attendance_participants', fn (Blueprint $t) => $t->dropColumn('employment_started_on'));
    }
};
