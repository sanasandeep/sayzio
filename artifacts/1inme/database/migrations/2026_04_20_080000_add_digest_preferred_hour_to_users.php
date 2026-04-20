<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'digest_preferred_hour')) {
                $table->unsignedTinyInteger('digest_preferred_hour')
                    ->default(9)
                    ->after('follower_digest_last_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'digest_preferred_hour')) {
                $table->dropColumn('digest_preferred_hour');
            }
        });
    }
};
