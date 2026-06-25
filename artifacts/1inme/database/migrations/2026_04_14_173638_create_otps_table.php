<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('otps')) {
            Schema::create('otps', function (Blueprint $table) {
                $table->id();
                $table->string('identifier');
                $table->string('type')->default('email');
                $table->string('code', 6);
                $table->string('purpose')->default('login');
                $table->string('guard')->default('web');
                $table->timestamp('expires_at');
                $table->boolean('used')->default(false);
                $table->timestamps();
                $table->index(['identifier', 'type', 'purpose', 'guard']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otps');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
    }
};
