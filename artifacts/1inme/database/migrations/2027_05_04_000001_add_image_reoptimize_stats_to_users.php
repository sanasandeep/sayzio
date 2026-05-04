<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'image_reoptimize_files_count')) {
                $t->unsignedInteger('image_reoptimize_files_count')->default(0);
            }
            if (!Schema::hasColumn('users', 'image_reoptimize_bytes_freed')) {
                $t->unsignedBigInteger('image_reoptimize_bytes_freed')->default(0);
            }
            if (!Schema::hasColumn('users', 'image_reoptimize_notice_dismissed_at')) {
                $t->timestamp('image_reoptimize_notice_dismissed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            foreach ([
                'image_reoptimize_files_count',
                'image_reoptimize_bytes_freed',
                'image_reoptimize_notice_dismissed_at',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
