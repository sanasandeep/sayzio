<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BgTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Services\PersonaCatalog;
use Database\Seeders\BgTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for ExpandedPageTemplateLibrarySeeder.
 *
 * The seeder is the single source of variety for every persona's
 * onboarding "Recommended for you" shelf. If a future edit silently
 * drops the bg-template lookup, breaks idempotency, or collapses the
 * variant bank to one look, the shelf goes back to feeling like ten
 * copies of the same beige starter page. These tests pin the four
 * properties that matter so that regression shows up loudly in CI.
 */
class ExpandedPageTemplateLibrarySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_exactly_ten_templates_per_persona_on_fresh_db(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        foreach (PersonaCatalog::all() as $persona) {
            $slug = $persona['slug'];
            $count = PageTemplate::query()
                ->where('is_active', true)
                ->where('recommended_personas', 'like', '%"' . $slug . '"%')
                ->count();
            $this->assertSame(
                10,
                $count,
                "Persona '{$slug}' should have exactly 10 active recommended templates, got {$count}."
            );
        }
    }

    public function test_rerunning_seeder_is_idempotent_and_does_not_overwrite_existing_slugs(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $beforeCount = PageTemplate::count();
        $this->assertGreaterThan(0, $beforeCount, 'first seeder run should have created templates');

        // Mutate one persona-seeded template — re-running must NOT overwrite it,
        // because the seeder is meant to defer to admin curation once a slug exists.
        $sample = PageTemplate::query()->where('slug', 'like', 'persona-%')->firstOrFail();
        $sample->update([
            'name'        => 'ADMIN EDITED — do not touch',
            'description' => 'Curator note that must survive re-seeding.',
        ]);
        $editedSlug = $sample->slug;
        $editedName = $sample->name;
        $editedDesc = $sample->description;

        (new ExpandedPageTemplateLibrarySeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $this->assertSame(
            $beforeCount,
            PageTemplate::count(),
            're-running seeder must not create duplicate templates'
        );

        // Slugs are unique enforced at DB level; double-check no duplicates by slug.
        $duplicateSlugs = PageTemplate::query()
            ->selectRaw('slug, count(*) as c')
            ->groupBy('slug')
            ->havingRaw('count(*) > 1')
            ->get();
        $this->assertCount(0, $duplicateSlugs, 'no slug should appear more than once');

        $reloaded = PageTemplate::where('slug', $editedSlug)->firstOrFail();
        $this->assertSame($editedName, $reloaded->name, 'admin name edits must not be overwritten');
        $this->assertSame($editedDesc, $reloaded->description, 'admin description edits must not be overwritten');
    }

    public function test_single_persona_variants_use_at_least_five_distinct_bg_button_combos(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        // Use the first persona; the seeder builds the same 10-variant bank
        // for every persona, so this is representative of the whole shelf.
        $personaSlug = PersonaCatalog::all()[0]['slug'];

        $templates = PageTemplate::query()
            ->where('recommended_personas', 'like', '%"' . $personaSlug . '"%')
            ->get();
        $this->assertCount(10, $templates, "expected 10 templates for persona '{$personaSlug}'");

        $combos = [];
        foreach ($templates as $tpl) {
            $biolink = $tpl->snapshot['biolink'] ?? [];
            $bgType = $biolink['background_type'] ?? null;
            $btnStyle = $biolink['button_style'] ?? null;
            $this->assertNotNull($bgType, "variant '{$tpl->slug}' is missing background_type");
            $this->assertNotNull($btnStyle, "variant '{$tpl->slug}' is missing button_style");
            $combos[$bgType . '|' . $btnStyle] = true;
        }

        $this->assertGreaterThanOrEqual(
            5,
            count($combos),
            "persona '{$personaSlug}' shelf should show at least 5 distinct background_type/button_style "
            . 'combinations to feel varied; got ' . count($combos) . ': ' . json_encode(array_keys($combos))
        );
    }

    public function test_seeded_rows_are_stamped_with_current_seed_version(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $rows = PageTemplate::query()->where('slug', 'like', 'persona-%')->get();
        $this->assertGreaterThan(0, $rows->count());

        foreach ($rows as $row) {
            $this->assertSame(
                ExpandedPageTemplateLibrarySeeder::SEED_VERSION,
                (int) (((array) $row->snapshot)['meta']['seed_version'] ?? -1),
                "row {$row->slug} should carry the current SEED_VERSION in snapshot.meta"
            );
        }
    }

    public function test_rerun_auto_refreshes_untouched_rows_with_older_seed_version(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        // Pick two rows in the persona namespace. We will simulate one
        // as "untouched but stamped with an older seed version" and
        // another as "admin-edited" (timestamp drift) so we can prove
        // the auto-refresh flips only the first.
        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $rows = PageTemplate::query()
            ->where('slug', 'like', 'persona-' . $personaSlug . '-%')
            ->orderBy('id')
            ->get();
        $this->assertGreaterThanOrEqual(2, $rows->count());

        $stale = $rows[0];
        $edited = $rows[1];

        // Downgrade `stale`'s stored seed_version and rewrite its
        // snapshot to something obviously different so we can detect
        // whether the row was recreated.
        $staleSnapshot = (array) $stale->snapshot;
        $staleSnapshot['meta']['seed_version'] = 0;
        $staleSnapshot['blocks'] = [['type' => 'paragraph', 'settings' => ['text' => 'OLD DESIGN'], 'is_active' => true]];
        \DB::table('page_templates')->where('id', $stale->id)->update([
            'snapshot'   => json_encode($staleSnapshot),
            // Keep updated_at == created_at so it counts as untouched.
            'updated_at' => $stale->created_at,
        ]);
        $staleId = $stale->id;
        $staleSlug = $stale->slug;

        // Mark `edited` as admin-edited via a clear updated_at drift,
        // and also stamp it with an older seed_version. Auto-refresh
        // must skip it (timestamp wins over version).
        $editedSnapshot = (array) $edited->snapshot;
        $editedSnapshot['meta']['seed_version'] = 0;
        \DB::table('page_templates')->where('id', $edited->id)->update([
            'name'       => 'ADMIN EDITED',
            'snapshot'   => json_encode($editedSnapshot),
            'updated_at' => $edited->created_at->copy()->addMinutes(5),
        ]);
        $editedId = $edited->id;

        // Re-run the seeder. The auto-refresh pass should delete the
        // stale row, then the fill loop should recreate it with the
        // current blueprint (and current SEED_VERSION).
        (new ExpandedPageTemplateLibrarySeeder())->run();

        // Stale row was deleted and refilled — old id is gone, new row
        // at the same slug carries the current seed_version and the
        // real blueprint blocks (not 'OLD DESIGN').
        $this->assertNull(
            PageTemplate::find($staleId),
            'untouched row with older seed_version should have been deleted'
        );
        $refilled = PageTemplate::where('slug', $staleSlug)->first();
        $this->assertNotNull($refilled, 'auto-refreshed slug should be recreated by the fill loop');
        $this->assertSame(
            ExpandedPageTemplateLibrarySeeder::SEED_VERSION,
            (int) (((array) $refilled->snapshot)['meta']['seed_version'] ?? -1),
            'recreated row should carry the current SEED_VERSION'
        );
        $blocks = (array) (((array) $refilled->snapshot)['blocks'] ?? []);
        $this->assertGreaterThan(
            1,
            count($blocks),
            'recreated row should use the real blueprint, not the OLD DESIGN stub'
        );

        // Edited row survived untouched — same id, same admin name.
        $survivor = PageTemplate::find($editedId);
        $this->assertNotNull($survivor, 'admin-edited row must not be deleted by auto-refresh');
        $this->assertSame('ADMIN EDITED', $survivor->name);
    }

    public function test_auto_refresh_recreates_stale_slug_even_when_persona_already_meets_minimum(): void
    {
        // Regression for the case where a persona already has >= MIN_PER_PERSONA
        // active templates because admins added their own. The count-based
        // gap-fill in run() will skip the persona entirely, so the
        // auto-refresh path itself must recreate the stale slug.
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $personaSlug = PersonaCatalog::all()[0]['slug'];

        // Pad the persona with 5 admin-added templates so it has 15
        // active recommended templates total — well above MIN_PER_PERSONA.
        for ($i = 0; $i < 5; $i++) {
            PageTemplate::create([
                'slug'                 => 'admin-extra-' . $personaSlug . '-' . $i,
                'name'                 => 'Admin Extra ' . $i,
                'category'             => $personaSlug,
                'description'          => 'Curator-added template that should never be touched.',
                'thumbnail_url'        => 'https://example.com/thumb.png',
                'plan_tier'            => null,
                'is_active'            => true,
                'sort_order'           => 200 + $i,
                'recommended_personas' => [$personaSlug],
                'snapshot'             => ['biolink' => [], 'blocks' => [], 'meta' => ['admin' => true]],
            ]);
        }

        // Pick one persona-seeded row, downgrade its seed_version, and
        // mark its blocks with a sentinel so we can prove it was
        // recreated from the current blueprint.
        $stale = PageTemplate::query()
            ->where('slug', 'like', 'persona-' . $personaSlug . '-%')
            ->orderBy('id')
            ->firstOrFail();
        $staleSnapshot = (array) $stale->snapshot;
        $staleSnapshot['meta']['seed_version'] = 0;
        $staleSnapshot['blocks'] = [['type' => 'paragraph', 'settings' => ['text' => 'OLD'], 'is_active' => true]];
        \DB::table('page_templates')->where('id', $stale->id)->update([
            'snapshot'   => json_encode($staleSnapshot),
            'updated_at' => $stale->created_at,
        ]);
        $staleId = $stale->id;
        $staleSlug = $stale->slug;

        (new ExpandedPageTemplateLibrarySeeder())->run();

        // Old row gone, slug recreated with current SEED_VERSION and
        // real blueprint blocks (not the OLD sentinel).
        $this->assertNull(PageTemplate::find($staleId));
        $refilled = PageTemplate::where('slug', $staleSlug)->first();
        $this->assertNotNull($refilled, 'stale slug must be recreated even when persona already meets MIN_PER_PERSONA');
        $this->assertSame(
            ExpandedPageTemplateLibrarySeeder::SEED_VERSION,
            (int) (((array) $refilled->snapshot)['meta']['seed_version'] ?? -1)
        );
        $blocks = (array) (((array) $refilled->snapshot)['blocks'] ?? []);
        $this->assertGreaterThan(1, count($blocks));

        // Admin-added templates were left exactly as they were.
        $adminCount = PageTemplate::query()
            ->where('slug', 'like', 'admin-extra-' . $personaSlug . '-%')
            ->count();
        $this->assertSame(5, $adminCount);
    }

    public function test_auto_refresh_is_a_no_op_when_every_row_is_at_current_version(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $idsBefore = PageTemplate::query()->orderBy('id')->pluck('id')->all();
        $countBefore = count($idsBefore);
        $this->assertGreaterThan(0, $countBefore);

        // Re-run several times — no row should be deleted or recreated.
        (new ExpandedPageTemplateLibrarySeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $idsAfter = PageTemplate::query()->orderBy('id')->pluck('id')->all();
        $this->assertSame(
            $idsBefore,
            $idsAfter,
            'rerunning the seeder when every row is current must not churn rows'
        );
    }

    public function test_seeder_works_when_bg_templates_table_is_empty(): void
    {
        // Intentionally do NOT run BgTemplateSeeder — bg_templates stays empty
        // and the seeder must engage its gradient/solid fallbacks instead of
        // crashing on a missing template lookup.
        $this->assertSame(0, BgTemplate::count(), 'precondition: bg_templates is empty');

        (new ExpandedPageTemplateLibrarySeeder())->run();

        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $templates = PageTemplate::query()
            ->where('recommended_personas', 'like', '%"' . $personaSlug . '"%')
            ->get();

        $this->assertCount(10, $templates, 'seeder must still produce 10 variants when bg_templates is empty');

        foreach ($templates as $tpl) {
            $biolink = $tpl->snapshot['biolink'] ?? [];
            $bgType = $biolink['background_type'] ?? null;
            $this->assertNotSame(
                'template',
                $bgType,
                "variant '{$tpl->slug}' should not reference a bg_template when the table is empty; "
                . 'expected gradient/color/image fallback.'
            );
            $this->assertArrayNotHasKey(
                'bg_template_id',
                $biolink,
                "variant '{$tpl->slug}' should not carry a bg_template_id when fallbacks are engaged."
            );
        }
    }
}
