<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds (idempotently) the WhatsApp Agent marketing surfaces. The
     * AI suite pages / plan features were already seeded by an earlier
     * migration that ran before the WhatsApp Agent existed, so this is a
     * pure additive top-up for existing databases:
     *
     *  - Inserts the `whatsapp-agent` SitePage row (the public page
     *    controller does firstOrFail(), so the row must exist).
     *  - Appends the WhatsApp Agent feature to the existing /features
     *    "AI suite" category, only if it is not already present, so admin
     *    edits to that category are preserved.
     *
     * Plan-feature gating is intentionally NOT touched: AiPlanAccess
     * already resolves `whatsapp_agent` (paid plans only) via its own
     * fallback, and plan/pricing changes are out of scope here.
     */
    public function up(): void
    {
        $this->seedSitePage();
        $this->backfillFeaturesPageItem();
    }

    public function down(): void
    {
        // 1. Remove the WhatsApp Agent SitePage row ONLY if its content
        //    still matches the seeded default (preserve admin edits).
        $seed = SitePagesContent::aiProductsDefault()['whatsapp-agent'] ?? null;
        if ($seed) {
            $row = DB::table('site_pages')->where('slug', 'whatsapp-agent')->first();
            if ($row) {
                $rowSections = json_decode((string) ($row->sections ?? ''), true);
                $matches = $row->title === $seed['title']
                    && ($row->meta_description ?? null) === ($seed['meta_description'] ?? null)
                    && $rowSections == ($seed['sections'] ?? [])
                    && ($row->cta_label ?? null) === ($seed['cta_label'] ?? null)
                    && ($row->cta_url ?? null) === ($seed['cta_url'] ?? null);
                if ($matches) {
                    DB::table('site_pages')->where('id', $row->id)->delete();
                }
            }
        }

        // 2. Strip the WhatsApp Agent feature from the /features "AI suite"
        //    category ONLY if the in-place item still equals the seeded one.
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if ($row) {
            $sections = json_decode($row->sections ?? '[]', true) ?: [];
            $seedItem = $this->whatsappFeatureItem();
            $changed  = false;
            foreach ($sections as &$s) {
                if (!is_array($s) || trim((string) ($s['id'] ?? '')) !== 'ai-suite') {
                    continue;
                }
                $features = $s['features'] ?? [];
                if (!is_array($features)) {
                    continue;
                }
                $kept = [];
                foreach ($features as $f) {
                    if ($f == $seedItem) {
                        $changed = true;
                        continue;
                    }
                    $kept[] = $f;
                }
                $s['features'] = array_values($kept);
            }
            unset($s);
            if ($changed) {
                DB::table('site_pages')->where('id', $row->id)->update([
                    'sections'   => json_encode(array_values($sections)),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedSitePage(): void
    {
        $data = SitePagesContent::aiProductsDefault()['whatsapp-agent'] ?? null;
        if (!$data) {
            return;
        }
        if (DB::table('site_pages')->where('slug', 'whatsapp-agent')->exists()) {
            return;
        }
        $now = now();
        DB::table('site_pages')->insert([
            'slug'             => 'whatsapp-agent',
            'title'            => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections'         => json_encode($data['sections'] ?? []),
            'cta_label'        => $data['cta_label'] ?? null,
            'cta_url'          => $data['cta_url'] ?? null,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
    }

    private function backfillFeaturesPageItem(): void
    {
        $row = DB::table('site_pages')->where('slug', 'features')->first();
        if (!$row) {
            // No features page yet — first visit / SitePagesSeeder will
            // create it from aiSuiteFeaturesCategory(), already including
            // the WhatsApp Agent feature.
            return;
        }
        $sections = json_decode($row->sections ?? '[]', true) ?: [];
        $seedItem = $this->whatsappFeatureItem();
        $found    = false;
        $changed  = false;
        foreach ($sections as &$s) {
            if (!is_array($s) || trim((string) ($s['id'] ?? '')) !== 'ai-suite') {
                continue;
            }
            $found    = true;
            $features = $s['features'] ?? [];
            if (!is_array($features)) {
                $features = [];
            }
            foreach ($features as $f) {
                $link = is_array($f) ? trim((string) ($f['link'] ?? '')) : '';
                $name = is_array($f) ? trim((string) ($f['name'] ?? '')) : '';
                if ($link === '/whatsapp-agent' || strcasecmp($name, 'WhatsApp Agent') === 0) {
                    return; // already present — preserve admin edits
                }
            }
            $features[] = $seedItem;
            $s['features'] = array_values($features);
            $changed = true;
        }
        unset($s);
        if ($found && $changed) {
            DB::table('site_pages')->where('id', $row->id)->update([
                'sections'   => json_encode(array_values($sections)),
                'updated_at' => now(),
            ]);
        }
    }

    private function whatsappFeatureItem(): array
    {
        foreach (SitePagesContent::aiSuiteFeaturesCategory()['features'] ?? [] as $f) {
            if (is_array($f) && trim((string) ($f['link'] ?? '')) === '/whatsapp-agent') {
                return $f;
            }
        }
        return [
            'name'        => 'WhatsApp Agent',
            'description' => 'Build and edit links, QR codes, contact cards, calendar events and file links by chatting on WhatsApp — voice notes and photos understood automatically.',
            'link'        => '/whatsapp-agent',
        ];
    }
};
