<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('creator_profiles', function (Blueprint $t) {
            $t->string('name', 200)->nullable();
            $t->json('workspace_settings')->nullable();
            $t->string('profile_verification_status')->nullable();
            $t->unsignedBigInteger('profile_verification_type_id')->nullable();
            $t->timestamp('profile_verified_at')->nullable();
            $t->string('profile_verified_name', 200)->nullable();
            $t->string('profile_verified_avatar', 1024)->nullable();
        });
        Schema::create('verification_revocations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('actor_user_id'); $t->string('actor_guard'); $t->string('subject_kind'); $t->unsignedBigInteger('subject_id'); $t->text('reason'); $t->timestamp('created_at');
        });
        Schema::table('profile_verification_requests', function (Blueprint $t) {
            $t->foreignId('creator_profile_id')->nullable()->constrained('creator_profiles')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('verification_revocations');
        Schema::table('profile_verification_requests', fn (Blueprint $t) => $t->dropConstrainedForeignId('creator_profile_id'));
        Schema::table('creator_profiles', fn (Blueprint $t) => $t->dropColumn(['name','workspace_settings','profile_verification_status','profile_verification_type_id','profile_verified_at','profile_verified_name','profile_verified_avatar']));
    }
};
