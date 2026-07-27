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
 * Regression coverage for ExpandedPageTemplateLibrarySeeder in its
 * neutralized state.
 *
 * The legacy persona blueprint library was retired: blueprintsFor()
 * returns an empty list for every persona, so the seeder must create
 * ZERO page templates, stay idempotent across re-runs, and never touch
 * admin-curated rows. These tests pin that contract so a future edit
 * can't accidentally resurrect the old seeded templates.
 */
class ExpandedPageTemplateLibrarySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_no_templates_on_fresh_db(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $this->assertSame(
            0,
            PageTemplate::count(),
            'the neutralized seeder must not create any page templates'
        );
    }

    public function test_blueprints_for_every_persona_are_empty(): void
    {
        $seeder = new ExpandedPageTemplateLibrarySeeder();
        $personas = PersonaCatalog::all();
        $this->assertNotEmpty($personas, 'no personas configured');

        foreach ($personas as $persona) {
            $this->assertSame(
                [],
                $seeder->blueprintsFor($persona),
                "persona '{$persona['slug']}' should have no blueprints — the legacy library was retired"
            );
        }
    }

    public function test_rerunning_seeder_is_idempotent_and_leaves_admin_rows_untouched(): void
    {
        (new BgTemplateSeeder())->run();

        // An admin-curated template must survive any number of seeder runs.
        $admin = PageTemplate::create([
            'slug'                 => 'admin-curated-template',
            'name'                 => 'Admin Curated',
            'category'             => 'general',
            'description'          => 'Hand-built by an admin; the seeder must never touch it.',
            'thumbnail_url'        => null,
            'plan_tier'            => null,
            'is_active'            => true,
            'sort_order'           => 10,
            'recommended_personas' => [PersonaCatalog::all()[0]['slug']],
            'snapshot'             => ['biolink' => [], 'blocks' => [], 'meta' => ['admin' => true]],
        ]);

        (new ExpandedPageTemplateLibrarySeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $this->assertSame(1, PageTemplate::count(), 're-running the seeder must not create or delete rows');

        $reloaded = PageTemplate::find($admin->id);
        $this->assertNotNull($reloaded, 'admin-curated row must survive seeder re-runs');
        $this->assertSame('Admin Curated', $reloaded->name);
    }

    public function test_auto_refresh_skips_persona_namespace_rows_without_a_blueprint(): void
    {
        // A leftover persona-namespace row (e.g. restored from a backup)
        // has no current blueprint anymore; the auto-refresh pass must
        // leave it alone rather than deleting or rewriting it.
        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $row = PageTemplate::create([
            'slug'                 => 'persona-' . $personaSlug . '-legacy-remnant',
            'name'                 => 'Legacy Remnant',
            'category'             => $personaSlug,
            'description'          => 'Persona-namespace row with no current blueprint.',
            'is_active'            => true,
            'sort_order'           => 1,
            'recommended_personas' => [$personaSlug],
            'snapshot'             => ['biolink' => [], 'blocks' => [], 'meta' => ['seed_version' => 0]],
        ]);

        (new ExpandedPageTemplateLibrarySeeder())->run();

        $survivor = PageTemplate::find($row->id);
        $this->assertNotNull($survivor, 'rows without a current blueprint must not be auto-refreshed away');
        $this->assertSame('Legacy Remnant', $survivor->name);
    }

    public function test_seeder_works_when_bg_templates_table_is_empty(): void
    {
        $this->assertSame(0, BgTemplate::count(), 'precondition: bg_templates is empty');

        (new ExpandedPageTemplateLibrarySeeder())->run();

        $this->assertSame(0, PageTemplate::count(), 'still creates nothing with empty bg_templates');
    }
}
