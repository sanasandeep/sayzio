<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds sensible default `recommended_personas` tags onto existing page
 * templates so the onboarding wizard has good "Recommended for you"
 * suggestions on day one. Idempotent: only fills templates whose tag
 * column is null/empty — never overwrites curator choices.
 */
class PageTemplatePersonaSeeder extends Seeder
{
    public function run(): void
    {
        // Map page-template categories -> default personas. Anything not
        // covered here gets a generic "creator/business/other" trio so it
        // surfaces for someone, somewhere.
        $defaultsByCategory = [
            'creator'    => ['creator', 'influencer', 'artist'],
            'business'   => ['business', 'coach'],
            'event'      => ['business', 'musician', 'coach'],
            'product'    => ['business', 'artist'],
            'portfolio'  => ['artist', 'photographer', 'developer', 'writer'],
            'restaurant' => ['business'],
            'nonprofit'  => ['business', 'coach'],
            'general'    => ['creator', 'business', 'other'],
        ];

        $fallback = ['creator', 'business', 'other'];

        PageTemplate::query()
            ->where(function ($q) {
                $q->whereNull('recommended_personas')
                  ->orWhereRaw("recommended_personas::text = '[]'")
                  ->orWhereRaw("recommended_personas::text = 'null'");
            })
            ->get()
            ->each(function (PageTemplate $tpl) use ($defaultsByCategory, $fallback) {
                $tpl->recommended_personas = $defaultsByCategory[$tpl->category] ?? $fallback;
                $tpl->save();
            });
    }
}
