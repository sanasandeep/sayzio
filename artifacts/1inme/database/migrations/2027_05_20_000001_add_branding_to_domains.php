<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-domain sub-branding for non-primary global domains. Each
        // global domain can carry its own light/dark wordmark + icon and a
        // short "relationship" blurb explaining how it relates to the
        // primary domain (shown on its landing page). All nullable: a
        // domain with no custom logos falls back to the platform AppSetting
        // branding so nothing ever renders broken.
        Schema::table('domains', function (Blueprint $table) {
            if (!Schema::hasColumn('domains', 'brand_logo_light_url')) {
                $table->string('brand_logo_light_url', 2048)->nullable()->after('is_primary');
            }
            if (!Schema::hasColumn('domains', 'brand_logo_dark_url')) {
                $table->string('brand_logo_dark_url', 2048)->nullable()->after('brand_logo_light_url');
            }
            if (!Schema::hasColumn('domains', 'brand_icon_url')) {
                $table->string('brand_icon_url', 2048)->nullable()->after('brand_logo_dark_url');
            }
            if (!Schema::hasColumn('domains', 'relationship_blurb')) {
                $table->text('relationship_blurb')->nullable()->after('brand_icon_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            foreach ([
                'brand_logo_light_url',
                'brand_logo_dark_url',
                'brand_icon_url',
                'relationship_blurb',
            ] as $col) {
                if (Schema::hasColumn('domains', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
