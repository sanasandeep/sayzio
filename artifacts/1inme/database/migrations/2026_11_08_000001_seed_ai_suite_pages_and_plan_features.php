<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds (idempotently):
     *  - Four new SitePage rows for the AI suite marketing pages.
     *  - Appends an "AI suite" category to the existing /features page
     *    sections, only if the category id is not already present
     *    (preserves admin edits).
     *  - Adds the four AI suite feature flags to every plan's features
     *    JSON, with sensible per-tier defaults. Existing keys are left
     *    untouched so curator edits are preserved.
     */
    public function up(): void
    {
        $this->seedSitePages();
        $this->backfillFeaturesPageCategory();
        $this->seedPlanFeatures();
    }

    public function down(): void
    {
        // Remove the four SitePage rows (only if they still hold the
        // default content for these slugs — light touch).
        DB::table('site_pages')->whereIn('slug', SitePagesContent::aiProductSlugs())->delete();

        // Strip the AI suite category from the features page sections.
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if ($row) {
            $sections = json_decode($row->sections ?? '[]', true) ?: [];
            $filtered = array_values(array_filter($sections, function ($s) {
                $id = is_array($s) ? trim((string) ($s['id'] ?? '')) : '';
                return $id !== 'ai-suite';
            }));
            if (count($filtered) !== count($sections)) {
                DB::table('site_pages')->where('id', $row->id)->update([
                    'sections' => json_encode($filtered),
                ]);
            }
        }

        // Strip AI feature keys from plans.
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $changed = false;
            foreach (['ai_chatbot', 'ai_agent', 'ai_widget', 'ai_voice_assistant'] as $k) {
                if (array_key_exists($k, $features)) {
                    unset($features[$k]);
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'features' => json_encode($features),
                ]);
            }
        }
    }

    private function seedSitePages(): void
    {
        $now = now();
        foreach (SitePagesContent::aiProductsDefault() as $slug => $data) {
            $existing = DB::table('site_pages')->where('slug', $slug)->first();
            if ($existing) {
                continue;
            }
            DB::table('site_pages')->insert([
                'slug'             => $slug,
                'title'            => $data['title'],
                'meta_description' => $data['meta_description'] ?? null,
                'sections'         => json_encode($data['sections'] ?? []),
                'cta_label'        => $data['cta_label'] ?? null,
                'cta_url'          => $data['cta_url'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    private function backfillFeaturesPageCategory(): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row) {
            // No features page yet — the SitePagesSeeder / first visit
            // will create it including the AI suite category from
            // featuresCategoriesDefault().
            return;
        }
        $sections = json_decode($row->sections ?? '[]', true) ?: [];
        foreach ($sections as $s) {
            if (is_array($s) && trim((string) ($s['id'] ?? '')) === 'ai-suite') {
                return; // already there — preserve admin edits
            }
        }
        // Prepend the AI suite category so it appears at the top of the page.
        array_unshift($sections, SitePagesContent::aiSuiteFeaturesCategory());
        DB::table('site_pages')->where('id', $row->id)->update([
            'sections'   => json_encode($sections),
            'updated_at' => now(),
        ]);
    }

    private function seedPlanFeatures(): void
    {
        // Defaults per plan tier slug. Plans with unknown slugs get the
        // free-tier defaults (everything off) so we never accidentally
        // unlock paid AI features on a custom tier.
        $defaults = [
            'free'       => ['ai_chatbot' => false, 'ai_agent' => false, 'ai_widget' => false, 'ai_voice_assistant' => false],
            'starter'    => ['ai_chatbot' => true,  'ai_agent' => false, 'ai_widget' => true,  'ai_voice_assistant' => false],
            'pro'        => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => false],
            'business'   => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => true],
            'enterprise' => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => true],
        ];

        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $defaults[$plan->slug] ?? $defaults['free'];
            $changed = false;
            foreach ($tierDefaults as $k => $v) {
                if (!array_key_exists($k, $features)) {
                    $features[$k] = $v;
                    $changed = true;
                }
            }
            if ($changed) {
                DB::table('plans')->where('id', $plan->id)->update([
                    'features' => json_encode($features),
                ]);
            }
        }
    }
};
