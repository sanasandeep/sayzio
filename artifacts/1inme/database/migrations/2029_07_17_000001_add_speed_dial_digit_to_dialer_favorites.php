<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dialer_favorites', function (Blueprint $table) {
            if (!Schema::hasColumn('dialer_favorites', 'speed_dial_digit')) {
                // Digit 1–9 on the keypad can each be assigned to one favorite.
                // Nullable means no speed-dial assignment; unique per user+digit
                // prevents two favorites owning the same key.
                $table->unsignedTinyInteger('speed_dial_digit')->nullable()->after('sort_order');
                $table->unique(['user_id', 'speed_dial_digit'], 'dialer_favorites_user_digit_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dialer_favorites', function (Blueprint $table) {
            if (Schema::hasColumn('dialer_favorites', 'speed_dial_digit')) {
                $table->dropUnique('dialer_favorites_user_digit_unique');
                $table->dropColumn('speed_dial_digit');
            }
        });
    }
};
