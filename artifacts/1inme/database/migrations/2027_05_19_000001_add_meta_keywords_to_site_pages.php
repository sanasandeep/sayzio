<?php

use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an admin-editable `meta_keywords` column to `site_pages` (and mirrors
 * it on `site_page_revisions` for the audit trail), then idempotently seeds
 * unique keyword-rich defaults onto any marketing page row that doesn't have
 * keywords yet. Title + description already ship as seeded row values; only
 * keywords are net-new, so this backfill is the one-time seed for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_pages') && !Schema::hasColumn('site_pages', 'meta_keywords')) {
            Schema::table('site_pages', function (Blueprint $table) {
                $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            });
        }

        if (Schema::hasTable('site_page_revisions') && !Schema::hasColumn('site_page_revisions', 'meta_keywords')) {
            Schema::table('site_page_revisions', function (Blueprint $table) {
                $table->string('meta_keywords', 500)->nullable()->after('meta_description');
            });
        }

        // Idempotent seed: only set keywords on rows that are still blank so
        // re-running the migration (or running it after an admin has already
        // customised a page) never clobbers admin-entered values.
        if (Schema::hasTable('site_pages')) {
            foreach (MarketingSeo::sitePageKeywordDefaults() as $slug => $keywords) {
                DB::table('site_pages')
                    ->where('slug', $slug)
                    ->where(function ($q) {
                        $q->whereNull('meta_keywords')->orWhere('meta_keywords', '');
                    })
                    ->update(['meta_keywords' => $keywords]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_page_revisions') && Schema::hasColumn('site_page_revisions', 'meta_keywords')) {
            Schema::table('site_page_revisions', function (Blueprint $table) {
                $table->dropColumn('meta_keywords');
            });
        }

        if (Schema::hasTable('site_pages') && Schema::hasColumn('site_pages', 'meta_keywords')) {
            Schema::table('site_pages', function (Blueprint $table) {
                $table->dropColumn('meta_keywords');
            });
        }
    }
};
