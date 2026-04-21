<?php

use App\Modules\Common\Models\SitePage;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
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
