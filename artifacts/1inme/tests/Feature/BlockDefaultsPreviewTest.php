<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin Block Defaults live-preview endpoint
 * (POST /admin/block-defaults/{type}/preview).
 *
 * The endpoint builds a transient block from system defaults + the
 * submitted style/content overrides and renders it through the shared
 * public block renderer into a standalone HTML document that the edit
 * screen injects into a sandboxed iframe. Pins:
 *   1. A valid request returns a full HTML document containing the real
 *      rendered block (not the fail-soft placeholder).
 *   2. Submitted style overrides are reflected in the rendered output.
 *   3. Content JSON overrides replace the system placeholder content.
 *   4. Invalid content JSON returns a 422 error envelope.
 *   5. Unknown types 404; guests are redirected to admin login.
 */
class BlockDefaultsPreviewTest extends TestCase
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

    public function test_preview_returns_real_rendered_block_html(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');

        $res = $this->post(route('admin.block-defaults.preview', 'heading'));

        $res->assertOk();
        $res->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $html = $res->getContent();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('data-block-type="heading"', $html);
        $this->assertStringContainsString('preview-stage', $html);
        // Real render, not the fail-soft placeholder frame.
        $this->assertStringNotContainsString('cannot be previewed here', $html);
    }

    public function test_every_registered_block_preview_renders_without_exception_fallback(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        $types = array_unique(array_map(
            [\App\Modules\User\Support\BlockTypeRegistry::class, 'canonical'],
            array_merge(array_keys(\App\Modules\User\Models\BiolinkBlock::TYPES),
                array_keys(\App\Modules\User\Support\BlockTypeRegistry::newTypes()))
        ));
        foreach ($types as $type) {
            foreach ([false, true] as $blank) {
                $res = $this->post(route('admin.block-defaults.preview', $type), [
                    'start_blank' => $blank ? '1' : '0',
                    'style' => ['bg_color' => '#123456', 'text_color' => '#abcdef'],
                ]);
                $this->assertSame(200, $res->getStatusCode(), $type.($blank ? ' blank' : ' defaults').' preview HTTP status');
                $html = $res->getContent();
                $this->assertStringNotContainsString('cannot be previewed here', $html,
                    $type.($blank ? ' blank' : ' defaults'));
                $this->assertStringContainsString('preview-stage', $html, $type);
            }
        }
    }

    public function test_profile_family_accepts_canonical_and_legacy_preview_urls(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');
        foreach (['profile_card', 'profile_card_v1', 'profile_card_v2', 'profile_card_v3', 'profile_card_v4'] as $type) {
            $res = $this->post(route('admin.block-defaults.preview', $type));
            $this->assertSame(200, $res->getStatusCode(), $type);
            $this->assertStringNotContainsString('cannot be previewed here', $res->getContent(), $type);
            $this->assertStringContainsString('Your Name', $res->getContent(), $type);
        }
    }

    public function test_style_overrides_are_reflected_in_preview(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');

        $res = $this->post(route('admin.block-defaults.preview', 'paragraph'), [
            'style' => ['grid_span' => '6', 'bg_color' => '#123456', 'bg_opacity' => '100', 'display_mode' => 'card'],
        ]);

        $res->assertOk();
        $html = $res->getContent();
        $this->assertStringContainsString('grid-column: span 6', $html);
        $this->assertStringContainsString('#123456', $html);
    }

    public function test_content_json_overrides_replace_placeholder_content(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');

        $res = $this->post(route('admin.block-defaults.preview', 'heading'), [
            'content_json' => json_encode(['text' => 'Custom Preview Headline 9137']),
        ]);

        $res->assertOk();
        $this->assertStringContainsString('Custom Preview Headline 9137', $res->getContent());
    }

    public function test_invalid_content_json_returns_422(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');

        $res = $this->post(route('admin.block-defaults.preview', 'heading'), [
            'content_json' => '{not json',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('error.message', 'Invalid JSON in content overrides.');
    }

    public function test_unknown_type_404s(): void
    {
        $this->actingAs($this->makeAdmin(), 'admin');

        $this->post(route('admin.block-defaults.preview', 'not-a-real-type'))
            ->assertNotFound();
    }

    public function test_guest_is_redirected(): void
    {
        $res = $this->post(route('admin.block-defaults.preview', 'heading'));

        $this->assertContains($res->getStatusCode(), [302, 401, 403]);
    }
}
