<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            // null when no client portal review has happened.
            // pending | approved | rejected once a portal share marks it for review.
            $table->string('client_approval_status', 16)->nullable()->after('pinned_at');
            $table->timestamp('client_approval_at')->nullable()->after('client_approval_status');
            $table->string('client_approval_email')->nullable()->after('client_approval_at');
            $table->index(['user_id', 'client_approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('creator_posts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'client_approval_status']);
            $table->dropColumn(['client_approval_status', 'client_approval_at', 'client_approval_email']);
        });
    }
};
