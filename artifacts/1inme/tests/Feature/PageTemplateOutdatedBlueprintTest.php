<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin "Outdated design" badge + blueprint-reset flow
 * in the retired-library state.
 *
 * The legacy persona blueprints were retired, so currentBlueprint()
 * returns null for every persona-namespace row. That means NO row can
 * ever be flagged outdated anymore, and the reset action must refuse to
 * touch any row — these tests pin exactly that, so a leftover row
 * restored from a backup can't be silently rewritten.
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

    /** A leftover persona-namespace row stamped with an old seed_version. */
    private function makeLegacyPersonaRow(): PageTemplate
    {
        $personaSlug = PersonaCatalog::all()[0]['slug'];
        return PageTemplate::create([
            'slug'                 => 'persona-' . $personaSlug . '-legacy-remnant',
            'name'                 => 'Legacy Remnant',
            'category'             => $personaSlug,
            'description'          => 'Row left over from the retired seeded library.',
            'is_active'            => true,
            'sort_order'           => 1,
            'recommended_personas' => [$personaSlug],
            'snapshot'             => [
                'biolink' => [],
                'blocks'  => [['type' => 'paragraph', 'settings' => ['text' => 'OLD'], 'is_active' => true]],
                'meta'    => ['seed_version' => 0],
            ],
        ]);
    }

    public function test_persona_row_with_old_seed_version_is_not_flagged_without_a_blueprint(): void
    {
        $row = $this->makeLegacyPersonaRow();

        $this->assertSame(0, $row->seedVersion());
        $this->assertNotNull($row->personaSeedSlug());
        $this->assertNull($row->currentBlueprint(), 'retired library must yield no current blueprint');
        $this->assertFalse(
            $row->isOutdatedBlueprint(),
            'a row without a current blueprint must never be flagged outdated'
        );
    }

    public function test_admin_added_persona_namespace_row_is_not_flagged(): void
    {
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

    public function test_reset_blueprint_refuses_for_persona_row_without_a_blueprint(): void
    {
        $row = $this->makeLegacyPersonaRow();
        $admin = $this->makeAdmin();

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.templates.blueprint.reset', ['id' => $row->id]));

        $resp->assertRedirect(route('admin.templates.index', ['tab' => 'page']));

        // Snapshot untouched — reset must refuse without a current blueprint.
        $fresh = PageTemplate::find($row->id);
        $this->assertSame('Legacy Remnant', $fresh->name);
        $this->assertSame(0, $fresh->seedVersion());
        $blocks = (array) (((array) $fresh->snapshot)['blocks'] ?? []);
        $this->assertCount(1, $blocks, 'reset must not rewrite the snapshot when no blueprint exists');
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
        $fresh = PageTemplate::find($row->id);
        $this->assertSame([], (array) (((array) $fresh->snapshot)['blocks'] ?? []));
    }
}
