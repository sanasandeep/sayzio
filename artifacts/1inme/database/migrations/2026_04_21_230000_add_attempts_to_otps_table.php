<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0)->after('used');
            $table->timestamp('last_attempt_at')->nullable()->after('attempts');
            $table->string('issued_ip', 45)->nullable()->after('last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'last_attempt_at', 'issued_ip']);
        });
    }
};
