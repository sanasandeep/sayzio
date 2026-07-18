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

    public function test_catalog_returns_157_presets(): void
    {
        $all = BgPresetCatalog::all();
        $this->assertCount(157, $all,
            'BgPresetCatalog should contain exactly 157 presets (41 gradients + 100 abstract + 16 patterns)');
    }

    public function test_catalog_has_three_groups(): void
    {
        $all = BgPresetCatalog::all();
        $byGroup = [];
        foreach ($all as $item) {
            $byGroup[$item['group']] = ($byGroup[$item['group']] ?? 0) + 1;
        }
        $this->assertArrayHasKey('gradients', $byGroup);
        $this->assertArrayHasKey('abstract',  $byGroup);
        $this->assertArrayHasKey('patterns',  $byGroup);
        $this->assertSame(41,  $byGroup['gradients'], 'Expected 41 gradient presets');
        $this->assertSame(100, $byGroup['abstract'],  'Expected 100 abstract presets');
        $this->assertSame(16,  $byGroup['patterns'],  'Expected 16 pattern presets');
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
}
