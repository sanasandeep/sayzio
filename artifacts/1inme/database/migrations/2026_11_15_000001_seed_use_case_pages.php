<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seeds (idempotently) one SitePage row per "1INME for X" use-case
     * landing page, stored under the "for-{persona}" slug. Existing rows
     * are left untouched so admin edits are preserved.
     */
    public function up(): void
    {
        $now = now();
        foreach (SitePagesContent::useCasesDefault() as $slug => $data) {
            $persona = \Illuminate\Support\Str::after($slug, 'for-');
            $useCaseExtra = SitePagesContent::useCaseExtraDefault($persona);
            $existing = DB::table('site_pages')->where('slug', $slug)->first();
            if ($existing) {
                // Backfill the editable extra.use_case payload onto rows that
                // were seeded before it existed, without touching admin copy.
                $extra = json_decode((string) ($existing->extra ?? ''), true);
                $extra = is_array($extra) ? $extra : [];
                if (empty($extra['use_case'])) {
                    $extra['use_case'] = $useCaseExtra;
                    DB::table('site_pages')->where('id', $existing->id)->update([
                        'extra'      => json_encode($extra),
                        'updated_at' => $now,
                    ]);
                }
                continue;
            }
            DB::table('site_pages')->insert([
                'slug'             => $slug,
                'title'            => $data['title'],
                'meta_description' => $data['meta_description'] ?? null,
                'sections'         => json_encode($data['sections'] ?? []),
                'extra'            => json_encode(['use_case' => $useCaseExtra]),
                'cta_label'        => $data['cta_label'] ?? null,
                'cta_url'          => $data['cta_url'] ?? null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Per CONTRIBUTING.md "Backfill / seed migration down() policy":
        // remove a use-case SitePage row ONLY if its content still matches
        // the seeded default for that slug, so admin edits are preserved.
        $defaults = SitePagesContent::useCasesDefault();
        foreach ($defaults as $slug => $seed) {
            $row = DB::table('site_pages')->where('slug', $slug)->first();
            if (!$row) {
                continue;
            }
            $rowSections  = json_decode((string) ($row->sections ?? ''), true);
            $seedSections = $seed['sections'] ?? [];
            // The editable hero/feature/FAQ payload (extra.use_case) must also
            // still match its seeded default, otherwise an admin has edited it
            // and the row must be preserved.
            $persona       = \Illuminate\Support\Str::after($slug, 'for-');
            $rowExtra      = json_decode((string) ($row->extra ?? ''), true);
            $rowUseCase    = is_array($rowExtra) ? ($rowExtra['use_case'] ?? null) : null;
            $seedUseCase   = SitePagesContent::useCaseExtraDefault($persona);
            $extraMatches  = $rowUseCase === null || $rowUseCase == $seedUseCase;
            $matches = $row->title === $seed['title']
                && ($row->meta_description ?? null) === ($seed['meta_description'] ?? null)
                && $rowSections == $seedSections
                && ($row->cta_label ?? null) === ($seed['cta_label'] ?? null)
                && ($row->cta_url ?? null) === ($seed['cta_url'] ?? null)
                && $extraMatches;
            if ($matches) {
                DB::table('site_pages')->where('id', $row->id)->delete();
            }
        }
    }
};
