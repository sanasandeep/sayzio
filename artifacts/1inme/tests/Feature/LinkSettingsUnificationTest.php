<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the link-settings single-source-of-truth (see
 * .agents/memory/link-settings-unification.md). Asserts that:
 *   (a) the biolink editor + short links both write the SEO/favicon Link
 *       columns and the biolink editor strips the legacy JSON mirrors;
 *   (b) the biolink visibility selector persists the Link.visibility column;
 *   (c) the file-create form only stores settings.open_in_app when the plan
 *       gate allows the deep-link feature;
 *   (d) Link::interstitialMode()/previewPageEnabled() return the right value
 *       per link type (and splash wins over a per-type preview page).
 */
class LinkSettingsUnificationTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function biolink(User $u): Link
    {
        return $u->links()->create([
            'user_id' => $u->id, 'type' => 'biolink',
            'alias'   => 'bl' . substr(Str::random(8), 0, 8),
            'is_active' => true,
        ]);
    }

    private function shortLink(User $u, string $type = 'url'): Link
    {
        return $u->links()->create([
            'user_id'  => $u->id, 'type' => $type,
            'alias'    => 'sl' . substr(Str::random(8), 0, 8),
            'long_url' => 'https://example.com',
            'is_active' => true,
        ]);
    }

    // ===== (a) SEO/favicon write the Link columns + strip JSON mirrors =====

    public function test_biolink_editor_writes_seo_columns_and_strips_json_mirrors(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'max_biolinks' => 5, 'custom_favicon' => true]));
        $link = $this->biolink($u);
        // Seed legacy JSON mirrors so we can prove the save strips them.
        $link->update(['settings' => ['biolink' => [
            'meta' => ['seo_title' => 'old', 'seo_description' => 'old desc'],
            'og'   => ['image_url' => 'https://old.example.com/og.png'],
            'favicon_url' => 'https://old.example.com/fav.ico',
        ]]]);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'meta' => ['seo_title' => 'New Title', 'seo_description' => 'New description'],
            'og'   => ['image_url' => 'https://cdn.example.com/share.png'],
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
        ]);
        $resp->assertSessionMissing('error');

        $link->refresh();
        // Canonical columns are written.
        $this->assertSame('New Title', $link->seo_title);
        $this->assertSame('New description', $link->seo_description);
        $this->assertSame('https://cdn.example.com/share.png', $link->seo_image);
        $this->assertSame('https://cdn.example.com/favicon.ico', $link->favicon);

        // Legacy JSON mirrors are stripped so they can never diverge.
        $bio = $link->settings['biolink'] ?? [];
        $this->assertArrayNotHasKey('seo_title', $bio['meta'] ?? []);
        $this->assertArrayNotHasKey('seo_description', $bio['meta'] ?? []);
        $this->assertArrayNotHasKey('image_url', $bio['og'] ?? []);
        $this->assertArrayNotHasKey('favicon_url', $bio);
    }

    public function test_biolink_editor_clears_seo_columns_when_fields_emptied(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'max_biolinks' => 5, 'custom_favicon' => true]));
        $link = $this->biolink($u);
        $link->update([
            'seo_title' => 'Existing', 'seo_description' => 'Existing desc',
            'seo_image' => 'https://cdn.example.com/old.png',
        ]);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'meta' => ['seo_title' => '', 'seo_description' => ''],
            'og'   => ['image_url' => ''],
        ]);
        $resp->assertSessionMissing('error');

        $link->refresh();
        $this->assertNull($link->seo_title);
        $this->assertNull($link->seo_description);
        $this->assertNull($link->seo_image);
    }

    public function test_short_link_store_writes_seo_columns(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'seo_title' => 'Short SEO', 'seo_description' => 'Short SEO desc',
        ]);
        $link = $u->links()->latest('id')->first();
        $this->assertNotNull($link);
        $this->assertSame('Short SEO', $link->seo_title);
        $this->assertSame('Short SEO desc', $link->seo_description);
    }

    public function test_short_link_store_writes_favicon_column(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan(['max_links' => 100]));
        $this->actingAs($u)->post('/user/links', [
            'type' => 'url', 'long_url' => 'https://example.com',
            'favicon' => UploadedFile::fake()->create('favicon.png', 4, 'image/png'),
        ]);
        $link = $u->links()->latest('id')->first();
        $this->assertNotNull($link);
        $this->assertNotEmpty($link->favicon, 'short link should persist the favicon canonical column');
        // Favicon is canonical on the column, not mirrored into the settings JSON.
        $this->assertArrayNotHasKey('favicon_url', (array) ($link->settings ?? []));
    }

    // ===== (a') Short-link UPDATE keeps the canonical columns in sync =====

    public function test_short_link_update_writes_seo_columns(): void
    {
        Storage::fake('user_files');
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->shortLink($u);

        $resp = $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url'        => 'https://example.com',
            'seo_title'       => 'Edited SEO',
            'seo_description' => 'Edited SEO desc',
            'seo_image'       => UploadedFile::fake()->image('share.png', 1200, 800),
            'favicon'         => UploadedFile::fake()->image('fav.png', 64, 64),
        ]);
        $resp->assertSessionMissing('error');

        $link->refresh();
        // Canonical columns are written by the update path.
        $this->assertSame('Edited SEO', $link->seo_title);
        $this->assertSame('Edited SEO desc', $link->seo_description);
        $this->assertNotEmpty($link->seo_image, 'update should persist the seo_image column');
        $this->assertNotEmpty($link->favicon, 'update should persist the favicon column');
        // SEO/favicon stay canonical on the columns, never mirrored into JSON.
        $settings = (array) ($link->settings ?? []);
        $this->assertArrayNotHasKey('favicon_url', $settings);
        $this->assertArrayNotHasKey('seo_title', $settings);
    }

    public function test_short_link_update_enables_open_in_app_when_plan_allows(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'link_deep_link' => true]));
        $link = $this->shortLink($u);

        $resp = $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url'    => 'https://example.com',
            'open_in_app' => 1,
        ]);
        $resp->assertSessionMissing('error');

        // The url-type deep-link toggle syncs into settings.open_in_app.
        $this->assertTrue((bool) ($link->fresh()->settings['open_in_app'] ?? false));
    }

    public function test_short_link_update_blocks_open_in_app_when_plan_downgraded(): void
    {
        // The plan does not include the deep-link feature. Editing the link
        // must not let the user enable the deep-link toggle.
        $u = $this->user($this->plan(['max_links' => 100, 'link_deep_link' => false]));
        $link = $this->shortLink($u);
        $this->assertArrayNotHasKey('open_in_app', (array) ($link->settings ?? []));

        $resp = $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url'    => 'https://example.com',
            'open_in_app' => 1,
        ]);
        // Plan gate rejects the edit outright with a session error.
        $resp->assertSessionHas('error');

        // And the downgraded plan can never end up with the deep-link enabled.
        $this->assertFalse((bool) ($link->fresh()->settings['open_in_app'] ?? false));
    }

    public function test_short_link_update_persists_and_clears_show_preview_page(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $link = $this->shortLink($u);

        // Enable the preview page.
        $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url'          => 'https://example.com',
            'show_preview_page' => 1,
        ])->assertSessionMissing('error');
        $this->assertTrue((bool) ($link->fresh()->settings['show_preview_page'] ?? false));

        // Unchecking it clears the flag from settings.
        $this->actingAs($u)->put('/user/links/' . $link->id, [
            'long_url'          => 'https://example.com',
            'show_preview_page' => 0,
        ])->assertSessionMissing('error');
        $this->assertArrayNotHasKey('show_preview_page', (array) ($link->fresh()->settings ?? []));
    }

    public function test_short_link_update_persists_show_preview_page_for_ics_and_vcf(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));

        foreach (['ics', 'vcf'] as $type) {
            $link = $this->shortLink($u, $type);
            $this->actingAs($u)->put('/user/links/' . $link->id, [
                'show_preview_page' => 1,
            ])->assertSessionMissing('error');
            $this->assertTrue(
                (bool) ($link->fresh()->settings['show_preview_page'] ?? false),
                "$type update should persist show_preview_page"
            );
        }
    }

    // ===== (b) Visibility selector persists Link.visibility =====

    public function test_biolink_editor_persists_visibility_column(): void
    {
        $u = $this->user($this->plan(['max_links' => 100, 'max_biolinks' => 5]));
        $link = $this->biolink($u);

        $resp = $this->actingAs($u)->post('/user/links/' . $link->id . '/page-settings', [
            'visibility' => 'registered',
        ]);
        $resp->assertSessionMissing('error');

        $this->assertSame('registered', $link->fresh()->visibility);
    }

    // ===== (c) File create stores settings.open_in_app only when plan-gated =====

    private function fakeUpload(): UploadedFile
    {
        Storage::fake('user_files');
        return UploadedFile::fake()->createWithContent('share.txt', 'hello world');
    }

    public function test_file_create_stores_open_in_app_when_plan_allows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => true,
            'max_file_size_mb' => 10, 'storage_limit_mb' => 100,
        ]));

        $this->actingAs($u)->post('/user/links-file', [
            'alias' => 'fa' . substr(Str::random(8), 0, 8), 'title' => 'File A',
            'file' => $this->fakeUpload(),
            'open_in_app' => 1,
        ]);

        $link = $u->links()->where('type', 'file')->latest('id')->first();
        $this->assertNotNull($link);
        $this->assertTrue((bool) ($link->settings['open_in_app'] ?? false));
    }

    public function test_file_create_blocks_open_in_app_when_plan_disallows(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => false,
            'max_file_size_mb' => 10, 'storage_limit_mb' => 100,
        ]));

        $resp = $this->actingAs($u)->post('/user/links-file', [
            'alias' => 'fb' . substr(Str::random(8), 0, 8), 'title' => 'File B',
            'file' => $this->fakeUpload(),
            'open_in_app' => 1,
        ]);
        // Plan gate rejects with a session error and creates no link.
        $resp->assertSessionHas('error');
        $this->assertSame(0, $u->links()->where('type', 'file')->count());
    }

    public function test_file_create_omits_open_in_app_when_not_requested(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => true,
            'max_file_size_mb' => 10, 'storage_limit_mb' => 100,
        ]));

        $this->actingAs($u)->post('/user/links-file', [
            'alias' => 'fc' . substr(Str::random(8), 0, 8), 'title' => 'File C',
            'file' => $this->fakeUpload(),
        ]);

        $link = $u->links()->where('type', 'file')->latest('id')->first();
        $this->assertNotNull($link);
        // File deep-link is opt-in: an unchecked toggle must not enable it.
        $this->assertArrayNotHasKey('open_in_app', (array) ($link->settings ?? []));
    }

    public function test_file_create_defaults_title_and_alias_when_blank(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => true,
            'max_file_size_mb' => 10, 'storage_limit_mb' => 100,
        ]));

        // No title or alias keys at all — the API/automation path. Must not 500.
        $resp = $this->actingAs($u)->post('/user/links-file', [
            'file' => $this->fakeUpload(),
        ]);
        $resp->assertSessionMissing('error');

        $link = $u->links()->where('type', 'file')->latest('id')->first();
        $this->assertNotNull($link);
        // Title falls back to the uploaded file's original name.
        $this->assertSame('share.txt', $link->title);
        // Alias is auto-generated (non-empty).
        $this->assertNotEmpty($link->alias);
    }

    public function test_file_create_returns_json_error_when_plan_gate_hit(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => false,
            'max_file_size_mb' => 10, 'storage_limit_mb' => 100,
        ]));

        // Automation client (Accept: application/json) hitting the deep-link
        // plan gate must get a structured JSON error, not an HTML redirect.
        $resp = $this->actingAs($u)->postJson('/user/links-file', [
            'alias' => 'fj' . substr(Str::random(8), 0, 8), 'title' => 'File J',
            'file' => $this->fakeUpload(),
            'open_in_app' => 1,
        ]);

        $resp->assertStatus(403);
        $resp->assertJsonPath('error.message', 'The "deep link" link setting isn\'t available on your current plan. Upgrade to enable it.');
        $this->assertSame(0, $u->links()->where('type', 'file')->count());
    }

    public function test_file_create_returns_json_error_when_file_too_large(): void
    {
        $u = $this->user($this->plan([
            'max_links' => 100, 'link_deep_link' => true,
            'max_file_size_mb' => 1, 'storage_limit_mb' => 100,
        ]));

        Storage::fake('user_files');
        // 2 MB upload against a 1 MB per-file plan limit -> validation 422.
        $resp = $this->actingAs($u)->postJson('/user/links-file', [
            'alias' => 'fk' . substr(Str::random(8), 0, 8), 'title' => 'File K',
            'file' => UploadedFile::fake()->create('big.bin', 2048),
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonStructure(['errors' => ['file']]);
        $this->assertSame(0, $u->links()->where('type', 'file')->count());
    }

    // ===== (d) interstitialMode()/previewPageEnabled() per type =====

    public function test_preview_page_enabled_for_url_type_via_settings(): void
    {
        $on = new Link(['type' => 'url', 'settings' => ['show_preview_page' => true]]);
        $this->assertTrue($on->previewPageEnabled());
        $this->assertSame('preview', $on->interstitialMode());

        $off = new Link(['type' => 'url', 'settings' => []]);
        $this->assertFalse($off->previewPageEnabled());
        $this->assertSame('none', $off->interstitialMode());
    }

    public function test_preview_page_enabled_for_ics_and_vcf_via_settings(): void
    {
        foreach (['ics', 'vcf'] as $type) {
            $on = new Link(['type' => $type, 'settings' => ['show_preview_page' => true]]);
            $this->assertTrue($on->previewPageEnabled(), "$type preview should be on");

            $off = new Link(['type' => $type, 'settings' => []]);
            $this->assertFalse($off->previewPageEnabled(), "$type preview should be off");
        }
    }

    public function test_preview_page_disabled_for_biolink_type(): void
    {
        // Biolink family doesn't use settings.show_preview_page at all.
        $bio = new Link(['type' => 'biolink', 'settings' => ['show_preview_page' => true]]);
        $this->assertFalse($bio->previewPageEnabled());
        $this->assertSame('none', $bio->interstitialMode());
    }

    public function test_preview_page_enabled_for_file_type_via_file_link_flag(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));

        $on = $u->links()->create([
            'user_id' => $u->id, 'type' => 'file',
            'alias' => 'f' . substr(Str::random(8), 0, 8), 'is_active' => true,
        ]);
        FileLink::create([
            'link_id' => $on->id, 'original_name' => 'a.txt', 'stored_path' => 'x/a.txt',
            'mime_type' => 'text/plain', 'file_size' => 5, 'disk' => 'user_files',
            'show_download_page' => true,
        ]);
        $this->assertTrue($on->fresh()->previewPageEnabled());

        $off = $u->links()->create([
            'user_id' => $u->id, 'type' => 'file',
            'alias' => 'g' . substr(Str::random(8), 0, 8), 'is_active' => true,
        ]);
        FileLink::create([
            'link_id' => $off->id, 'original_name' => 'b.txt', 'stored_path' => 'x/b.txt',
            'mime_type' => 'text/plain', 'file_size' => 5, 'disk' => 'user_files',
            'show_download_page' => false,
        ]);
        $this->assertFalse($off->fresh()->previewPageEnabled());
    }

    public function test_interstitial_mode_splash_wins_over_preview(): void
    {
        $u = $this->user($this->plan(['max_links' => 100]));
        $splash = SplashPage::create([
            'user_id' => $u->id, 'name' => 'Splash ' . Str::random(4),
        ]);
        $link = $u->links()->create([
            'user_id' => $u->id, 'type' => 'url',
            'alias' => 's' . substr(Str::random(8), 0, 8), 'is_active' => true,
            'settings' => ['show_preview_page' => true],
            'splash_page_id' => $splash->id, 'splash_enabled' => true,
        ]);
        // Both a preview page and a splash are enabled — splash takes priority.
        $this->assertSame('splash', $link->fresh()->interstitialMode());
    }
}
