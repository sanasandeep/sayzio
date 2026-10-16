<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Replace only known legacy copy. Preserve custom administrator copy.
        $descriptions = [
            'bio-lite' => [
                'old' => ['A focused, affordable Link in Bio plan: one page, done properly, without the Sayzio badge.'],
                'new' => 'A focused Link in Bio plan with three pages, 100 short links and no Sayzio badge.',
            ],
            'bio-plus' => [
                'old' => [
                    'A power-user Link in Bio plan: your own domain, the expanded AI tools and deeper analytics.',
                    'A power-user Link in Bio plan with custom domains, team collaboration, and expanded AI tools — for creators in India ready to grow beyond the basics.',
                ],
                'new' => 'Grow your Link in Bio presence with ten pages, 500 short links, a custom domain and exportable analytics.',
            ],
        ];
        foreach ($descriptions as $slug => $copy) {
            DB::table('plans')->where('slug', $slug)->whereIn('description', $copy['old'])
                ->update(['description' => $copy['new'], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Do not restore descriptions that advertise unavailable features.
    }
};
