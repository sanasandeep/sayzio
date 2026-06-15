<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Optional customer verification for native reviews. When a page opts
        // in, a review is held in the `unverified` status until the reviewer
        // either matches a known customer (subscriber / contact) or clicks a
        // one-time email confirmation link. `verified_at` drives the public
        // "Verified customer" badge.
        Schema::table('reviews', function (Blueprint $t) {
            $t->timestamp('verified_at')->nullable()->after('is_spam');
            // email | subscriber | contact
            $t->string('verification_method', 16)->nullable()->after('verified_at');
            $t->string('verification_token', 64)->nullable()->after('verification_method');

            $t->index('verification_token');
            $t->index(['user_id', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'verified_at']);
            $t->dropIndex(['verification_token']);
            $t->dropColumn(['verified_at', 'verification_method', 'verification_token']);
        });
    }
};
