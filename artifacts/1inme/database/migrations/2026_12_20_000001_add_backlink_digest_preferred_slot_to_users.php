<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'backlink_digest_preferred_weekday')) {
                $table->unsignedTinyInteger('backlink_digest_preferred_weekday')
                    ->default(1)
                    ->after('last_backlink_digest_sent_at');
            }
            if (!Schema::hasColumn('users', 'backlink_digest_preferred_hour')) {
                $table->unsignedTinyInteger('backlink_digest_preferred_hour')
                    ->default(9)
                    ->after('backlink_digest_preferred_weekday');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['backlink_digest_preferred_weekday', 'backlink_digest_preferred_hour'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
