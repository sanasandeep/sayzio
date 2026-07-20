<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'profile_verification_status')) {
                $table->string('profile_verification_status', 30)->default('unverified')->after('status');
            }
            if (!Schema::hasColumn('users', 'profile_verification_type_id')) {
                $table->unsignedBigInteger('profile_verification_type_id')->nullable()->after('profile_verification_status');
                $table->foreign('profile_verification_type_id', 'fk_users_profile_tick_type')
                    ->references('id')->on('verification_tick_types')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'profile_verified_name')) {
                $table->string('profile_verified_name')->nullable()->after('profile_verification_type_id');
            }
            if (!Schema::hasColumn('users', 'profile_verified_avatar')) {
                $table->string('profile_verified_avatar')->nullable()->after('profile_verified_name');
            }
            if (!Schema::hasColumn('users', 'profile_verified_at')) {
                $table->timestamp('profile_verified_at')->nullable()->after('profile_verified_avatar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'profile_verification_type_id')) {
                $table->dropForeign('fk_users_profile_tick_type');
            }
            $cols = ['profile_verification_status', 'profile_verification_type_id',
                     'profile_verified_name', 'profile_verified_avatar', 'profile_verified_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
