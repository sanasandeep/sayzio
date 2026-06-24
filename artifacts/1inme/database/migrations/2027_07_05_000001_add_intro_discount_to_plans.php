<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-plan first-term introductory discount config.
 *
 * Stored as nullable jsonb (`intro_discount`) holding the normalized
 * shape emitted by {@see \App\Services\Billing\IntroDiscount::normalize()}
 * — null/absent means "no intro discount". Additive + guarded so it is
 * safe to replay against the shared RDS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('plans', 'intro_discount')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->jsonb('intro_discount')->nullable()->after('metadata');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('plans', 'intro_discount')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->dropColumn('intro_discount');
            });
        }
    }
};
