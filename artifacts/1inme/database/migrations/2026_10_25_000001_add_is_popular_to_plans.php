<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('is_default');
        });

        if (!\DB::table('plans')->where('is_popular', true)->exists()) {
            \DB::table('plans')->where('slug', 'pro')->update(['is_popular' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_popular');
        });
    }
};
