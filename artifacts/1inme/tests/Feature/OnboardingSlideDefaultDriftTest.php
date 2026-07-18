<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\OnboardingSlide;
use App\Modules\Admin\Models\Role;
use Database\Seeders\OnboardingSlidesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the "customized vs default" detection for the mobile intro carousel.
 *
 * The intro slides ship as a bundled copy in the mobile app AND as seeded
 * rows in the DB. A static guard (test-onboarding-slides-sync.mjs) keeps the
 * two baked-in copies in lockstep, but once an admin edits a live row in the
 * admin UI, that DB copy is served to the app and can silently diverge from
 * the shipped default with no guard. These tests pin the runtime detection
 * that surfaces such drift to the admin (badge + banner in the index view).
 */
class OnboardingSlideDefaultDriftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seeding reads the public disk for images; fake it so the seed is
        // deterministic and does not touch S3.
        Storage::fake('public');
    }

    public function test_freshly_seeded_slides_all_report_default(): void
    {
        (new OnboardingSlidesSeeder())->run();

        $this->assertGreaterThan(0, OnboardingSlide::count());

        foreach (OnboardingSlide::all() as $slide) {
            $this->assertSame(
                'default',
                $slide->customizationState(),
                "freshly seeded slide '{$slide->slug}' should match its shipped default"
            );
            $this->assertSame([], $slide->driftedFields());
            $this->assertFalse($slide->hasDriftedFromDefault());
        }
    }

    public function test_editing_a_slide_body_flags_it_as_customized(): void
    {
        (new OnboardingSlidesSeeder())->run();

        $slide = OnboardingSlide::where('slug', 'welcome')->firstOrFail();
        $slide->update(['body' => 'A totally different pitch than the shipped default.']);

        $slide->refresh();
        $this->assertSame('customized', $slide->customizationState());
        $this->assertTrue($slide->hasDriftedFromDefault());
        $this->assertSame(['body'], $slide->driftedFields());
    }

    public function test_editing_multiple_copy_fields_lists_all_drifted_fields(): void
    {
        (new OnboardingSlidesSeeder())->run();

        $slide = OnboardingSlide::where('slug', 'creators')->firstOrFail();
        $slide->update([
            'category' => 'For makers',
            'title'    => 'A brand new headline',
        ]);

        $slide->refresh();
        $this->assertSame('customized', $slide->customizationState());
        $this->assertEqualsCanonicalizing(['category', 'title'], $slide->driftedFields());
    }

    public function test_admin_created_slug_reports_custom_not_drifted(): void
    {
        $slide = OnboardingSlide::create([
            'slug'       => 'my-bespoke-slide',
            'category'   => 'Special',
            'title'      => 'Hand-written slide',
            'body'       => 'This one has no shipped default.',
            'status'     => 'active',
            'sort_order' => 999,
        ]);

        $this->assertSame('custom', $slide->customizationState());
        $this->assertFalse($slide->hasDriftedFromDefault());
        $this->assertSame([], $slide->driftedFields());
    }

    public function test_seeded_defaults_cover_every_seeded_slug(): void
    {
        (new OnboardingSlidesSeeder())->run();

        $defaults = OnboardingSlide::seededDefaults();
        foreach (OnboardingSlide::pluck('slug') as $slug) {
            $this->assertArrayHasKey(
                $slug,
                $defaults,
                "seeded slug '{$slug}' has no matching default entry"
            );
        }
    }

    public function test_admin_index_surfaces_customized_badge_and_banner(): void
    {
        (new OnboardingSlidesSeeder())->run();

        OnboardingSlide::where('slug', 'welcome')
            ->firstOrFail()
            ->update(['title' => 'Edited headline that drifts']);

        $admin = $this->makeSettingsAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.onboarding-slides.index'));

        $response->assertOk();
        $response->assertSee('Customized');
        $response->assertSee('edited away from the shipped default wording');
        $response->assertSee('welcome');
    }

    /**
     * Build a super-admin (which holds every permission, including the
     * settings.manage the onboarding-slides routes require).
     */
    private function makeSettingsAdmin(): Admin
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
}
