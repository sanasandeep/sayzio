<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('profile_verification_requests')) {
            Schema::create('profile_verification_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tick_type_id')->nullable()->constrained('verification_tick_types')->nullOnDelete();
                $table->string('official_name');
                $table->text('purpose');
                $table->string('logo_path')->nullable();
                $table->json('proof_files')->nullable();
                $table->string('status', 20)->default('pending'); // pending, approved, rejected
                $table->string('kind', 20)->default('new'); // new, reverification
                $table->text('admin_notes')->nullable();
                // Snapshot of name/avatar at request time (for re-verification)
                $table->string('prev_verified_name')->nullable();
                $table->string('new_name')->nullable();
                $table->string('new_avatar')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_verification_requests');
    }
};
