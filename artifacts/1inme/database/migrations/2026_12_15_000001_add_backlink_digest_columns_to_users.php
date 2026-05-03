<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'last_backlink_digest_sent_at')) {
                $t->timestamp('last_backlink_digest_sent_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'last_backlink_digest_sent_at')) {
                $t->dropColumn('last_backlink_digest_sent_at');
            }
        });
    }
};
