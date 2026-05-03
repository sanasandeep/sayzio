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
