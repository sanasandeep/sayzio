<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $t) {
            $t->string('spam_reason', 160)->nullable()->after('is_spam');
        });

        Schema::table('subscribers', function (Blueprint $t) {
            $t->string('spam_reason', 160)->nullable()->after('is_spam');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $t) {
            $t->dropColumn('spam_reason');
        });
        Schema::table('subscribers', function (Blueprint $t) {
            $t->dropColumn('spam_reason');
        });
    }
};
