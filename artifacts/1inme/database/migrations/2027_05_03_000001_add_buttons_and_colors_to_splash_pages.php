<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('splash_pages', function (Blueprint $t) {
            if (!Schema::hasColumn('splash_pages', 'cta_bg_color')) {
                $t->string('cta_bg_color', 20)->nullable()->after('cta_url');
            }
            if (!Schema::hasColumn('splash_pages', 'cta_text_color')) {
                $t->string('cta_text_color', 20)->nullable()->after('cta_bg_color');
            }
            if (!Schema::hasColumn('splash_pages', 'extra_buttons')) {
                $t->json('extra_buttons')->nullable()->after('cta_text_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('splash_pages', function (Blueprint $t) {
            foreach (['extra_buttons', 'cta_text_color', 'cta_bg_color'] as $col) {
                if (Schema::hasColumn('splash_pages', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
