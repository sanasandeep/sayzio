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
 * Coverage for the "fix a bare persona in one click" flow: the dashboard
 * coverage warning carries the uncovered persona slug(s) as a query param,
 * the templates index reads it (callout + missing-tag filter), and the
 * create/edit forms pre-check the persona so tagging is a single save.
 */
class AdminTemplatePersonaCoverageTest extends TestCase
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

    public function test_index_reads_cover_param_and_shows_the_coverage_callout(): void
    {
        $admin = $this->makeAdmin();
        $slug = PersonaCatalog::all()[0]['slug'];
        $label = PersonaCatalog::labelFor($slug);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.index', ['tab' => 'page', 'cover' => $slug]));

        $resp->assertOk();
        $resp->assertSee('Covering');
        $resp->assertSee($label);
        // The Alpine cover filter must be seeded with the slug.
        $resp->assertSee($slug);
    }

    public function test_index_drops_bogus_cover_slugs(): void
    {
        $admin = $this->makeAdmin();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.index', ['tab' => 'page', 'cover' => 'not-a-real-persona']));

        $resp->assertOk();
        $resp->assertDontSee('Covering an uncovered persona');
        $resp->assertDontSee('Covering uncovered personas');
    }

    public function test_create_form_pre_checks_the_persona_from_the_param(): void
    {
        $admin = $this->makeAdmin();
        $slug = PersonaCatalog::all()[0]['slug'];

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.create', ['kind' => 'page', 'persona' => $slug]));

        $resp->assertOk();
        $resp->assertSee('Pre-checked to cover');
        $resp->assertSee(
            '<input type="checkbox" name="recommended_personas[]" value="' . $slug . '" checked',
            false
        );
    }

    public function test_edit_form_pre_checks_the_persona_additively(): void
    {
        $admin = $this->makeAdmin();
        $slugs = PersonaCatalog::slugs();
        $existing = $slugs[0];
        $toCover = $slugs[1];

        $tpl = PageTemplate::create([
            'slug'                 => 'coverage-edit-' . uniqid(),
            'name'                 => 'Coverage Edit',
            'category'             => 'general',
            'description'          => 'x',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [$existing],
            'snapshot'             => ['blocks' => []],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.templates.edit', ['kind' => 'page', 'id' => $tpl->id, 'persona' => $toCover]));

        $resp->assertOk();
        // Existing tag stays checked, and the covered persona is added.
        $resp->assertSee(
            '<input type="checkbox" name="recommended_personas[]" value="' . $existing . '" checked',
            false
        );
        $resp->assertSee(
            '<input type="checkbox" name="recommended_personas[]" value="' . $toCover . '" checked',
            false
        );
    }

    public function test_dashboard_warning_link_carries_the_uncovered_persona_slug(): void
    {
        $admin = $this->makeAdmin();

        // Seed exactly one active template tagged for the first persona, so
        // every OTHER persona is uncovered and the coverage warning fires.
        $covered = PersonaCatalog::all()[0]['slug'];
        PageTemplate::create([
            'slug'                 => 'coverage-seed-' . uniqid(),
            'name'                 => 'Coverage Seed',
            'category'             => 'general',
            'description'          => 'x',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [$covered],
            'snapshot'             => ['blocks' => []],
        ]);

        \App\Modules\Common\Support\TemplateGalleryHealth::flush();

        $uncovered = PersonaCatalog::all()[1]['slug'];

        $resp = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $resp->assertOk();
        // The "Manage templates" button must carry the uncovered slug as cover=.
        $resp->assertSee('cover=' . $uncovered, false);
    }
}
