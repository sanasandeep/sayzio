<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('creator_phone_assignments', function (Blueprint $t) {
            $t->id(); $t->string('phone',16)->unique();
            $t->foreignId('creator_profile_id')->nullable()->constrained('creator_profiles')->nullOnDelete();
            $t->unsignedBigInteger('attached_by')->nullable(); $t->string('source',30); $t->timestamps();
        });
        Schema::create('creator_phone_claims', function (Blueprint $t) {
            $t->id(); $t->foreignId('creator_profile_id')->constrained('creator_profiles')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('phone',16)->index(); $t->text('message'); $t->json('proof_files')->nullable(); $t->json('supporting_urls')->nullable();
            $t->string('status')->default('pending'); $t->text('admin_notes')->nullable(); $t->unsignedBigInteger('reviewed_by')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('creator_phone_claims'); Schema::dropIfExists('creator_phone_assignments');
    }
};
