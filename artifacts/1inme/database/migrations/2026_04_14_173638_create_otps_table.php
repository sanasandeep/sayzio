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
                if (Schema::getConnection()->getDriverName() !== 'mysql') {
                    $table->index(['identifier', 'type', 'purpose', 'guard']);
                }
            });

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                // Four full utf8mb4 varchar(255) columns exceed MySQL's
                // 3072-byte index key limit; use prefix lengths instead.
                \Illuminate\Support\Facades\DB::statement(
                    'alter table `otps` add index `otps_identifier_type_purpose_guard_index` (`identifier`(191), `type`(32), `purpose`(32), `guard`(32))'
                );
            }
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
