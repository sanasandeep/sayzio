<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MeshGradientCatalog;
use App\Modules\User\Support\PatternCatalog;
use App\Modules\User\Support\TilesBgCatalog;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6204 regression coverage for the simplified background presets:
 *  - New background types (tiles / mesh / pattern) save round-trip and
 *    stamp bg_effect_colors for the mobile fallback.
 *  - Torn tear styles + backdrop colors save; invalid keys are rejected.
 *  - Legacy saved keys (retired gradient / torn preset groups) still
 *    render on the public page unchanged.
 */
class BiolinkBackgroundTypesTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function biolink(User $u): Link
    {
        return $u->links()->create([
            'user_id'   => $u->id,
            'type'      => 'biolink',
            'alias'     => 'bt' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    private function save(User $u, Link $link, array $payload)
    {
        return $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', $payload);
    }

    // ===== New types: save round-trip + bg_effect_colors stamp =====

    public function test_tiles_background_saves_and_stamps_effect_colors(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->save($u, $link, [
            'background_type' => 'tiles',
            'tiles_palette'   => 'tiles_midnight',
            'tiles_layout'    => 'metro',
            'tiles_animate'   => '1',
        ]);
        $resp->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('tiles', $bio['background_type'] ?? null);
        $this->assertSame('tiles_midnight', $bio['tiles_palette'] ?? null);
        $this->assertSame('metro', $bio['tiles_layout'] ?? null);
        $this->assertSame('1', $bio['tiles_animate'] ?? null);
        $this->assertSame(TilesBgCatalog::colors('tiles_midnight'), $bio['bg_effect_colors'] ?? null);
    }

    public function test_mesh_background_saves_and_stamps_effect_colors(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->save($u, $link, [
            'background_type' => 'mesh',
            'mesh_preset'     => 'mesh_aurora',
        ])->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('mesh', $bio['background_type'] ?? null);
        $this->assertSame('mesh_aurora', $bio['mesh_preset'] ?? null);
        $this->assertSame(MeshGradientCatalog::colors('mesh_aurora'), $bio['bg_effect_colors'] ?? null);
    }

    public function test_pattern_background_saves_and_stamps_effect_colors(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->save($u, $link, [
            'background_type' => 'pattern',
            'pattern_preset'  => 'pattern_dots_dark',
        ])->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('pattern', $bio['background_type'] ?? null);
        $this->assertSame('pattern_dots_dark', $bio['pattern_preset'] ?? null);
        $this->assertSame(PatternCatalog::colors('pattern_dots_dark'), $bio['bg_effect_colors'] ?? null);
    }

    public function test_switching_away_from_effect_type_clears_effect_colors(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->save($u, $link, [
            'background_type' => 'mesh',
            'mesh_preset'     => 'mesh_noir',
        ]);
        $this->assertNotEmpty($link->refresh()->settings['biolink']['bg_effect_colors'] ?? null);

        $this->save($u, $link, [
            'background_type'  => 'color',
            'background_color' => '#112233',
        ])->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('color', $bio['background_type'] ?? null);
        $this->assertArrayNotHasKey('bg_effect_colors', $bio);
    }

    public function test_invalid_effect_keys_are_rejected(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        foreach ([
            ['background_type' => 'tiles', 'tiles_palette' => 'not_a_palette'],
            ['background_type' => 'mesh', 'mesh_preset' => 'mesh_bogus'],
            ['background_type' => 'pattern', 'pattern_preset' => 'pattern_bogus'],
            ['background_type' => 'tiles', 'tiles_palette' => 'tiles_midnight', 'tiles_layout' => 'diagonal-weird'],
        ] as $payload) {
            $resp = $this->save($u, $link, $payload);
            $resp->assertSessionHasErrors();
        }

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertArrayNotHasKey('bg_effect_colors', $bio);
    }

    // ===== Torn: styles + backdrop colors =====

    public function test_torn_style_and_backdrop_colors_save(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->save($u, $link, [
            'background_type'      => 'torn',
            'torn_style'           => 'deckled',
            'torn_paper_color'     => '#e2f3e8',
            'torn_backdrop_color'  => '#69a888',
            'torn_backdrop_color2' => '#2f5d48',
        ])->assertSessionMissing('error');

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('torn', $bio['background_type'] ?? null);
        $this->assertSame('deckled', $bio['torn_style'] ?? null);
        $this->assertSame('#69a888', $bio['torn_backdrop_color'] ?? null);
        $this->assertSame('#2f5d48', $bio['torn_backdrop_color2'] ?? null);
    }

    public function test_unknown_torn_style_is_rejected(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->save($u, $link, [
            'background_type' => 'torn',
            'torn_style'      => 'shredded',
        ])->assertSessionHasErrors();
    }

    public function test_all_catalog_torn_styles_have_sheets(): void
    {
        foreach (TornStyleCatalog::styles() as $key => $label) {
            $this->assertNotEmpty(TornStyleCatalog::sheets($key), "Style [{$key}] has no sheets");
        }
        // Legacy pages without a stored style fall back to the diagonal tear.
        $this->assertSame(TornStyleCatalog::sheets(TornStyleCatalog::DEFAULT), TornStyleCatalog::sheets(null));
        // Every quick-combo references a real style.
        foreach (TornStyleCatalog::PRESETS as $combo) {
            $this->assertTrue(TornStyleCatalog::isValidStyle($combo['style']));
        }
    }

    // ===== Legacy keys keep rendering on the public page =====

    public function test_legacy_gradient_and_torn_preset_keys_still_render_publicly(): void
    {
        $u    = $this->user();

        foreach (['gradient_zero', 'torn_paper_1'] as $legacyKey) {
            $link = $this->biolink($u);
            $link->update([
                'settings' => array_merge($link->settings ?? [], [
                    'biolink' => [
                        'background_type' => 'preset',
                        'bg_preset_key'   => $legacyKey,
                    ],
                ]),
            ]);

            // Public visitors carry no workspace binding.
            app()->forgetInstance('current_workspace');
            app()->forgetInstance('workspace_owner');

            $resp = $this->get('/' . $link->alias);
            $resp->assertOk();
        }
    }

    public function test_new_effect_types_render_publicly(): void
    {
        $u = $this->user();

        $cases = [
            ['background_type' => 'tiles', 'tiles_palette' => 'tiles_ocean', 'tiles_layout' => 'brick', 'tiles_animate' => '1'],
            ['background_type' => 'mesh', 'mesh_preset' => 'mesh_lagoon'],
            ['background_type' => 'pattern', 'pattern_preset' => 'pattern_grid_dark'],
            ['background_type' => 'torn', 'torn_style' => 'stack', 'torn_backdrop_color' => '#8aa6b4', 'torn_backdrop_color2' => '#46626f'],
        ];

        foreach ($cases as $bio) {
            $link = $this->biolink($u);
            $link->update([
                'settings' => array_merge($link->settings ?? [], ['biolink' => $bio]),
            ]);

            app()->forgetInstance('current_workspace');
            app()->forgetInstance('workspace_owner');

            $this->get('/' . $link->alias)->assertOk();
        }
    }
}
