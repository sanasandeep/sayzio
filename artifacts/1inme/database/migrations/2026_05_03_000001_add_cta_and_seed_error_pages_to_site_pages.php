<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('cta_label', 120)->nullable()->after('sections');
            $table->string('cta_url', 500)->nullable()->after('cta_label');
        });

        $now = now();
        $rows = [
            [
                'slug'             => 'error-404',
                'title'            => 'Page not found',
                'meta_description' => 'The page you were looking for does not exist or has been moved.',
                'sections'         => json_encode([
                    [
                        'heading' => "We can't find that page",
                        'body'    => "The link you followed may be broken, or the page may have been removed. Try heading back to the homepage to find what you were looking for.",
                    ],
                ]),
                'cta_label'        => 'Back to home',
                'cta_url'          => '/',
            ],
            [
                'slug'             => 'error-403',
                'title'            => 'No access',
                'meta_description' => "You don't have permission to view this page.",
                'sections'         => json_encode([
                    [
                        'heading' => "You don't have access to this page",
                        'body'    => "You may need to sign in with a different account, or ask the page owner for permission. If you think this is a mistake, please get in touch.",
                    ],
                ]),
                'cta_label'        => 'Back to home',
                'cta_url'          => '/',
            ],
        ];

        foreach ($rows as $r) {
            $exists = DB::table('site_pages')->where('slug', $r['slug'])->exists();
            if (!$exists) {
                DB::table('site_pages')->insert(array_merge($r, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('site_pages')->whereIn('slug', ['error-403', 'error-404'])->delete();
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn(['cta_label', 'cta_url']);
        });
    }
};
