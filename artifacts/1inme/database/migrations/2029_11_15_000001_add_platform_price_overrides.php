<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prices', function (Blueprint $t) {
            $t->unsignedBigInteger('ios_amount_minor')->nullable();
            $t->unsignedBigInteger('android_amount_minor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('prices', fn (Blueprint $t) => $t->dropColumn(['ios_amount_minor', 'android_amount_minor']));
    }
};
