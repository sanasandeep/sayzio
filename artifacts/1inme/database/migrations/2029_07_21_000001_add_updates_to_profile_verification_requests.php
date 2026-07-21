<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('profile_verification_requests')
            && !Schema::hasColumn('profile_verification_requests', 'updates')) {
            Schema::table('profile_verification_requests', function (Blueprint $table) {
                // Append-only list of user-submitted follow-up updates while the
                // request is pending: [{message, files: [...], created_at}].
                $table->json('updates')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('profile_verification_requests')
            && Schema::hasColumn('profile_verification_requests', 'updates')) {
            Schema::table('profile_verification_requests', function (Blueprint $table) {
                $table->dropColumn('updates');
            });
        }
    }
};
