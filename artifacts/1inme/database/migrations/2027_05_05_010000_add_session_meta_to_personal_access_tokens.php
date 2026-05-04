<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds session metadata to Sanctum's personal_access_tokens so we can
 * power the "Devices & sessions" page (task #1111): show the device /
 * UA / country / IP that minted each token plus the IP/UA observed on
 * the most-recent request, so a user can spot a stranger's session and
 * revoke it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $t) {
            $t->string('device_label', 120)->nullable()->after('name');
            $t->string('platform', 32)->nullable()->after('device_label');
            $t->string('client_kind', 32)->nullable()->after('platform');
            $t->string('created_ip', 45)->nullable()->after('client_kind');
            $t->string('created_country', 2)->nullable()->after('created_ip');
            $t->string('created_user_agent', 500)->nullable()->after('created_country');
            $t->string('last_ip', 45)->nullable()->after('created_user_agent');
            $t->string('last_country', 2)->nullable()->after('last_ip');
            $t->string('last_user_agent', 500)->nullable()->after('last_country');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $t) {
            $t->dropColumn([
                'device_label',
                'platform',
                'client_kind',
                'created_ip',
                'created_country',
                'created_user_agent',
                'last_ip',
                'last_country',
                'last_user_agent',
            ]);
        });
    }
};
