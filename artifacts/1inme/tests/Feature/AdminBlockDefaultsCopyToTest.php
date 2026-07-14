<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Support\BlockDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBlockDefaultsCopyToTest extends TestCase
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

    public function test_index_renders_copy_modal_for_customised_rows(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'style' => ['border_radius' => '16'],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.block-defaults.index'))
            ->assertOk()
            ->assertSee('Copy overrides from', false)
            ->assertSee('copy-to', false);
    }

    public function test_copy_to_applies_source_override_to_targets(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'style'   => ['border_radius' => '16', 'shadow_preset' => 'soft'],
            'content' => ['text' => 'Sample'],
        ]);

        $response = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'link'), [
                'targets' => ['heading', 'paragraph_rich'],
            ]);

        $response->assertRedirect(route('admin.block-defaults.index'));
        $response->assertSessionHas('success');

        $overrides = BlockDefaults::getAdminOverrides();
        foreach (['heading', 'paragraph_rich'] as $target) {
            $this->assertSame('16', $overrides[$target]['style']['border_radius'] ?? null);
            $this->assertSame('soft', $overrides[$target]['style']['shadow_preset'] ?? null);
            $this->assertSame('Sample', $overrides[$target]['content']['text'] ?? null);
        }
    }

    public function test_copy_replaces_existing_target_override_exactly(): void
    {
        // Source has ONLY style; target starts with content that must be cleared.
        BlockDefaults::saveAdminOverrideForType('link', [
            'style' => ['border_radius' => '8'],
        ]);
        BlockDefaults::saveAdminOverrideForType('heading', [
            'content' => ['text' => 'Old heading text'],
            'style'   => ['border_radius' => '99'],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'link'), [
                'targets' => ['heading'],
            ])->assertRedirect(route('admin.block-defaults.index'));

        $heading = BlockDefaults::getAdminOverrides()['heading'] ?? [];
        $this->assertSame(['border_radius' => '8'], $heading['style'] ?? null);
        $this->assertArrayNotHasKey('content', $heading);
    }

    public function test_copy_from_type_without_override_errors(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'divider'), [
                'targets' => ['heading'],
            ])
            ->assertRedirect(route('admin.block-defaults.index'))
            ->assertSessionHasErrors('copy');

        $this->assertArrayNotHasKey('heading', BlockDefaults::getAdminOverrides());
    }

    public function test_source_and_invalid_types_are_excluded_from_targets(): void
    {
        BlockDefaults::saveAdminOverrideForType('link', [
            'style' => ['border_radius' => '16'],
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'link'), [
                'targets' => ['link', 'not_a_real_type'],
            ])
            ->assertRedirect(route('admin.block-defaults.index'))
            ->assertSessionHasErrors('copy');
    }

    public function test_invalid_source_type_404s(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.block-defaults.copy-to', 'nope_type'), [
                'targets' => ['heading'],
            ])->assertNotFound();
    }

    public function test_guest_cannot_copy(): void
    {
        $response = $this->post(route('admin.block-defaults.copy-to', 'link'), [
            'targets' => ['heading'],
        ]);
        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertArrayNotHasKey('heading', BlockDefaults::getAdminOverrides());
    }
}
