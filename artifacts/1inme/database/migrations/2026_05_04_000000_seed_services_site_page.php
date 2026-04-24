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
        SitePage::updateOrCreate(['slug' => 'services'], $this->seedAttributes());
    }

    public function down(): void
    {
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // only delete the seeded /services row if every column we wrote in
        // up() still equals the seeded default. Any drift means an admin
        // edited the page and we must preserve their work.
        if (!Schema::hasTable('site_pages')) {
            return;
        }
        $row = SitePage::where('slug', 'services')->first();
        if (!$row) {
            return;
        }
        $seed = $this->seedAttributes();
        $matches = $row->title === $seed['title']
            && $row->meta_description === $seed['meta_description']
            && (is_array($row->sections) ? $row->sections : []) == $seed['sections']
            && $row->cta_label === $seed['cta_label']
            && $row->cta_url === $seed['cta_url'];
        if ($matches) {
            $row->delete();
        }
    }

    private function seedAttributes(): array
    {
        return [
            'title' => 'What you can do with 1INME',
            'meta_description' => 'See how marketers, creators, agencies, small businesses and event organizers use 1INME as their link-in-bio, portfolio, and audience hub.',
            'sections' => SitePagesSeeder::servicesDefaultSections(),
            'cta_label' => 'Create your 1INME',
            'cta_url' => '/register',
        ];
    }
};
