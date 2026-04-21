<?php

use App\Modules\Common\Models\SitePage;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The site_pages table is created by a later migration; on a fresh
        // install this migration runs before that one, in which case the
        // seed will be applied later by the SitePagesSeeder.
        if (!Schema::hasTable('site_pages')) {
            return;
        }
        SitePage::updateOrCreate(
            ['slug' => 'services'],
            [
                'title' => 'What you can do with 1INME',
                'meta_description' => 'See how marketers, creators, agencies, small businesses and event organizers use 1INME as their link-in-bio, portfolio, and audience hub.',
                'sections' => SitePagesSeeder::servicesDefaultSections(),
                'cta_label' => 'Create your 1INME',
                'cta_url' => '/register',
            ]
        );
    }

    public function down(): void
    {
        SitePage::where('slug', 'services')->delete();
    }
};
