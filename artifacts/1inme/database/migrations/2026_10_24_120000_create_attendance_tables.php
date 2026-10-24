<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_policies', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id')->unique();
            $t->json('settings');
            $t->timestamps();
        });
        Schema::create('attendance_participants', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('user_id');
            $t->string('state')->default('out');
            $t->timestamps();
            $t->unique(['workspace_id', 'user_id']);
        });
        Schema::create('attendance_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('participant_id')->constrained('attendance_participants')->cascadeOnDelete();
            $t->string('action');
            $t->string('pose');
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('attendance_punches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->constrained('attendance_participants')->cascadeOnDelete();
            $t->uuid('challenge_id')->nullable()->unique();
            $t->string('action');
            $t->timestamp('occurred_at');
            $t->string('photo_path')->nullable();
            $t->string('pose')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->decimal('accuracy', 10, 2)->nullable();
            $t->string('review_status')->default('pending');
            $t->string('source')->default('web');
            $t->timestamps();
            $t->index(['participant_id', 'occurred_at']);
        });
        Schema::create('attendance_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('participant_id')->constrained('attendance_participants')->cascadeOnDelete();
            $t->string('type');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->text('reason');
            $t->foreignId('punch_id')->nullable()->constrained('attendance_punches');
            $t->timestamp('proposed_at')->nullable();
            $t->unsignedInteger('minutes')->nullable();
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('attendance_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workspace_id');
            $t->unsignedBigInteger('actor_user_id');
            $t->string('actor_guard')->default('web');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('decision');
            $t->text('note');
            $t->json('details')->nullable();
            $t->timestamp('created_at');
            $t->index(['workspace_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['attendance_reviews', 'attendance_requests', 'attendance_punches', 'attendance_challenges', 'attendance_participants', 'attendance_policies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
