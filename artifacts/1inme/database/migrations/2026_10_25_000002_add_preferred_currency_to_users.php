<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist a signed-in visitor's manual currency override (USD/INR) on
 * their user record so the choice follows them across devices and
 * survives session expiry.
 *
 * Only consulted when the user has no explicit profile country — a
 * country, when set, still wins over this column (country is the
 * billing-of-record signal). Anonymous visitors get the same survival
 * via a long-lived signed cookie; this column is the signed-in mirror.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('preferred_currency', 3)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('preferred_currency');
        });
    }
};
