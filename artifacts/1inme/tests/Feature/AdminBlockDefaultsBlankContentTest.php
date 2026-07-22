<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Support\BlockDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBlockDefaultsBlankContentTest extends TestCase
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

    // ── Explicit blank overrides ────────────────────────────────────

    public function test_explicit_empty_string_override_is_honoured_as_blank(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.block-defaults.update', 'link'), [
                'content_json' => json_encode(['text' => '']),
            ])->assertRedirect(route('admin.block-defaults.edit', 'link'));

        $content = BlockDefaults::contentForType('link');
        $this->assertSame('', $content['text']);
        // URL was not overridden, so the system default survives.
        $this->assertNotSame('', $content['url'] ?? '');
    }

    public function test_explicit_empty_array_override_is_honoured_as_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('list', [
            'content' => ['items' => []],
        ]);

        $content = BlockDefaults::contentForType('list');
        $this->assertSame([], $content['items']);
    }

    public function test_placeholder_flag_suppressed_when_all_sample_content_blanked(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'content' => ['text' => '', 'url' => '', 'icon' => ''],
        ]);

        $content = BlockDefaults::contentForType('link');
        $this->assertArrayNotHasKey('_placeholder', $content);
    }

    public function test_placeholder_flag_kept_when_sample_content_remains(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'content' => ['text' => 'Custom label'],
        ]);

        $content = BlockDefaults::contentForType('link');
        $this->assertTrue((bool) ($content['_placeholder'] ?? false));
    }

    // ── Start blank flag ────────────────────────────────────────────

    public function test_start_blank_saves_and_blanks_seeded_content(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.block-defaults.update', 'heading'), [
                'content_json' => '',
                'start_blank'  => '1',
            ])->assertRedirect(route('admin.block-defaults.edit', 'heading'));

        $this->assertTrue(BlockDefaults::startBlankForType('heading'));

        $content = BlockDefaults::contentForType('heading');
        $this->assertSame('', $content['text']);
        // Structural keys survive blanking.
        $this->assertArrayHasKey('size', $content);
        $this->assertNotSame('', $content['size']);
        // No placeholder banner for intentionally-blank content.
        $this->assertArrayNotHasKey('_placeholder', $content);
    }

    public function test_start_blank_blanks_list_arrays_but_keeps_structure(): void
    {
        BlockDefaults::saveAdminOverrideForType('link_tree_group', ['start_blank' => true]);

        $content = BlockDefaults::contentForType('link_tree_group');
        $this->assertSame('', $content['title']);
        $this->assertSame([], $content['items']);
        $this->assertSame('list', $content['layout']);
        $this->assertNotSame('', $content['accent_color']);
    }

    public function test_content_override_applies_on_top_of_start_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'start_blank' => true,
            'content'     => ['text' => 'Only this label'],
        ]);

        $content = BlockDefaults::contentForType('link');
        $this->assertSame('Only this label', $content['text']);
        $this->assertSame('', $content['url']);
    }

    public function test_unchecking_start_blank_restores_system_defaults(): void
    {
        BlockDefaults::saveAdminOverrideForType('heading', ['start_blank' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.block-defaults.update', 'heading'), [
                'content_json' => '',
                'start_blank'  => '0',
            ])->assertRedirect();

        $this->assertFalse(BlockDefaults::startBlankForType('heading'));
        $content = BlockDefaults::contentForType('heading');
        $this->assertNotSame('', $content['text']);
        $this->assertTrue((bool) ($content['_placeholder'] ?? false));
    }

    public function test_copy_to_carries_start_blank_flag(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'start_blank' => true,
            'content'     => ['text' => ''],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'link'), [
                'targets' => ['heading'],
            ])->assertRedirect(route('admin.block-defaults.index'));

        $this->assertTrue(BlockDefaults::startBlankForType('heading'));
        $this->assertSame('', BlockDefaults::contentForType('heading')['text']);
    }

    // ── Editor page & preview ───────────────────────────────────────

    public function test_edit_page_renders_friendly_content_fields_and_start_blank_toggle(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.block-defaults.edit', 'link'))
            ->assertOk()
            ->assertSee('checkbox-start-blank', false)
            ->assertSee('content-field-text', false)
            ->assertSee('content-field-url', false);
    }

    public function test_preview_honours_start_blank_and_blank_overrides(): void
    {
        $response = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.preview', 'link'), [
                'content_json' => '',
                'start_blank'  => '1',
            ]);

        $response->assertOk();
        $this->assertStringNotContainsString('My Link', $response->getContent());
    }

    public function test_seeded_settings_for_new_block_are_blank_when_start_blank(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', ['start_blank' => true]);

        $settings = BlockDefaults::seededSettings('link');
        $this->assertSame('', $settings['text']);
        $this->assertSame('', $settings['url']);
        $this->assertArrayNotHasKey('_placeholder', $settings);
        $this->assertIsArray($settings['_style']);
    }
}
