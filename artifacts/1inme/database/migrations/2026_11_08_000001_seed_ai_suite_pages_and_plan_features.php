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
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy",
        // every delete / unset below is gated on a "still equals what
        // up() wrote" check. The previous version of this method was
        // documented as "light touch" but actually deleted unconditionally
        // and silently discarded admin edits to the AI suite pages,
        // /features sections, and plan features JSON.

        // 1. Remove the four AI SitePage rows ONLY if their content still
        //    matches the seeded default for that slug.
        $defaults = SitePagesContent::aiProductsDefault();
        foreach (SitePagesContent::aiProductSlugs() as $slug) {
            $row = DB::table('site_pages')->where('slug', $slug)->first();
            if (!$row) {
                continue;
            }
            $seed = $defaults[$slug] ?? null;
            if (!$seed) {
                continue;
            }
            $rowSections  = json_decode((string) ($row->sections ?? ''), true);
            $seedSections = $seed['sections'] ?? [];
            $matches = $row->title === $seed['title']
                && ($row->meta_description ?? null) === ($seed['meta_description'] ?? null)
                && $rowSections == $seedSections
                && ($row->cta_label ?? null) === ($seed['cta_label'] ?? null)
                && ($row->cta_url ?? null) === ($seed['cta_url'] ?? null);
            if ($matches) {
                DB::table('site_pages')->where('id', $row->id)->delete();
            }
        }

        // 2. Strip the AI suite category from /features sections ONLY if the
        //    in-place category still equals the seeded one. An admin who
        //    re-ordered features inside the category, renamed it, or added
        //    a link will see their edits preserved.
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if ($row) {
            $sections = json_decode($row->sections ?? '[]', true) ?: [];
            $seedCat  = SitePagesContent::aiSuiteFeaturesCategory();
            $changed  = false;
            $kept     = [];
            foreach ($sections as $s) {
                $id = is_array($s) ? trim((string) ($s['id'] ?? '')) : '';
                if ($id === 'ai-suite' && $s == $seedCat) {
                    $changed = true;
                    continue;
                }
                $kept[] = $s;
            }
            if ($changed) {
                DB::table('site_pages')->where('id', $row->id)->update([
                    'sections' => json_encode(array_values($kept)),
                ]);
            }
        }

        // 3. Strip AI feature keys from plans ONLY where the value still
        //    equals the seeded default for that plan tier.
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $this->planFeatureDefaultsFor($plan->slug);
            $changed = false;
            foreach ($tierDefaults as $k => $v) {
                if (array_key_exists($k, $features) && $features[$k] === $v) {
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
        $plans = DB::table('plans')->get();
        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $tierDefaults = $this->planFeatureDefaultsFor($plan->slug);
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

    /**
     * Defaults per plan tier slug. Plans with unknown slugs get the
     * free-tier defaults (everything off) so we never accidentally
     * unlock paid AI features on a custom tier.
     */
    private function planFeatureDefaultsFor(?string $slug): array
    {
        $defaults = [
            'free'       => ['ai_chatbot' => false, 'ai_agent' => false, 'ai_widget' => false, 'ai_voice_assistant' => false],
            'starter'    => ['ai_chatbot' => true,  'ai_agent' => false, 'ai_widget' => true,  'ai_voice_assistant' => false],
            'pro'        => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => false],
            'business'   => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => true],
            'enterprise' => ['ai_chatbot' => true,  'ai_agent' => true,  'ai_widget' => true,  'ai_voice_assistant' => true],
        ];
        return $defaults[$slug] ?? $defaults['free'];
    }
};
