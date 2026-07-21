<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional creator-profile-specific avatar override (Task #5494).
 * Stored like `users.avatar` (`/storage/<path>` or absolute URL); null
 * means "inherit the account profile photo".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'creator_avatar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('creator_avatar', 1024)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'creator_avatar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('creator_avatar');
            });
        }
    }
};
