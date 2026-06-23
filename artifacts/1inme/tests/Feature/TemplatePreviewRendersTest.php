<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Database\Seeders\CardTemplateSeeder;
use Database\Seeders\ExpandedPageTemplateLibrarySeeder;
use Database\Seeders\StarterPageTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end guard for the admin template-preview route
 * ({@see \App\Modules\Admin\Controllers\TemplateController::preview}).
 *
 * The preview reuses TemplateService::buildPreviewLink(), which throws
 * InvalidArgumentException on an unknown/unsupported block type. The
 * controller catches that and renders a readable "can't be previewed"
 * fallback (still HTTP 200) instead of a stack trace — which means a
 * template whose stored snapshot contains a renderer-incompatible block
 * type degrades silently: the route stays 200, but an admin only ever
 * sees the error card.
 *
 * Sibling tests validate the seeders' *in-memory* snapshots
 * ({@see PageTemplateSeedDesignValidityTest}). This test instead drives
 * the real HTTP route against the actual seeded template library so it
 * also catches regressions in the live render path (buildPreviewLink,
 * the sanitizer, the common.biolink view, the block-render partial)
 * that a pure snapshot-shape check would miss.
 *
 * Auth note: the route is gated by AdminAuth + CheckPermission (both read
 * the `admin` guard), while the controller passes `$request->user()` —
 * resolved against the default `web` guard — into buildPreviewLink, which
 * type-hints an App\Modules\User\Models\User. We therefore authenticate
 * BOTH guards (web user last, so it wins the default resolver) to mirror
 * the real previewer: a web user who also holds admin access.
 */
class TemplatePreviewRendersTest extends TestCase
{
    use RefreshDatabase;

    /** A distinctive marker only present in the preview *error* fallback. */
    private const ERROR_MARKER = 'This template can';

    /**
     * Authenticate the admin guard (so the route's AdminAuth +
     * settings.manage permission checks pass) and the web guard (so the
     * controller's `$request->user()` resolves to a real User for
     * buildPreviewLink). The web `be()` runs last so it owns the default
     * guard the controller reads from.
     */
    private function authenticatePreviewer(): void
    {
        $role = Role::firstOrCreate(
            ['slug' => 'tpl-preview-staff'],
            ['name' => 'Template Preview Staff', 'guard' => 'admin'],
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings'],
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $admin = Admin::create([
            'name'     => 'Tpl Admin ' . Str::random(4),
            'email'    => 'tpl' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);

        $webUser = User::create([
            'name'              => 'Preview User',
            'email'             => 'pv' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('x'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $this->be($admin, 'admin');
        $this->be($webUser, 'web');
    }

    /** Seed the full live template library (page + card). */
    private function seedTemplateLibrary(): void
    {
        $this->seed(StarterPageTemplatesSeeder::class);
        $this->seed(ExpandedPageTemplateLibrarySeeder::class);
        $this->seed(CardTemplateSeeder::class);
    }

    /**
     * Drive the preview route for one template and assert it renders a
     * real biolink page (not the error fallback) with non-empty block
     * markup.
     */
    private function assertPreviewsCleanly(string $kind, int $id, string $label): void
    {
        $resp = $this->get(route('admin.templates.preview', ['kind' => $kind, 'id' => $id]));

        $resp->assertOk();

        $html = $resp->getContent();

        $this->assertStringNotContainsString(
            self::ERROR_MARKER,
            $html,
            "{$label} rendered the preview error fallback — its snapshot has a "
            . 'block type the renderer cannot handle. Open the admin design-fix '
            . 'tools to repair it.'
        );

        $this->assertStringContainsString(
            'data-block-id=',
            $html,
            "{$label} previewed without any block markup — expected at least one "
            . 'rendered block in the page.'
        );
    }

    public function test_every_active_page_template_previews_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $this->authenticatePreviewer();

        $templates = PageTemplate::where('is_active', true)->get(['id', 'slug']);
        $this->assertNotEmpty($templates, 'no active page templates were seeded to preview');

        foreach ($templates as $tpl) {
            $this->assertPreviewsCleanly('page', $tpl->id, "page template '{$tpl->slug}'");
        }
    }

    public function test_every_active_card_template_previews_without_blank_blocks(): void
    {
        $this->seedTemplateLibrary();
        $this->authenticatePreviewer();

        $templates = CardTemplate::where('is_active', true)->get(['id', 'slug']);
        $this->assertNotEmpty($templates, 'no active card templates were seeded to preview');

        foreach ($templates as $tpl) {
            $this->assertPreviewsCleanly('card', $tpl->id, "card template '{$tpl->slug}'");
        }
    }

    /**
     * Sanity check on the guard itself: a template with a deliberately
     * broken block type must trip the error fallback (and therefore fail
     * the assertion above). This proves the test would actually catch a
     * renderer-incompatible template rather than passing vacuously.
     */
    public function test_broken_template_hits_the_error_fallback(): void
    {
        $this->authenticatePreviewer();

        $broken = PageTemplate::create([
            'slug'                 => 'broken-' . Str::random(8),
            'name'                 => 'Broken Preview',
            'category'             => 'general',
            'description'          => 'Deliberately broken for the guard test.',
            'is_active'            => true,
            'sort_order'           => 0,
            'recommended_personas' => [],
            'snapshot'             => [
                'blocks' => [
                    ['type' => 'definitely_not_a_real_block_type', 'settings' => []],
                ],
            ],
        ]);

        $resp = $this->get(route('admin.templates.preview', ['kind' => 'page', 'id' => $broken->id]));

        $resp->assertOk();
        $resp->assertSee(self::ERROR_MARKER, false);
    }
}
