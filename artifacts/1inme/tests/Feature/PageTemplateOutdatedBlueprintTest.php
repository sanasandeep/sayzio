<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Services\PersonaCatalog;
use Database\Seeders\BgTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin "Outdated design" badge + diff/reset flow.
 *
 * Pins the per-row classification logic (only persona-seeded rows that
 * have a current blueprint AND an older stored seed_version qualify),
 * and the blueprint-reset action that lets admins one-click roll a
 * customized row forward to the latest design.
 */
class PageTemplateOutdatedBlueprintTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /** Pick one persona-seeded row and stamp it back to seed_version 0. */
    private function seedAndDowngradeOneRow(): PageTemplate
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $row = PageTemplate::query()
            ->where('slug', 'like', 'persona-' . $personaSlug . '-%')
            ->orderBy('id')
            ->firstOrFail();

        $snap = (array) $row->snapshot;
        $snap['meta']['seed_version'] = 0;
        DB::table('page_templates')->where('id', $row->id)->update([
            'snapshot' => json_encode($snap),
            // Mark as admin-edited so autoRefreshStaleVersions leaves
            // it alone — the badge/diff path is exactly for this case.
            'updated_at' => $row->created_at->copy()->addMinutes(5),
        ]);
        return $row->refresh();
    }

    public function test_is_outdated_blueprint_flags_persona_rows_with_older_seed_version(): void
    {
        $row = $this->seedAndDowngradeOneRow();

        $this->assertTrue($row->isOutdatedBlueprint(), 'persona row at older seed_version must be flagged outdated');
        $this->assertSame(0, $row->seedVersion());
        $this->assertNotNull($row->personaSeedSlug());
        $this->assertNotNull($row->currentBlueprint());
    }

    public function test_admin_added_persona_namespace_row_is_not_flagged(): void
    {
        // A row admin-added inside the persona namespace but with a
        // blueprint key the seeder doesn't recognize must NOT show the
        // outdated badge — there's no current blueprint to compare to.
        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $row = PageTemplate::create([
            'slug'                 => 'persona-' . $personaSlug . '-admin-only-key',
            'name'                 => 'Curator Pick',
            'category'             => $personaSlug,
            'description'          => 'Hand-built by an admin.',
            'thumbnail_url'        => null,
            'plan_tier'            => null,
            'is_active'            => true,
            'sort_order'           => 500,
            'recommended_personas' => [$personaSlug],
            'snapshot'             => ['biolink' => [], 'blocks' => [], 'meta' => []],
        ]);

        $this->assertNull($row->currentBlueprint());
        $this->assertFalse($row->isOutdatedBlueprint());
    }

    public function test_non_persona_template_is_never_flagged(): void
    {
        $row = PageTemplate::create([
            'slug'                 => 'curator-handcrafted',
            'name'                 => 'Curator Handcrafted',
            'category'             => 'general',
            'description'          => 'Not part of the persona namespace.',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => ['blocks' => []],
        ]);

        $this->assertNull($row->personaSeedSlug());
        $this->assertFalse($row->isOutdatedBlueprint());
    }

    public function test_current_seed_version_row_is_not_flagged(): void
    {
        (new BgTemplateSeeder())->run();
        (new ExpandedPageTemplateLibrarySeeder())->run();

        $personaSlug = PersonaCatalog::all()[0]['slug'];
        $row = PageTemplate::query()
            ->where('slug', 'like', 'persona-' . $personaSlug . '-%')
            ->firstOrFail();

        $this->assertSame(ExpandedPageTemplateLibrarySeeder::SEED_VERSION, $row->seedVersion());
        $this->assertFalse($row->isOutdatedBlueprint());
    }

    public function test_blueprint_diff_route_renders_for_outdated_row(): void
    {
        $row = $this->seedAndDowngradeOneRow();
        $admin = $this->makeAdmin();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.blueprint.diff', ['id' => $row->id]));

        $resp->assertOk();
        $resp->assertSee('Stored blueprint v0');
        $resp->assertSee('current design v' . ExpandedPageTemplateLibrarySeeder::SEED_VERSION, false);
        $resp->assertSee($row->slug);
    }

    public function test_reset_blueprint_action_replaces_snapshot_and_bumps_version(): void
    {
        $row = $this->seedAndDowngradeOneRow();
        $admin = $this->makeAdmin();

        // Mutate the row so we can prove reset overwrites it.
        $mutated = (array) $row->snapshot;
        $mutated['blocks'] = [['type' => 'paragraph', 'settings' => ['text' => 'OLD'], 'is_active' => true]];
        DB::table('page_templates')->where('id', $row->id)->update([
            'name'     => 'Stale Name',
            'snapshot' => json_encode($mutated),
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.blueprint.reset', ['id' => $row->id]));

        $resp->assertRedirect(route('admin.templates.index', ['tab' => 'page']));

        $fresh = PageTemplate::find($row->id);
        $this->assertSame(
            ExpandedPageTemplateLibrarySeeder::SEED_VERSION,
            $fresh->seedVersion(),
            'reset must stamp the current SEED_VERSION onto the row'
        );
        $this->assertNotSame('Stale Name', $fresh->name, 'reset must restore the blueprint name');
        $blocks = (array) (((array) $fresh->snapshot)['blocks'] ?? []);
        $this->assertGreaterThan(1, count($blocks), 'reset must restore real blueprint blocks, not the OLD sentinel');
        $this->assertFalse($fresh->isOutdatedBlueprint(), 'row must no longer be flagged outdated after reset');
    }

    public function test_reset_blueprint_refuses_for_non_persona_row(): void
    {
        $row = PageTemplate::create([
            'slug'                 => 'curator-handcrafted-' . uniqid(),
            'name'                 => 'Curator Handcrafted',
            'category'             => 'general',
            'description'          => 'No blueprint to reset to.',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => ['blocks' => []],
        ]);
        $admin = $this->makeAdmin();

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.blueprint.reset', ['id' => $row->id]));

        $resp->assertRedirect(route('admin.templates.index', ['tab' => 'page']));
        // Snapshot untouched — still the empty-blocks row we created.
        $fresh = PageTemplate::find($row->id);
        $this->assertSame([], (array) (((array) $fresh->snapshot)['blocks'] ?? []));
    }
}
