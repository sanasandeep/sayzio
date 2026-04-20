<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the existing FK + add columns. Postgres-safe path: drop FK,
        // make user_id nullable, re-add FK; existing rows keep their owners.
        Schema::table('domains', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('domains', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->boolean('is_active')->default(true)->after('is_verified');
            $table->string('cname_target')->nullable()->after('verification_token');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Backfill: every pre-existing row is a user-owned, active domain.
        DB::table('domains')->update(['is_active' => true]);

        Schema::create('domain_plan', function (Blueprint $table) {
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['domain_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_plan');
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'cname_target']);
        });
    }
};
