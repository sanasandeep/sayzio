<?php

use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->text('intro')->nullable()->after('meta_description');
            $table->date('last_updated_at')->nullable()->after('intro');
            $table->boolean('show_toc')->default(true)->after('last_updated_at');
        });

        $now = now();
        $policySlugs = ['terms', 'privacy', 'refunds', 'cookies', 'gdpr'];
        $defaults = SitePagesContent::policyDefaults();

        foreach ($policySlugs as $slug) {
            if (!isset($defaults[$slug])) {
                continue;
            }
            $data = $defaults[$slug];
            $existing = DB::table('site_pages')->where('slug', $slug)->first();

            if (!$existing) {
                DB::table('site_pages')->insert([
                    'slug'             => $slug,
                    'title'            => $data['title'],
                    'meta_description' => $data['meta_description'] ?? null,
                    'intro'            => $data['intro'] ?? null,
                    'last_updated_at'  => $data['last_updated_at'] ?? $now->toDateString(),
                    'show_toc'         => true,
                    'sections'         => json_encode(SitePagesContent::normalizeSections($data['sections'])),
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
                continue;
            }

            $current = json_decode($existing->sections ?? '[]', true) ?: [];
            $rich = SitePagesContent::richDefaults()[$slug] ?? null;
            // If the page still holds the previously-seeded rich defaults
            // wholesale (no admin edits), replace them with the richer
            // policy defaults. Otherwise, only append missing sections.
            if ($rich && SitePagesContent::sectionsMatchExactly($current, $rich['sections'] ?? [])) {
                $merged = SitePagesContent::normalizeSections($data['sections']);
            } else {
                $merged = SitePagesContent::mergeMissingSections($current, $data['sections']);
            }

            $newTitle = $existing->title;
            $newMeta  = $existing->meta_description;
            if ($rich && trim((string) $existing->title) === trim((string) ($rich['title'] ?? ''))) {
                $newTitle = $data['title'];
            }
            if ($rich && trim((string) $existing->meta_description) === trim((string) ($rich['meta_description'] ?? ''))) {
                $newMeta = $data['meta_description'] ?? $existing->meta_description;
            }

            DB::table('site_pages')->where('slug', $slug)->update([
                'title'           => $newTitle,
                'meta_description'=> $newMeta,
                'intro'           => $existing->intro ?? ($data['intro'] ?? null),
                'last_updated_at' => $existing->last_updated_at ?? ($data['last_updated_at'] ?? $now->toDateString()),
                'show_toc'        => true,
                'sections'        => json_encode($merged),
                'updated_at'      => $now,
            ]);
        }

        // Make sure every other existing site_page has stable section ids
        // and a visibility flag so the new renderer/editor work uniformly.
        $rows = DB::table('site_pages')->get();
        foreach ($rows as $row) {
            if (in_array($row->slug, $policySlugs, true)) continue;
            $current = json_decode($row->sections ?? '[]', true) ?: [];
            if (empty($current)) continue;
            $normalized = SitePagesContent::normalizeSections($current);
            if (json_encode($normalized) === json_encode($current)) continue;
            DB::table('site_pages')->where('id', $row->id)->update([
                'sections'   => json_encode($normalized),
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->dropColumn(['intro', 'last_updated_at', 'show_toc']);
        });
    }
};
