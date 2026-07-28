<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BgPresetCatalog;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the BgPresetCatalog catalog and the biolink editor's
 * preset-background save path (background_type=preset + bg_preset_key).
 *
 * Invariants:
 *  - Catalog contains 157 entries covering three groups.
 *  - A valid preset key is accepted and persisted on the link's settings.
 *  - An invalid (unknown) key is rejected with a 422.
 *  - Switching away from preset (e.g. back to 'color') persists normally.
 */
class BiolinkBgPresetSaveTest extends TestCase
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
            'alias'     => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    // ===== Catalog unit =====

    public function test_catalog_returns_179_presets(): void
    {
        $all = BgPresetCatalog::all();
        $this->assertCount(179, $all,
            'BgPresetCatalog should contain exactly 179 presets (60 gradients + 100 abstract + 16 patterns + 3 torn)');
    }

    public function test_catalog_has_expected_groups(): void
    {
        $all = BgPresetCatalog::all();
        $byGroup = [];
        foreach ($all as $item) {
            $byGroup[$item['group']] = ($byGroup[$item['group']] ?? 0) + 1;
        }
        $this->assertArrayHasKey('gradients', $byGroup);
        $this->assertArrayHasKey('abstract',  $byGroup);
        $this->assertArrayHasKey('patterns',  $byGroup);
        $this->assertArrayHasKey('torn',      $byGroup);
        $this->assertSame(60,  $byGroup['gradients'], 'Expected 60 gradient presets');
        $this->assertSame(100, $byGroup['abstract'],  'Expected 100 abstract presets');
        $this->assertSame(16,  $byGroup['patterns'],  'Expected 16 pattern presets');
        $this->assertSame(3,   $byGroup['torn'],      'Expected 3 torn presets');
    }

    public function test_findByKey_returns_entry_for_known_key(): void
    {
        $entry = BgPresetCatalog::findByKey('gradient_zero');
        $this->assertNotNull($entry);
        $this->assertArrayHasKey('css', $entry);
        $this->assertNotEmpty($entry['css']);
        $this->assertSame('gradients', $entry['group']);
    }

    public function test_findByKey_returns_null_for_unknown_key(): void
    {
        $this->assertNull(BgPresetCatalog::findByKey('totally_fake_key'));
        $this->assertNull(BgPresetCatalog::findByKey(''));
    }

    public function test_css_returns_non_empty_string_for_valid_key(): void
    {
        $css = BgPresetCatalog::css('abstract_one');
        $this->assertIsString($css);
        $this->assertNotEmpty($css);
        // Must look like a CSS declaration (contains a colon separator).
        $this->assertStringContainsString(':', $css);
    }

    public function test_css_returns_null_for_invalid_key(): void
    {
        $this->assertNull(BgPresetCatalog::css('nonexistent_key'));
    }

    public function test_all_preset_css_values_are_non_empty_strings(): void
    {
        foreach (BgPresetCatalog::all() as $key => $entry) {
            $this->assertIsString($entry['css'], "CSS for preset '$key' should be a string");
            $this->assertNotEmpty($entry['css'], "CSS for preset '$key' should not be empty");
            // No raw PHP or server-side code should have leaked into CSS values.
            $this->assertStringNotContainsString('<?', $entry['css'],
                "CSS for preset '$key' must not contain PHP open tags");
        }
    }

    // ===== Save path: valid preset =====

    public function test_page_settings_accepts_valid_preset_key(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'preset',
            'bg_preset_key'   => 'gradient_zero',
        ]);

        $resp->assertRedirect();
        $resp->assertSessionMissing('error');

        $link->refresh();
        $bio = $link->settings['biolink'] ?? [];
        $this->assertSame('preset',        $bio['background_type'] ?? null);
        $this->assertSame('gradient_zero', $bio['bg_preset_key']   ?? null);
    }

    public function test_page_settings_accepts_abstract_and_pattern_preset_keys(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        foreach (['abstract_one', 'abs_back_1'] as $key) {
            $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
                'background_type' => 'preset',
                'bg_preset_key'   => $key,
            ]);
            $resp->assertSessionMissing('error');
            $link->refresh();
            $bio = $link->settings['biolink'] ?? [];
            $this->assertSame($key, $bio['bg_preset_key'] ?? null,
                "preset key '$key' should be persisted");
        }
    }

    // ===== Save path: invalid preset key is rejected =====

    public function test_page_settings_rejects_unknown_preset_key(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'preset',
            'bg_preset_key'   => 'definitely_not_a_real_preset',
        ]);

        $resp->assertSessionHasErrors('bg_preset_key');
    }

    public function test_page_settings_rejects_preset_key_with_invalid_chars(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'preset',
            'bg_preset_key'   => '<script>alert(1)</script>',
        ]);

        $resp->assertSessionHasErrors('bg_preset_key');
    }

    public function test_page_settings_rejects_unknown_background_type(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'hacked_type',
        ]);

        $resp->assertSessionHasErrors('background_type');
    }

    // ===== Switching back from preset to another type =====

    public function test_switching_from_preset_to_color_persists_correctly(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        // First save as preset.
        $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'preset',
            'bg_preset_key'   => 'gradient_zero',
        ]);

        // Then switch to color.
        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type' => 'color',
            'background_color' => '#112233',
        ]);

        $resp->assertSessionMissing('error');
        $link->refresh();
        $bio = $link->settings['biolink'] ?? [];
        $this->assertSame('color',   $bio['background_type']  ?? null);
        $this->assertSame('#112233', $bio['background_color'] ?? null);
    }

    // ===== Page-level preset transparency (Task #5970) =====

    public function test_page_settings_persists_preset_opacity(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type'   => 'preset',
            'bg_preset_key'     => 'gradient_zero',
            'bg_preset_opacity' => 65,
        ])->assertSessionMissing('error');

        $bio = $link->fresh()->settings['biolink'] ?? [];
        $this->assertSame('gradient_zero', $bio['bg_preset_key'] ?? null);
        $this->assertEquals(65, $bio['bg_preset_opacity'] ?? null);
    }

    public function test_page_settings_accepts_zero_preset_opacity(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'background_type'   => 'preset',
            'bg_preset_key'     => 'gradient_zero',
            'bg_preset_opacity' => 0,
        ])->assertSessionMissing('error');

        $bio = $link->fresh()->settings['biolink'] ?? [];
        $this->assertEquals(0, $bio['bg_preset_opacity'] ?? null);
    }

    public function test_page_settings_rejects_out_of_range_preset_opacity(): void
    {
        $u    = $this->user();
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->from('/x')->post('/user/links/' . $link->id . '/page-settings', [
            'background_type'   => 'preset',
            'bg_preset_key'     => 'gradient_zero',
            'bg_preset_opacity' => 150,
        ]);
        $resp->assertSessionHasErrors('bg_preset_opacity');
    }

    // ===== Block-level preset backgrounds (Task #5970) =====

    private function makeBlock(User $u, Link $link, string $type = 'heading'): \App\Modules\User\Models\BiolinkBlock
    {
        $resp = $this->actingAs($u)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => $type]);
        $resp->assertOk();

        return \App\Modules\User\Models\BiolinkBlock::where('link_id', $link->id)
            ->latest('id')->firstOrFail();
    }

    private function saveBlockStyle(User $u, Link $link, $block, array $style)
    {
        return $this->actingAs($u)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", ['style' => $style]);
    }

    public function test_block_persists_valid_preset_key_and_opacity(): void
    {
        $u     = $this->user();
        $link  = $this->biolink($u);
        $block = $this->makeBlock($u, $link);

        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key'     => 'abstract_one',
            'bg_preset_opacity' => 40,
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertSame('abstract_one', $style['bg_preset_key'] ?? null);
        $this->assertEquals(40, $style['bg_preset_opacity'] ?? null);
    }

    public function test_block_rejects_torn_preset_key(): void
    {
        $u     = $this->user();
        $link  = $this->biolink($u);
        $block = $this->makeBlock($u, $link);

        $this->assertTrue(BgPresetCatalog::isTorn('torn_cream'));

        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key' => 'torn_cream',
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_preset_key', $style,
            'Torn presets need full-page layers and must be dropped at block level');
    }

    public function test_block_drops_unknown_preset_key(): void
    {
        $u     = $this->user();
        $link  = $this->biolink($u);
        $block = $this->makeBlock($u, $link);

        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key' => 'not_a_real_preset_key',
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_preset_key', $style);
    }

    public function test_block_clamps_preset_opacity_to_bounds(): void
    {
        $u     = $this->user();
        $link  = $this->biolink($u);
        $block = $this->makeBlock($u, $link);

        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key'     => 'gradient_zero',
            'bg_preset_opacity' => 400,
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertEquals(100, $style['bg_preset_opacity'] ?? null);
    }

    public function test_block_clearing_preset_key_removes_it(): void
    {
        $u     = $this->user();
        $link  = $this->biolink($u);
        $block = $this->makeBlock($u, $link);

        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key' => 'gradient_zero',
        ])->assertOk();
        $this->assertSame('gradient_zero',
            $block->fresh()->settings['_style']['bg_preset_key'] ?? null);

        // Empty string = the picker's hidden input after "Remove".
        $this->saveBlockStyle($u, $link, $block, [
            'bg_preset_key' => '',
        ])->assertOk();

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayNotHasKey('bg_preset_key', $style);
    }
}
