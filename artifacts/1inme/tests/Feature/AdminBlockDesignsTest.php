<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\AdminBlockDesigns;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBlockDesignsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
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

    // ── Index ────────────────────────────────────────────────────────

    public function test_index_renders_with_built_in_variants_and_templates(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.block-designs.index'))
            ->assertOk()
            ->assertSee('Designs gallery variants')
            ->assertSee('Block Theme presets');
    }

    public function test_create_forms_render(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'admin')
            ->get(route('admin.block-designs.variants.create'))
            ->assertOk()
            ->assertSee('Style payload (JSON)');
        $this->actingAs($admin, 'admin')
            ->get(route('admin.block-designs.templates.create'))
            ->assertOk()
            ->assertSee('Style payload (JSON)');
    }

    // ── Variant CRUD ─────────────────────────────────────────────────

    public function test_admin_can_create_custom_variant_and_it_merges_into_catalog(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.save'), [
                'name'       => 'Midnight Pop',
                'tags'       => ['dark'],
                'types'      => ['link'],
                'enabled'    => 1,
                'style_json' => json_encode([
                    'bg_color'      => '#111827',
                    'text_color'    => '#f9fafb',
                    'border_radius' => 22,
                ]),
            ])->assertRedirect(route('admin.block-designs.index'));

        $customs = AdminBlockDesigns::customVariants();
        $this->assertCount(1, $customs);
        $key = $customs[0]['key'];
        $this->assertStringStartsWith(AdminBlockDesigns::KEY_PREFIX, $key);

        // Merged into the gallery catalog for the matching type…
        $keys = array_column(BlockVariantCatalog::forType('link'), 'key');
        $this->assertContains($key, $keys);
        // …but not for a non-matching type.
        $headingKeys = array_column(BlockVariantCatalog::forType('heading'), 'key');
        $this->assertNotContains($key, $headingKeys);

        // Resolvable via find() so applying it works.
        $found = BlockVariantCatalog::find('link', $key);
        $this->assertNotNull($found);
        $this->assertSame('#111827', $found['style']['bg_color']);
    }

    public function test_variant_style_payload_is_sanitized_through_the_editor_allowlist(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.save'), [
                'name'       => 'Sneaky',
                'style_json' => json_encode([
                    'bg_color'         => '#222222',
                    'evil_key'         => '<script>alert(1)</script>',
                    'font_size'        => 9999,
                    '_variant'         => 'spoofed',
                    '_template'        => 'spoofed',
                    'apply_to_all'     => true,
                ]),
            ])->assertRedirect(route('admin.block-designs.index'));

        $style = AdminBlockDesigns::customVariants()[0]['style'];
        $this->assertSame('#222222', $style['bg_color']);
        $this->assertArrayNotHasKey('evil_key', $style);
        $this->assertEquals(72, $style['font_size']); // out of bounds → clamped to max
        $this->assertArrayNotHasKey('_variant', $style);
        $this->assertArrayNotHasKey('_template', $style);
        $this->assertArrayNotHasKey('apply_to_all', $style);
    }

    public function test_fully_invalid_style_payload_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.block-designs.variants.create'))
            ->post(route('admin.block-designs.variants.save'), [
                'name'       => 'Nothing Valid',
                'style_json' => json_encode(['bogus' => 'x']),
            ])->assertRedirect(route('admin.block-designs.variants.create'))
            ->assertSessionHasErrors('style_json');

        $this->assertCount(0, AdminBlockDesigns::customVariants());
    }

    public function test_admin_can_edit_and_delete_custom_variant(): void
    {
        $saved = AdminBlockDesigns::saveVariant([
            'key' => '', 'name' => 'Original', 'tags' => [], 'shape' => '',
            'types' => [], 'style' => ['bg_color' => '#000000'], 'enabled' => true,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.save'), [
                'key'        => $saved['key'],
                'name'       => 'Renamed',
                'style_json' => json_encode(['bg_color' => '#ffffff']),
            ])->assertRedirect(route('admin.block-designs.index'));

        $v = AdminBlockDesigns::findCustomVariant($saved['key']);
        $this->assertSame('Renamed', $v['name']);
        $this->assertSame('#ffffff', $v['style']['bg_color']);

        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.block-designs.variants.delete', $saved['key']))
            ->assertRedirect(route('admin.block-designs.index'));
        $this->assertNull(AdminBlockDesigns::findCustomVariant($saved['key']));
    }

    public function test_editing_unknown_or_built_in_key_is_404(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.block-designs.variants.edit', 'classic'))
            ->assertNotFound();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.save'), [
                'key'        => 'classic',
                'name'       => 'Hijack',
                'style_json' => json_encode(['bg_color' => '#ff0000']),
            ])->assertNotFound();
    }

    public function test_built_in_variant_cannot_be_deleted_but_can_be_hidden(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.block-designs.variants.delete', 'classic'))
            ->assertNotFound();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.toggle', 'classic'), ['hidden' => 1])
            ->assertRedirect();

        // Hidden from the gallery…
        $galleryKeys = array_column(BlockVariantCatalog::forType('link'), 'key');
        $this->assertNotContains('classic', $galleryKeys);
        // …but still resolvable, so blocks wearing it keep rendering.
        $this->assertNotNull(BlockVariantCatalog::find('link', 'classic'));

        // Un-hide restores it.
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.variants.toggle', 'classic'), ['hidden' => 0])
            ->assertRedirect();
        $galleryKeys = array_column(BlockVariantCatalog::forType('link'), 'key');
        $this->assertContains('classic', $galleryKeys);
    }

    public function test_catalog_version_bumps_on_admin_change(): void
    {
        $before = BlockVariantCatalog::version();
        AdminBlockDesigns::saveVariant([
            'key' => '', 'name' => 'Bump', 'tags' => [], 'shape' => '',
            'types' => [], 'style' => ['bg_color' => '#123456'], 'enabled' => true,
        ]);
        $this->assertGreaterThan($before, BlockVariantCatalog::version());
    }

    // ── Theme templates ─────────────────────────────────────────────

    public function test_admin_can_create_custom_theme_preset_and_it_merges(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.templates.save'), [
                'label'      => 'Neon Night',
                'icon'       => 'fa-bolt',
                'enabled'    => 1,
                'style_json' => json_encode([
                    'bg_color'   => '#0f172a',
                    'text_color' => '#a5f3fc',
                ]),
            ])->assertRedirect(route('admin.block-designs.index'));

        $customs = AdminBlockDesigns::customTemplates();
        $this->assertCount(1, $customs);
        $key = array_key_first($customs);

        $merged = BiolinkBlock::blockTemplates();
        $this->assertArrayHasKey($key, $merged);
        $this->assertSame('Neon Night', $merged[$key]['label']);
    }

    public function test_built_in_template_hidden_from_picker_but_still_resolvable(): void
    {
        $builtIn = array_key_first(BiolinkBlock::BLOCK_TEMPLATES);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-designs.templates.toggle', $builtIn), ['hidden' => 1])
            ->assertRedirect();

        $this->assertArrayNotHasKey($builtIn, BiolinkBlock::blockTemplates(true));
        $this->assertArrayHasKey($builtIn, BiolinkBlock::blockTemplates(false));

        // Built-ins can never be deleted.
        $this->actingAs($this->admin(), 'admin')
            ->delete(route('admin.block-designs.templates.delete', $builtIn))
            ->assertNotFound();
    }

    // ── Access control ──────────────────────────────────────────────

    public function test_guests_cannot_access_block_designs(): void
    {
        $this->get(route('admin.block-designs.index'))->assertRedirect();
        $this->post(route('admin.block-designs.variants.save'), [
            'name' => 'Nope', 'style_json' => '{"bg_color":"#000000"}',
        ])->assertRedirect();
        $this->assertCount(0, AdminBlockDesigns::customVariants());
    }
}
