<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->boolean('is_read')->default(false)->after('status');
            $t->boolean('is_starred')->default(false)->after('is_read');
            $t->boolean('is_spam')->default(false)->after('is_starred');
            $t->timestamp('read_at')->nullable()->after('is_spam');
            $t->index(['user_id', 'is_read']);
            $t->index(['user_id', 'is_starred']);
        });
    }

    public function down(): void
    {
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'is_read']);
            $t->dropIndex(['user_id', 'is_starred']);
            $t->dropColumn(['is_read', 'is_starred', 'is_spam', 'read_at']);
        });
    }
};
