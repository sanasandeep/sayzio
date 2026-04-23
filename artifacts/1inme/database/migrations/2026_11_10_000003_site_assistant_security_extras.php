<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds:
 *  - site_assistant_page_hints.disable_widget — admin can hide the
 *    launcher entirely on matching routes.
 *  - site_assistant_conversations.bound_user_id — when the conversation
 *    starts for a signed-in visitor we lock the visitor_token to that
 *    user; subsequent reuse by anyone else is rejected.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('site_assistant_page_hints', function (Blueprint $t) {
            $t->boolean('disable_widget')->default(false)->after('suggested_actions');
        });
        Schema::table('site_assistant_conversations', function (Blueprint $t) {
            // Once set, future requests with this token MUST be from the
            // same user. Anonymous conversations have NULL here and may
            // not be claimed retroactively by an authed user.
            $t->unsignedBigInteger('bound_user_id')->nullable()->after('user_id');
            $t->index('bound_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('site_assistant_page_hints', function (Blueprint $t) {
            $t->dropColumn('disable_widget');
        });
        Schema::table('site_assistant_conversations', function (Blueprint $t) {
            $t->dropIndex(['bound_user_id']);
            $t->dropColumn('bound_user_id');
        });
    }
};
